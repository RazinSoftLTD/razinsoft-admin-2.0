<?php

namespace App\Console\Commands;

use App\Support\FinanceSync;
use Illuminate\Console\Command;

/**
 * One-off: pull every invoice payment recorded before the Finance module existed into
 * Finance as income. Safe to re-run — each payment maps to exactly one income row.
 */
class FinanceBackfillIncome extends Command
{
    protected $signature = 'finance:backfill-income';

    protected $description = 'Mirror existing invoice payments into Finance as income';

    public function handle(): int
    {
        $this->info('Mirroring invoice payments into Finance…');
        $count = FinanceSync::backfillPayments();
        $this->info("Done — {$count} payment(s) processed.");
        $this->line('Income with no wallet/bank yet shows as "Unassigned"; open Transactions to attach them.');

        return self::SUCCESS;
    }
}
