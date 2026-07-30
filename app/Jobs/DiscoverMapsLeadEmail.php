<?php

namespace App\Jobs;

use App\Models\MapsLead;
use App\Models\MapsOutreachSetting;
use App\Services\MapsLeadEmailFinder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Looks up one lead's email address, then hands it on for outreach if the
 * settings say so.
 *
 * Queued per lead so a slow or dead website never holds up collection: the
 * extension keeps posting leads while these run behind it.
 */
class DiscoverMapsLeadEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 45;

    public function __construct(public int $leadId) {}

    public function handle(MapsLeadEmailFinder $finder): void
    {
        $settings = MapsOutreachSetting::current();

        if (! $settings->discovers()) {
            return;
        }

        $lead = MapsLead::find($this->leadId);

        if (! $lead || $lead->email) {
            return; // gone, or already has an address
        }

        if (blank($lead->website)) {
            $lead->forceFill([
                'email_status' => 'no_website',
                'email_checked_at' => now(),
                'email_attempts' => $lead->email_attempts + 1,
            ])->save();

            return;
        }

        $result = $finder->findAll($lead->website);

        /*
         * Keep every address, not just the one we will write to. The rest are
         * what an operator falls back on when the generic inbox goes unanswered,
         * and having them recorded is the difference between a lead with one
         * dead address and a lead with three live ones.
         */
        foreach ($result['emails'] as $row) {
            \App\Models\MapsLeadEmail::updateOrCreate(
                ['maps_lead_id' => $lead->id, 'email' => $row['email']],
                [
                    'source_url' => $row['source_url'],
                    'is_generic' => $row['is_generic'],
                    'same_domain' => $row['same_domain'],
                ],
            );
        }

        /*
         * The address on the lead itself is the one outreach uses, so it must be
         * a shared inbox. Mailing rahim@company.com unsolicited is a different
         * thing entirely from mailing info@company.com, both legally and in how
         * it is received.
         */
        $primary = collect($result['emails'])->firstWhere('is_generic', true);

        $lead->forceFill([
            'email' => $primary['email'] ?? null,
            'email_source' => $primary ? 'website' : null,
            'email_status' => $result['status'],
            'email_checked_at' => now(),
            'email_attempts' => $lead->email_attempts + 1,
        ])->save();

        RecordMapsCollectionEvent::dispatch([
            'run_id' => $lead->last_run_id,
            'level' => $result['emails'] ? 'info' : 'debug',
            'event' => 'email.lookup',
            'message' => $result['emails']
                ? sprintf(
                    'Found %d address(es) for %s across %d page(s)%s',
                    count($result['emails']),
                    $lead->name,
                    $result['pages'],
                    $primary ? ": using {$primary['email']}" : ' - none is a shared inbox, so none will be mailed',
                )
                : "No email for {$lead->name}: {$result['note']}",
            'place_key' => $lead->place_key,
            'lead_id' => $lead->id,
        ]);

        if (! $primary) {
            return;
        }

        if ($settings->sendsAutomatically()) {
            SendMapsLeadOutreach::dispatch($lead->id);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Lead email lookup failed outright.', ['lead' => $this->leadId, 'error' => $e->getMessage()]);

        MapsLead::where('id', $this->leadId)->update([
            'email_status' => 'failed',
            'email_checked_at' => now(),
        ]);
    }
}
