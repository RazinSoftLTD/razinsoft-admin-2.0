<?php

namespace App\Console\Commands;

use App\Models\EnvatoSetting;
use App\Services\Envato\EnvatoSync;
use Illuminate\Console\Command;
use Throwable;

/** Daily refresh of the CodeCanyon watchlist — also what builds the sales-history snapshots. */
class SyncCodeCanyon extends Command
{
    protected $signature = 'codecanyon:sync {--force : Run even when auto-sync is switched off}';

    protected $description = 'Sync watched CodeCanyon authors and products from the official Envato API.';

    public function handle(EnvatoSync $sync): int
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

        try {
            [$authors, $products] = $sync->all();
            $this->info("Synced {$authors} author(s) and {$products} product(s).");
        } catch (Throwable $e) {
            $settings->update(['last_error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
