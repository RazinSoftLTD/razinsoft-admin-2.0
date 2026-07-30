<?php

namespace App\Jobs;

use App\Models\EmailSuppression;
use App\Models\MapsLead;
use App\Models\MapsOutreachSetting;
use App\Services\Email\EmailDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Sends one outreach message to a collected lead.
 *
 * Everything about how mail actually goes out - SMTP account, suppression list,
 * duplicate guard, logging, bounce and open tracking - is EmailDispatcher's job
 * already. This class only decides *whether* this particular lead should be
 * mailed right now, and records the outcome on the lead.
 *
 * Five things stop a send, checked in this order:
 *   1. outreach turned off
 *   2. no address, or already contacted
 *   3. the address is on the suppression list (bounced, complained, unsubscribed)
 *   4. the lead's country is outside the allowed list
 *   5. today's ceiling is used up, or the gap since the last message is too short
 *
 * A lead deferred by (5) is re-queued rather than dropped, so a day's worth of
 * leads goes out spread over the day instead of all at once.
 */
class SendMapsLeadOutreach implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $leadId) {}

    public function handle(EmailDispatcher $mailer): void
    {
        $settings = MapsOutreachSetting::current();

        if (! $settings->sendsAutomatically()) {
            return;
        }

        $lead = MapsLead::find($this->leadId);

        if (! $lead || blank($lead->email) || $lead->outreach_sent_at) {
            return;
        }

        if (EmailSuppression::has($lead->email)) {
            $this->mark($lead, 'suppressed', 'address is on the suppression list');

            return;
        }

        if (! $settings->allowsCountry($lead->search_country)) {
            $this->mark($lead, 'skipped', "country not enabled: {$lead->search_country}");

            return;
        }

        if ($settings->remainingToday() < 1) {
            // Try again tomorrow rather than burning the attempt.
            $this->release(now()->addDay()->startOfDay()->diffInSeconds(now()) ?: 3600);

            return;
        }

        if (! $settings->gapElapsed()) {
            $this->release($settings->min_gap_seconds);

            return;
        }

        $log = $mailer->sendTemplate(
            $settings->template_key,
            $lead->email,
            $this->variables($lead),
            [
                'to_name' => $lead->name,
                'module' => 'maps-leads',
                'related' => $lead,
                'config_id' => $settings->email_config_id,
                'event' => 'maps_lead.outreach',
            ],
        );

        if (! $log) {
            // Dispatcher refused it: inactive template, no SMTP account, duplicate,
            // or an address it considers unsendable. Its own log says which.
            $this->mark($lead, 'refused', 'the mailer declined to queue this message');

            return;
        }

        $settings->countSend();
        $this->mark($lead, 'sent', null);

        RecordMapsCollectionEvent::dispatch([
            'run_id' => $lead->last_run_id,
            'level' => 'info',
            'event' => 'outreach.sent',
            'message' => "Outreach queued to {$lead->email} ({$lead->name})",
            'place_key' => $lead->place_key,
            'lead_id' => $lead->id,
        ]);
    }

    /**
     * Template variables. `unsubscribe_url` is not optional - an opt-out link is
     * what separates lawful outreach from spam, and the template is expected to
     * render it.
     *
     * @return array<string, string>
     */
    private function variables(MapsLead $lead): array
    {
        return [
            'business_name' => $lead->name,
            'business_category' => (string) $lead->category,
            'business_city' => (string) $lead->search_city,
            'business_country' => (string) $lead->search_country,
            'business_website' => (string) $lead->website,
            'business_phone' => (string) $lead->phone,
            'unsubscribe_url' => route('outreach.unsubscribe', $lead->unsubscribeToken()),
        ];
    }

    private function mark(MapsLead $lead, string $status, ?string $error): void
    {
        $lead->forceFill([
            'outreach_status' => $status,
            'outreach_sent_at' => $status === 'sent' ? now() : null,
            'outreach_error' => $error,
        ])->save();
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Lead outreach failed.', ['lead' => $this->leadId, 'error' => $e->getMessage()]);

        MapsLead::where('id', $this->leadId)->update([
            'outreach_status' => 'failed',
            'outreach_error' => mb_substr($e->getMessage(), 0, 190),
        ]);
    }
}
