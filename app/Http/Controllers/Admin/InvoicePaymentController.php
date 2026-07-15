<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InvoicePaymentController extends Controller
{
    /** Record a (partial) payment against an invoice; due recalculates automatically. */
    public function store(Request $request, ClientInvoice $invoice)
    {
        abort_unless($request->user()->canAct('invoices', 'finance', $invoice), 403);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max(0.01, $invoice->amountDue())],
            'paid_at' => ['required', 'date'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'currency' => ['nullable', 'string', 'max:8'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'method' => ['nullable', 'string', 'max:60'],        // Payment Gateway
            'reference' => ['nullable', 'string', 'max:120'],     // Transaction Id
            'bank_account' => ['nullable', 'string', 'max:255'], // "Bank Information" (multi-line)
            'receipt' => ['nullable', 'file', 'max:5120'],
            'note' => ['nullable', 'string', 'max:1000'],         // Remark
        ], [], ['amount' => 'payment amount']);

        $receipt = $request->hasFile('receipt') ? $request->file('receipt')->store('invoices/receipts', 'public') : null;

        $invoice->payments()->create([
            'amount' => $data['amount'],
            'paid_at' => $data['paid_at'],
            'project_id' => $data['project_id'] ?? null,
            'currency' => $data['currency'] ?? $invoice->currency,
            'exchange_rate' => $data['exchange_rate'] ?? null,
            'method' => $data['method'] ?? $invoice->payment_method,
            'reference' => $data['reference'] ?? null,
            'bank_account' => $data['bank_account'] ?? null,
            'receipt' => $receipt,
            'note' => $data['note'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        $invoice->recomputePaid();
        $invoice->logActivity('payment_added',
            'Added a payment of '.$invoice->currencySymbol().number_format((float) $data['amount'], 2)
            .($data['method'] ?? null ? ' via '.$data['method'] : '').'.');

        return back()->with('status', 'Payment of '.number_format($data['amount'], 2).' recorded. Due is now '.number_format($invoice->amountDue(), 2).'.');
    }

    public function destroy(Request $request, ClientInvoice $invoice, InvoicePayment $payment)
    {
        abort_unless($request->user()->canAct('invoices', 'finance', $invoice), 403);
        abort_unless($payment->client_invoice_id === $invoice->id, 404);

        $amount = (float) $payment->amount;
        if ($payment->receipt) {
            Storage::disk('public')->delete($payment->receipt);
        }
        $payment->delete();
        $invoice->recomputePaid();
        $invoice->logActivity('payment_deleted', 'Deleted a payment of '.$invoice->currencySymbol().number_format($amount, 2).'.');

        return back()->with('status', 'Payment removed. Due is now '.number_format($invoice->amountDue(), 2).'.');
    }
}
