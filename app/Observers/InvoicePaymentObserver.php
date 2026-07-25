<?php

namespace App\Observers;

use App\Models\InvoicePayment;
use App\Support\FinanceSync;

/**
 * Payments get recorded from three different screens (admin, client portal, public pay link),
 * so the Finance mirror hangs off the model rather than any one controller.
 */
class InvoicePaymentObserver
{
    public function created(InvoicePayment $payment): void
    {
        FinanceSync::recordPayment($payment);
    }

    public function updated(InvoicePayment $payment): void
    {
        FinanceSync::recordPayment($payment);
    }

    /**
     * Must run on `deleting`, not `deleted`: the finance row's foreign key is ON DELETE SET NULL,
     * so by the time the row is gone the link we match on has already been cleared.
     */
    public function deleting(InvoicePayment $payment): void
    {
        FinanceSync::removePayment($payment);
    }
}
