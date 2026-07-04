<?php

namespace App\Http\Controllers;

use App\Models\ClientInvoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Stripe\StripeClient;

/** Public (token-guarded, no login) invoice pay flow for clients. */
class InvoicePayController extends Controller
{
    public function show(string $token)
    {
        $invoice = ClientInvoice::where('public_token', $token)->with('items')->firstOrFail();

        return view('pay.invoice', compact('invoice'));
    }

    /** Start payment for the payable amount — Stripe Checkout, or a local dev fallback. */
    public function checkout(string $token)
    {
        $invoice = ClientInvoice::where('public_token', $token)->firstOrFail();
        $amount = $invoice->payableAmount();

        if ($amount <= 0) {
            return redirect()->route('pay.invoice.show', $token)->with('status', 'This invoice is already fully paid.');
        }

        if (config('services.stripe.secret')) {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'client_reference_id' => $invoice->invoice_number,
                'metadata' => ['client_invoice_id' => $invoice->id, 'amount' => $amount],
                'success_url' => route('pay.invoice.success', $token).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('pay.invoice.show', $token),
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

        // Local dev fallback — no Stripe keys: simulate a successful gateway return.
        return redirect()->route('pay.invoice.success', ['token' => $token, 'dev' => 1]);
    }

    /** Payment succeeded (Stripe return or dev fallback) — record it idempotently. */
    public function success(Request $request, string $token)
    {
        $invoice = ClientInvoice::where('public_token', $token)->firstOrFail();
        $amount = $invoice->payableAmount();
        $reference = $request->query('session_id') ?: 'dev-'.now()->timestamp;

        // If Stripe, confirm the session is actually paid before recording.
        if ($sessionId = $request->query('session_id')) {
            if (! config('services.stripe.secret')) {
                abort(400);
            }
            $session = (new StripeClient(config('services.stripe.secret')))->checkout->sessions->retrieve($sessionId);
            if (($session->payment_status ?? null) !== 'paid') {
                return redirect()->route('pay.invoice.show', $token)->withErrors(['pay' => 'Payment not completed.']);
            }
            $amount = round(($session->amount_total ?? 0) / 100, 2);
        }

        // Idempotent: don't double-record the same gateway reference.
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

        return view('pay.success', compact('invoice'));
    }
}
