<?php

namespace App\Console\Commands;

use App\Models\EnvatoSetting;
use App\Models\EnvatoSyncRun;
use App\Services\Envato\SyncRunner;
use Illuminate\Console\Command;

/** Daily refresh of the CodeCanyon watchlist — also what builds the sales-history snapshots. */
class SyncCodeCanyon extends Command
{
    protected $signature = 'codecanyon:sync
        {--force : Run even when auto-sync is switched off}
        {--catch-up : Only run when today has no snapshot yet}
        {--now : Run inline instead of queueing it}';

    protected $description = 'Sync watched CodeCanyon authors and products from the official Envato API.';

    public function handle(SyncRunner $runner): int
    {
        $settings = EnvatoSetting::current();

        if (! $settings->isConfigured()) {
            $this->warn('No Envato token configured — skipping.');

            return self::SUCCESS;
        }
        if (! $settings->auto_sync && ! $this->option('force')) {
            $this->line('Auto-sync is off — skipping.');

            return self::SUCCESS;
        }

        // The catch-up pass exists because a missed day cannot be recovered: Envato
        // serves only today's numbers, so if the 04:00 run failed the gap is
        // permanent unless something tries again before midnight.
        if ($this->option('catch-up') && $runner->capturedToday()) {
            $this->line("Today's snapshot is already recorded — nothing to catch up.");

            return self::SUCCESS;
        }

        $trigger = $this->option('catch-up') ? 'catch-up' : 'schedule';

        if ($this->option('now')) {
            return $this->report($runner->execute(EnvatoSyncRun::create(['trigger' => $trigger])));
        }

        if (! $run = $runner->queue($trigger)) {
            $this->warn('Could not queue a sync.');

            return self::FAILURE;
        }

        $this->info("Sync #{$run->id} is {$run->status}.");

        return self::SUCCESS;
    }

    private function report(EnvatoSyncRun $run): int
    {
        if ($run->status === 'failed') {
            $this->error($run->error);

            return self::FAILURE;
        }

        $this->info("Synced {$run->authors_synced} author(s) and {$run->products_synced} product(s); {$run->snapshots_written} snapshot(s) recorded.");

        return self::SUCCESS;
    }
}
