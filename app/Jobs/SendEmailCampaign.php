<?php

namespace App\Jobs;

use App\Models\EmailCampaign;
use App\Models\EmailTemplate;
use App\Services\Email\CampaignAudience;
use App\Services\Email\EmailDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queues a campaign's messages, a batch at a time.
 *
 * The batching is the point. Dumping ten thousand messages into the queue at once is what makes a
 * provider throttle or block a domain, so this queues a slice, spaces the rest out with a delay,
 * and comes back for the next slice — each message still going through EmailDispatcher, so the
 * suppression list, validation and logging all apply exactly as they do everywhere else.
 */
class SendEmailCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Messages queued per run, and how far apart the batches are spread. */
    private const BATCH_SIZE = 100;

    private const BATCH_GAP_SECONDS = 60;

    public int $tries = 3;

    public function __construct(public int $campaignId) {}

    public function handle(EmailDispatcher $dispatcher, CampaignAudience $audience): void
    {
        $campaign = EmailCampaign::with('template')->find($this->campaignId);

        if (! $campaign || ! in_array($campaign->status, ['scheduled', 'sending'], true)) {
            return;                                   // deleted, or cancelled while queued
        }

        // First run: work out who it goes to and record them, so progress can be shown and a
        // re-run cannot mail anyone twice.
        if ($campaign->status === 'scheduled') {
            $this->buildRecipientList($campaign, $audience);
        }

        $pending = $campaign->recipients()->where('status', 'pending')->limit(self::BATCH_SIZE)->get();

        if ($pending->isEmpty()) {
            $campaign->forceFill(['status' => 'sent', 'finished_at' => now()])->save();

            return;
        }

        foreach ($pending as $recipient) {
            $body = $this->bodyFor($campaign, $recipient->name);

            /*
             * A Maps lead's log points at the lead, not the campaign, so the
             * lead's own engagement history is a single relation. Campaign
             * progress and stats read email_campaign_recipients, which links
             * both ways, so nothing is lost by doing this.
             */
            $lead = $recipient->maps_lead_id
                ? \App\Models\MapsLead::find($recipient->maps_lead_id)
                : null;

            $log = $dispatcher->send($recipient->email, $body['subject'], $body['html'], $body['text'], [
                'to_name' => $recipient->name,
                'config_id' => $campaign->email_config_id,
                'template_id' => $campaign->email_template_id,
                'user_id' => $recipient->user_id,
                'module' => $lead ? 'maps-leads' : 'campaign',
                'related' => $lead ?: $campaign,
                'created_by' => $campaign->created_by,
                // Each recipient gets their own copy; the dedupe check is about accidental
                // double-sends, not about a campaign reaching many people.
                'dedupe' => false,
            ]);

            $recipient->update([
                'email_log_id' => $log?->id,
                // A null log means the dispatcher refused it — almost always a suppression that
                // appeared after the list was built.
                'status' => $log ? 'queued' : 'skipped',
            ]);

            // Mark the lead as contacted so no other path mails it again.
            if ($log && $lead && ! $lead->outreach_sent_at) {
                $lead->forceFill(['outreach_status' => 'sent', 'outreach_sent_at' => now()])->save();
            }
        }

        // More to go: come back after a gap rather than flooding the provider.
        if ($campaign->recipients()->where('status', 'pending')->exists()) {
            self::dispatch($campaign->id)->delay(now()->addSeconds(self::BATCH_GAP_SECONDS));

            return;
        }

        $campaign->forceFill(['status' => 'sent', 'finished_at' => now()])->save();
    }

    private function buildRecipientList(EmailCampaign $campaign, CampaignAudience $audience): void
    {
        $people = $audience->resolve($campaign->audience ?? ['type' => 'all']);

        // A Maps-lead audience resolves to maps_leads rows; anything else to users.
        // The id therefore belongs in a different column, and putting a lead id in
        // user_id would break the foreign key and misattribute the mail.
        $isMaps = CampaignAudience::isMaps($campaign->audience['type'] ?? null);

        foreach ($people as $person) {
            // insertOrIgnore semantics via firstOrCreate: the unique index on (campaign, email)
            // means a re-run can never add someone twice.
            $campaign->recipients()->firstOrCreate(
                ['email' => mb_strtolower($person->email)],
                [
                    'user_id' => $isMaps ? null : $person->id,
                    'maps_lead_id' => $isMaps ? $person->id : null,
                    'name' => $person->name,
                    'status' => 'pending',
                ],
            );
        }

        $campaign->forceFill([
            'status' => 'sending',
            'started_at' => now(),
            'total_recipients' => $campaign->recipients()->count(),
        ])->save();

        Log::info("Campaign [{$campaign->name}] resolved to {$campaign->total_recipients} recipient(s).");
    }

    /** The message for one person — template when chosen, otherwise the campaign's own body. */
    private function bodyFor(EmailCampaign $campaign, ?string $name): array
    {
        $data = ['customer_name' => $name ?: 'there', 'campaign_subject' => $campaign->subject];

        if ($campaign->template) {
            $rendered = $campaign->template->renderFor($data + [
                'campaign_body' => $campaign->body_html,
                'newsletter_subject' => $campaign->subject,
                'newsletter_body' => $campaign->body_html,
            ]);

            return ['subject' => $rendered['subject'], 'html' => $rendered['html'], 'text' => $rendered['text']];
        }

        $globals = EmailTemplate::globalValues();

        return [
            'subject' => EmailTemplate::interpolate($campaign->subject, $data + $globals),
            'html' => EmailTemplate::interpolate((string) $campaign->body_html, $data + $globals),
            'text' => EmailTemplate::interpolate((string) $campaign->body_text, $data + $globals),
        ];
    }

    public function failed(\Throwable $e): void
    {
        EmailCampaign::whereKey($this->campaignId)->update([
            'status' => 'cancelled',
            'updated_at' => now(),
        ]);

        Log::error("Campaign {$this->campaignId} stopped: {$e->getMessage()}");
    }
}
