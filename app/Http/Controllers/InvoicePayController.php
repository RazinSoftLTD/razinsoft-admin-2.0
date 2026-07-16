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
            // Gateways the admin enabled for this invoice (+ the PayPal entry point).
            'pay_methods' => $invoice->payMethods(),
            'paypal_url' => route('pay.invoice.paypal', $invoice->public_token),
            // True when the admin requested a specific (partial) amount.
            'partial_requested' => ! is_null($invoice->requested_amount),
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
            $charged = min($amount, $invoice->amountDue());
            $invoice->payments()->create([
                'amount' => $charged,
                'paid_at' => now()->toDateString(),
                'method' => 'Stripe',
                'currency' => $invoice->currency,
                'reference' => $reference,
                'note' => 'Paid online',
            ]);
            $invoice->update(['requested_amount' => null]);
            $invoice->recomputePaid();
            $invoice->logActivity('payment_added',
                'Client paid '.$invoice->currencySymbol().number_format($charged, 2).' online (Stripe).',
                'client', $invoice->client);
        }

        return redirect()->away($invoice->payUrl().'?paid=1');
    }

    // ---------------------------------------------------------------- PayPal

    /** PayPal REST base URL for the configured mode. */
    private function paypalBase(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function paypalToken(): ?string
    {
        $id = config('services.paypal.client_id');
        $secret = config('services.paypal.secret');
        if (! $id || ! $secret) {
            return null;
        }

        $res = \Illuminate\Support\Facades\Http::asForm()
            ->withBasicAuth($id, $secret)
            ->post($this->paypalBase().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        return $res->successful() ? $res->json('access_token') : null;
    }

    /** Start a PayPal payment for the payable amount. */
    public function paypal(string $token)
    {
        $invoice = ClientInvoice::where('public_token', $token)->firstOrFail();
        $amount = $invoice->payableAmount();

        if ($amount <= 0 || ! in_array('paypal', $invoice->payMethods(), true)) {
            return redirect()->away($invoice->payUrl());
        }

        $access = $this->paypalToken();
        if (! $access) {
            // No PayPal credentials configured — send the client back with a clear flag.
            return redirect()->away($invoice->payUrl().'?paypal=unavailable');
        }

        $res = \Illuminate\Support\Facades\Http::withToken($access)
            ->post($this->paypalBase().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $invoice->invoice_number,
                    'custom_id' => (string) $invoice->id,
                    'amount' => ['currency_code' => strtoupper($invoice->currency), 'value' => number_format($amount, 2, '.', '')],
                    'description' => "Invoice {$invoice->invoice_number}",
                ]],
                'application_context' => [
                    'brand_name' => 'RazinSoft',
                    'user_action' => 'PAY_NOW',
                    'return_url' => route('pay.invoice.paypal.return', $token),
                    'cancel_url' => $invoice->payUrl(),
                ],
            ]);

        $approve = collect($res->json('links') ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return $approve ? redirect()->away($approve) : redirect()->away($invoice->payUrl().'?paypal=error');
    }

    /** PayPal approved — capture the order and record the payment. */
    public function paypalReturn(Request $request, string $token)
    {
        $invoice = ClientInvoice::where('public_token', $token)->firstOrFail();
        $orderId = $request->query('token'); // PayPal returns its order id as ?token=

        $access = $orderId ? $this->paypalToken() : null;
        if (! $access) {
            return redirect()->away($invoice->payUrl());
        }

        $res = \Illuminate\Support\Facades\Http::withToken($access)
            ->withBody('', 'application/json')
            ->post($this->paypalBase()."/v2/checkout/orders/{$orderId}/capture");

        $capture = $res->json('purchase_units.0.payments.captures.0');
        if (($res->json('status') !== 'COMPLETED') || ! $capture || ($capture['status'] ?? null) !== 'COMPLETED') {
            return redirect()->away($invoice->payUrl());
        }

        $amount = round((float) ($capture['amount']['value'] ?? 0), 2);
        $reference = 'paypal-'.($capture['id'] ?? $orderId);

        if ($amount > 0 && ! InvoicePayment::where('client_invoice_id', $invoice->id)->where('reference', $reference)->exists()) {
            $charged = min($amount, $invoice->amountDue());
            $invoice->payments()->create([
                'amount' => $charged,
                'paid_at' => now()->toDateString(),
                'method' => 'PayPal',
                'currency' => $invoice->currency,
                'reference' => $reference,
                'note' => 'Paid online',
            ]);
            $invoice->update(['requested_amount' => null]);
            $invoice->recomputePaid();
            $invoice->logActivity('payment_added',
                'Client paid '.$invoice->currencySymbol().number_format($charged, 2).' online (PayPal).',
                'client', $invoice->client);
        }

        return redirect()->away($invoice->payUrl().'?paid=1');
    }
}
