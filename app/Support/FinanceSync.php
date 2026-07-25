<?php

namespace App\Support;

use App\Models\ClientInvoice;
use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\InvoicePayment;

/**
 * Keeps Finance in step with the Invoice module: every recorded invoice payment shows up as
 * income, and removing the payment removes the income again. Receivables are read straight
 * off the invoices, so there is nothing to sync there.
 */
class FinanceSync
{
    /** Create (or refresh) the income transaction that mirrors an invoice payment. */
    public static function recordPayment(InvoicePayment $payment): ?FinanceTransaction
    {
        $invoice = $payment->invoice;
        $currency = $payment->currency ?: ($invoice->currency ?? 'USD');

        $row = FinanceTransaction::withTrashed()->firstOrNew(['invoice_payment_id' => $payment->id]);
        $row->fill([
            'type' => 'income',
            'direction' => 'in',
            // Match the payment's bank/wallet name when it lines up with a finance account,
            // else fall back to the only active account in that currency. Left blank when
            // neither works — the income still counts, it just isn't tied to a balance yet.
            'account_id' => $row->account_id ?: self::guessAccount($payment->bank_account, $currency)?->id,
            'category_id' => $row->category_id ?: self::incomeCategoryId(),
            'amount' => (float) $payment->amount,
            'currency' => $currency,
            'exchange_rate' => $payment->exchange_rate ?: null,
            'occurred_on' => $payment->paid_at?->toDateString() ?? now()->toDateString(),
            'reference' => $payment->reference ?: ($invoice->invoice_number ?? null),
            'notes' => trim('Invoice payment'.($invoice ? ' · '.$invoice->invoice_number : '').($payment->note ? ' — '.$payment->note : '')),
            'source' => 'invoice',
            'client_invoice_id' => $payment->client_invoice_id,
            'created_by' => $payment->recorded_by,
        ]);
        $row->deleted_at = null;                 // a re-recorded payment revives its income row
        $row->save();

        return $row;
    }

    /** Drop the mirrored income when its payment is removed. */
    public static function removePayment(InvoicePayment $payment): void
    {
        FinanceTransaction::where('invoice_payment_id', $payment->id)->get()->each->delete();
    }

    /** Backfill every existing invoice payment — used once when the module is switched on. */
    public static function backfillPayments(): int
    {
        $count = 0;
        InvoicePayment::with('invoice')->chunkById(200, function ($payments) use (&$count) {
            foreach ($payments as $payment) {
                self::recordPayment($payment);
                $count++;
            }
        });

        return $count;
    }

    /** Unpaid / part-paid invoices — the Receivables list, straight from the Invoice module. */
    public static function receivablesQuery()
    {
        return ClientInvoice::query()
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->whereColumn('amount_paid', '<', 'total');
    }

    private static function guessAccount(?string $name, string $currency): ?FinanceAccount
    {
        $name = trim((string) $name);
        if ($name !== '') {
            $match = FinanceAccount::active()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();
            if ($match) {
                return $match;
            }
        }

        $inCurrency = FinanceAccount::active()->where('currency', $currency)->get();

        return $inCurrency->count() === 1 ? $inCurrency->first() : null;
    }

    private static function incomeCategoryId(): ?int
    {
        return FinanceCategory::ofKind('income')->where('name', 'Client Payment')->value('id')
            ?? FinanceCategory::ofKind('income')->value('id');
    }
}
