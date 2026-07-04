<?php

namespace App\Http\Controllers;

use App\Models\ClientInvoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Stripe\StripeClient;

/** Public (token-guarded, no login) invoice pay flow. The page itself lives on the FRONTEND; this
 *  controller exposes a JSON endpoint for it and handles the Stripe checkout + payment recording. */
class InvoicePayController extends Controller
{
    /** JSON payload for the frontend pay page — the full invoice. */
    public function apiShow(string $token)
    {
        $invoice = ClientInvoice::where('public_token', $token)->with('items', 'payments')->firstOrFail();
        $cur = $invoice->currencySymbol();

        return response()->json([
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'currency' => $invoice->currency,
            'currency_symbol' => $cur,
            'status' => $invoice->status,
            'status_label' => ClientInvoice::STATUSES[$invoice->status] ?? $invoice->status,
            'bill_to' => [
                'name' => $invoice->bill_to_name,
                'company' => $invoice->bill_to_company,
                'email' => $invoice->bill_to_email,
                'phone' => $invoice->bill_to_phone,
                'address' => $invoice->bill_to_address,
            ],
            'items' => $invoice->items->map(fn ($i) => [
                'description' => $i->description,
                'sub_description' => $i->sub_description,
                'qty' => (float) $i->qty,
                'unit_price' => (float) $i->unit_price,
                'tax_percent' => (float) $i->tax_percent,
                'amount' => (float) $i->amount,
            ])->values(),
            'subtotal' => (float) $invoice->subtotal,
            'discount_total' => (float) $invoice->discount_total,
            'tax_total' => (float) $invoice->tax_total,
            'total' => (float) $invoice->total,
            'amount_paid' => (float) $invoice->amount_paid,
            'amount_due' => $invoice->amountDue(),
            'payable_amount' => $invoice->payableAmount(),
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
            'payments' => $invoice->payments->map(fn ($p) => [
                'amount' => (float) $p->amount,
                'paid_at' => $p->paid_at?->toDateString(),
                'method' => $p->method,
            ])->values(),
            // Where the "Pay Now" button sends the client.
            'checkout_url' => route('pay.invoice.checkout', $invoice->public_token),
        ]);
    }

    /** Old backend URL → redirect to the frontend pay page (keeps shared links working). */
    public function show(string $token)
    {
        $invoice = ClientInvoice::where('public_token', $token)->firstOrFail();

        return redirect()->away($invoice->payUrl());
    }

    /** Start payment for the payable amount — Stripe Checkout, or a local dev fallback. */
    public function checkout(string $token)
    {
        $invoice = ClientInvoice::where('public_token', $token)->firstOrFail();
        $amount = $invoice->payableAmount();

        if ($amount <= 0) {
            return redirect()->away($invoice->payUrl());
        }

        if (config('services.stripe.secret')) {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'client_reference_id' => $invoice->invoice_number,
                'metadata' => ['client_invoice_id' => $invoice->id],
                'success_url' => route('pay.invoice.success', $token).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $invoice->payUrl(),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($invoice->currency),
                        'unit_amount' => (int) round($amount * 100),
                        'product_data' => ['name' => "Invoice {$invoice->invoice_number}"],
                    ],
                ]],
            ]);

            return redirect()->away($session->url);
        }

        // Local dev fallback (no Stripe keys) — simulate a successful gateway return.
        return redirect()->route('pay.invoice.success', ['token' => $token, 'dev' => 1]);
    }

    /** Payment succeeded (Stripe return / dev) — record it, then send the client back to the frontend page. */
    public function success(Request $request, string $token)
    {
        $invoice = ClientInvoice::where('public_token', $token)->firstOrFail();
        $amount = $invoice->payableAmount();
        $reference = $request->query('session_id') ?: 'dev-'.now()->timestamp;

        if ($sessionId = $request->query('session_id')) {
            if (! config('services.stripe.secret')) {
                abort(400);
            }
            $session = (new StripeClient(config('services.stripe.secret')))->checkout->sessions->retrieve($sessionId);
            if (($session->payment_status ?? null) !== 'paid') {
                return redirect()->away($invoice->payUrl());
            }
            $amount = round(($session->amount_total ?? 0) / 100, 2);
        }

        if ($amount > 0 && ! InvoicePayment::where('client_invoice_id', $invoice->id)->where('reference', $reference)->exists()) {
            $invoice->payments()->create([
                'amount' => min($amount, $invoice->amountDue()),
                'paid_at' => now()->toDateString(),
                'method' => 'Stripe',
                'reference' => $reference,
                'note' => 'Paid online',
            ]);
            $invoice->update(['requested_amount' => null]);
            $invoice->recomputePaid();
        }

        return redirect()->away($invoice->payUrl().'?paid=1');
    }
}
