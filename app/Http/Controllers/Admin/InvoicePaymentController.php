<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;

class InvoicePaymentController extends Controller
{
    /** Record a (partial) payment against an invoice; due recalculates automatically. */
    public function store(Request $request, ClientInvoice $invoice)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max(0.01, $invoice->amountDue())],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['amount' => 'payment amount']);

        $invoice->payments()->create([
            'amount' => $data['amount'],
            'paid_at' => $data['paid_at'],
            'method' => $data['method'] ?? $invoice->payment_method,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        $invoice->recomputePaid();

        return back()->with('status', 'Payment of '.number_format($data['amount'], 2).' recorded. Due is now '.number_format($invoice->amountDue(), 2).'.');
    }

    public function destroy(ClientInvoice $invoice, InvoicePayment $payment)
    {
        abort_unless($payment->client_invoice_id === $invoice->id, 404);

        $payment->delete();
        $invoice->recomputePaid();

        return back()->with('status', 'Payment removed. Due is now '.number_format($invoice->amountDue(), 2).'.');
    }
}
