<?php

namespace App\Console\Commands;

use App\Models\RecurringInvoice;
use Illuminate\Console\Command;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:recurring';

    protected $description = 'Generate invoices from active recurring profiles that are due';

    public function handle(): int
    {
        $due = RecurringInvoice::where('active', true)
            ->whereDate('next_run_at', '<=', now())
            ->get();

        foreach ($due as $profile) {
            $invoice = $profile->generate();
            $this->info("Generated {$invoice->invoice_number} from profile #{$profile->id}.");
        }

        $this->info("Done — {$due->count()} invoice(s) generated.");

        return self::SUCCESS;
    }
}
