<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\MetaCapiSetting;
use App\Services\Meta\ConversionsApi;
use Illuminate\Console\Command;

/**
 * Sends the qualified / unqualified verdicts Meta never heard about.
 *
 * The observer only reports leads judged from now on. Everything already sitting in the CRM is a
 * verdict Meta could learn from and has not seen, and a few hundred at once teaches its model far
 * faster than waiting for new leads to trickle in.
 *
 * Meta refuses events older than seven days, so that is the default window and anything older is
 * skipped rather than silently dropped on their side. --clamp will send older leads stamped at the
 * edge of the window: the verdict lands, the timing is a fiction, and that trade is the operator's
 * to make, not this command's.
 *
 * Safe to re-run: every event carries the same id the observer uses (lead-{id}-{status}), so Meta
 * deduplicates rather than double-counting.
 */
class BackfillLeadQuality extends Command
{
    protected $signature = 'meta:backfill-lead-quality
                            {--days=7 : How far back to look}
                            {--limit= : Stop after this many leads}
                            {--clamp : Send leads older than 7 days stamped at the window edge}
                            {--dry-run : Show what would be sent, send nothing}';

    protected $description = 'Send existing qualified/unqualified leads to Meta as lead-quality events';

    private const EVENT_FOR = ['qualified' => 'QualifiedLead', 'unqualified' => 'UnqualifiedLead'];

    /** Meta's hard limit on how old an event may be. */
    private const MAX_AGE_DAYS = 7;

    public function handle(): int
    {
        $settings = MetaCapiSetting::current();

        if (! $settings->isConfigured() || ! $settings->is_enabled) {
            $this->error('Meta Conversions API is not configured or is switched off.');

            return self::FAILURE;
        }

        $off = collect(self::EVENT_FOR)->reject(fn ($e) => $settings->sends($e));

        if ($off->isNotEmpty()) {
            $this->error('These events are switched off in Settings → Meta Conversions API: '.$off->implode(', '));
            $this->line('Turn them on there first, or nothing will be sent.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $oldest = now()->subDays(self::MAX_AGE_DAYS);
        $dry = (bool) $this->option('dry-run');
        $clamp = (bool) $this->option('clamp');

        $query = Lead::query()
            ->whereIn('lead_status', array_keys(self::EVENT_FOR))
            ->where('updated_at', '>=', $cutoff)
            // No email and no phone means Meta has nothing to match on; the event would be noise.
            // Blank strings count as missing — a cleared field is stored as '' as often as null.
            ->where(fn ($q) => $q->where('email', '!=', '')->whereNotNull('email')
                ->orWhere(fn ($w) => $w->where('phone', '!=', '')->whereNotNull('phone')))
            ->orderBy('id');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('Nothing to send.');

            return self::SUCCESS;
        }

        $tooOld = (clone $query)->where('updated_at', '<', $oldest)->count();

        $this->line("Leads judged in the last {$days} day(s): {$total}");

        if ($tooOld > 0) {
            $this->line($clamp
                ? "  {$tooOld} are older than ".self::MAX_AGE_DAYS." days — sending them stamped at the window edge."
                : "  {$tooOld} are older than ".self::MAX_AGE_DAYS." days — SKIPPED (Meta would reject them). Use --clamp to send anyway.");
        }

        if ($dry) {
            $this->warn('Dry run — nothing will be sent.');
        }

        $bar = $this->output->createProgressBar($total);
        $sent = $skipped = $failed = 0;

        $query->chunkById(100, function ($leads) use (&$sent, &$skipped, &$failed, $bar, $dry, $clamp, $oldest) {
            foreach ($leads as $lead) {
                $bar->advance();

                $when = $lead->updated_at;

                if ($when->lt($oldest)) {
                    if (! $clamp) {
                        $skipped++;

                        continue;
                    }
                    // A minute inside the window, so it is still accepted after the run takes a while.
                    $when = $oldest->copy()->addMinutes(5);
                }

                if ($dry) {
                    $sent++;

                    continue;
                }

                [$first, $last] = array_pad(explode(' ', trim((string) $lead->full_name), 2), 2, null);

                $ok = ConversionsApi::make()->send(
                    self::EVENT_FOR[$lead->lead_status],
                    'lead-'.$lead->id.'-'.$lead->lead_status,
                    ['content_name' => 'Lead '.$lead->lead_status, 'content_category' => $lead->lead_source],
                    [
                        'email' => $lead->email,
                        'phone' => $lead->phone ?: $lead->office_phone,
                        'first_name' => $first,
                        'last_name' => $last,
                        'city' => $lead->city,
                        'country' => $lead->country,
                        'id' => $lead->id,
                    ],
                    null,
                    $when,
                );

                $ok ? $sent++ : $failed++;
            }

            // Meta rate-limits bursts; a short pause between chunks keeps a long run from tripping it.
            if (! $dry) {
                usleep(500_000);
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(($dry ? 'Would send: ' : 'Sent: ').$sent);
        if ($skipped) {
            $this->line('Skipped (too old): '.$skipped);
        }
        if ($failed) {
            $this->error('Failed: '.$failed.' — see the log and Settings → Meta Conversions API for the last error.');
        }

        return self::SUCCESS;
    }
}
