<?php

namespace App\Http\Controllers;

use App\Models\BillingAddress;
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
                // The page shows the form only when this is missing.
                'has_address' => filled($invoice->bill_to_address),
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
            'discount_type' => $invoice->discount_type,
            'discount_value' => (float) $invoice->discount_value,
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
            // Optional short description for the requested amount.
            'partial_note' => $invoice->requested_note,
        ]);
    }

    /** Old backend URL → redirect to the frontend pay page (keeps shared links working). */
    public function show(string $token)
    {
        $invoice = ClientInvoice::where('public_token', $token)->firstOrFail();

        return redirect()->away($invoice->payUrl());
    }

    /** Start payment for the payable amount — Stripe Checkout, or a local dev fallback. */
    /**
     * Let the person paying supply the billing address when the invoice has none.
     *
     * The pay link is the authorisation — whoever holds it is trusted to pay, so they are trusted
     * to say where the bill goes. It is accepted only while the invoice is unpaid and only when
     * there is nothing there yet: an address already on a bill is part of a financial record and
     * is not something a link should be able to rewrite.
     *
     * The address is also filed against the client, so the next invoice and their own dashboard
     * already have it and nobody is asked twice.
     */
    public function storeBillingAddress(Request $request, string $token)
    {
        $invoice = ClientInvoice::where('public_token', $token)->firstOrFail();

        if (filled($invoice->bill_to_address)) {
            return response()->json(['error' => 'This invoice already has a billing address.'], 422);
        }

        if ($invoice->status === 'paid' || (float) $invoice->amountDue() <= 0) {
            return response()->json(['error' => 'This invoice is already settled.'], 422);
        }

        $data = $request->validate([
            'label' => ['nullable', 'in:home,office,other'],
            'full_name' => ['nullable', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip' => ['required', 'string', 'max:30'],
            'country' => ['required', 'string', 'max:100'],
        ]);

        // Kept on the client when the invoice belongs to one, so it is theirs from now on.
        $saved = null;

        if ($invoice->client_id) {
            $saved = BillingAddress::create([
                'user_id' => $invoice->client_id,
                'label' => $data['label'] ?? 'other',
                'full_name' => ($data['full_name'] ?? null) ?: $invoice->bill_to_name,
                'company' => ($data['company'] ?? null) ?: $invoice->bill_to_company,
                'phone' => ($data['phone'] ?? null) ?: $invoice->bill_to_phone,
                'address' => $data['address'],
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'zip' => $data['zip'],
                'country' => $data['country'],
            ]);
        }

        // The invoice stores one line, which is what the PDF and the panel show.
        $oneLine = $saved
            ? $saved->oneLine()
            : collect([$data['address'], $data['city'] ?? null, $data['state'] ?? null, $data['zip'], $data['country']])
                ->filter()->join(', ');

        $invoice->forceFill(array_filter([
            'bill_to_address' => $oneLine,
            'bill_to_name' => $invoice->bill_to_name ?: ($data['full_name'] ?? null),
            'bill_to_company' => $invoice->bill_to_company ?: ($data['company'] ?? null),
            'bill_to_phone' => $invoice->bill_to_phone ?: ($data['phone'] ?? null),
        ]))->save();

        $invoice->logActivity('billing_address_added', 'Billing address added from the payment page.', 'client', $invoice->client);

        return response()->json(['address' => $oneLine]);
    }

    public function checkout(string $token)
    {
        $invoice = ClientInvoice::where('public_token', $token)->firstOrFail();
        $amount = $invoice->payableAmount();

        if ($amount <= 0) {
            return redirect()->away($invoice->payUrl());
        }

        if (config('services.stripe.secret')) {
            $stripe = new StripeClient(config('services.stripe.secret'));

            // Send the invoice's billing address to Stripe as a Customer, so the charge and its
            // receipt carry the address we billed — card checks and tax rules rely on it. Without
            // one we let Stripe collect it at checkout rather than charging with no address.
            $customer = $this->stripeCustomer($stripe, $invoice);

            $session = $stripe->checkout->sessions->create(array_filter([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'client_reference_id' => $invoice->invoice_number,
                'metadata' => ['client_invoice_id' => $invoice->id],
                'customer' => $customer?->id,
                'customer_email' => $customer ? null : $invoice->bill_to_email,
                'billing_address_collection' => $customer ? 'auto' : 'required',
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
            ]));

            return redirect()->away($session->url);
        }

        // Local dev fallback (no Stripe keys) — simulate a successful gateway return.
        return redirect()->route('pay.invoice.success', ['token' => $token, 'dev' => 1]);
    }

    /**
     * A Stripe Customer carrying the invoice's billing address, reused across that client's
     * invoices. Returns null when the invoice has no address — the caller then asks Stripe to
     * collect one at checkout. A Stripe error here must not block the payment, so it degrades
     * to the same path.
     */
    private function stripeCustomer(StripeClient $stripe, ClientInvoice $invoice): ?\Stripe\Customer
    {
        if (blank($invoice->bill_to_address)) {
            return null;
        }

        $client = $invoice->client;

        try {
            if ($client?->stripe_customer_id) {
                return $stripe->customers->update($client->stripe_customer_id, [
                    'name' => $invoice->bill_to_name ?: $client->name,
                    'address' => ['line1' => $invoice->bill_to_address],
                ]);
            }

            $customer = $stripe->customers->create(array_filter([
                'name' => $invoice->bill_to_name,
                'email' => $invoice->bill_to_email,
                'phone' => $invoice->bill_to_phone,
                'address' => ['line1' => $invoice->bill_to_address],
            ]));

            $client?->forceFill(['stripe_customer_id' => $customer->id])->save();

            return $customer;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
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
                'note' => $invoice->requested_note ?: 'Paid online',
            ]);
            $invoice->update(['requested_amount' => null, 'requested_note' => null]);
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
                    'brand_name' => config('brand.product'),
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
                'note' => $invoice->requested_note ?: 'Paid online',
            ]);
            $invoice->update(['requested_amount' => null, 'requested_note' => null]);
            $invoice->recomputePaid();
            $invoice->logActivity('payment_added',
                'Client paid '.$invoice->currencySymbol().number_format($charged, 2).' online (PayPal).',
                'client', $invoice->client);
        }

        return redirect()->away($invoice->payUrl().'?paid=1');
    }
}
