<?php

namespace App\Console\Commands;

use App\Models\ClientInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Permanently remove invoices that have sat in the Bin for more than 30 days. */
class PurgeInvoiceBin extends Command
{
    protected $signature = 'invoices:purge-bin {--days=30}';

    protected $description = 'Permanently delete invoices trashed more than N days ago (default 30).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $stale = ClientInvoice::onlyTrashed()->where('deleted_at', '<', $cutoff)->get();
        foreach ($stale as $invoice) {
            if ($invoice->attachment) {
                Storage::disk('public')->delete($invoice->attachment);
            }
            $invoice->forceDelete();
        }

        $this->info("Purged {$stale->count()} invoice(s) from the Bin older than {$days} days.");

        return self::SUCCESS;
    }
}
