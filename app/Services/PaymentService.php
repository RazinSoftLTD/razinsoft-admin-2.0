<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Stripe\StripeClient;

class PaymentService
{
    /**
     * Begin payment for an order and return a descriptor the frontend uses to render the
     * in-page payment UI:
     *   - Stripe (card)  → ['provider' => 'stripe', 'stripe' => ['client_secret', 'publishable_key']]  (Embedded Checkout)
     *   - PayPal         → ['provider' => 'paypal', 'checkout_url' => '...']
     *   - dev fallback   → ['provider' => 'dev', 'checkout_url' => '.../api/dev/pay/...']
     */
    public function initiate(Order $order, string $gateway): array
    {
        if ($gateway === 'stripe' && config('services.stripe.secret')) {
            return ['provider' => 'stripe', 'stripe' => $this->stripeEmbedded($order)];
        }

        if ($gateway === 'paypal' && config('services.paypal.client_id') && config('services.paypal.secret')) {
            return ['provider' => 'paypal', 'checkout_url' => $this->paypal($order)];
        }

        return ['provider' => 'dev', 'checkout_url' => $this->devUrl($order)];
    }

    /** Create a Stripe Embedded Checkout session (card) — mounts inside our page, no redirect/iframe block. */
    private function stripeEmbedded(Order $order): array
    {
        $stripe = new StripeClient(config('services.stripe.secret'));

        $params = [
            'ui_mode' => 'embedded_page', // Stripe renamed 'embedded' → 'embedded_page'
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'client_reference_id' => $order->order_number,
            'metadata' => ['order_id' => $order->id, 'order_number' => $order->order_number],
            // Embedded mode redirects the page to this URL on completion (with the session id).
            'return_url' => $this->frontend("/payment/success?order={$order->order_number}&session_id={CHECKOUT_SESSION_ID}"),
            'line_items' => $order->items->map(fn ($it) => [
                'quantity' => $it->quantity,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => (int) round($it->unit_price * 100),
                    'product_data' => ['name' => trim($it->product_name.' — '.($it->plan_name ?? 'License'))],
                ],
            ])->all(),
        ];

        // Apply the coupon discount so Stripe charges the same total shown on the site.
        if ((float) $order->discount > 0) {
            $coupon = $stripe->coupons->create([
                'amount_off' => (int) round($order->discount * 100),
                'currency' => strtolower($order->currency),
                'duration' => 'once',
                'name' => $order->coupon_code ? "Coupon {$order->coupon_code}" : 'Discount',
            ]);
            $params['discounts'] = [['coupon' => $coupon->id]];
        }

        $session = $stripe->checkout->sessions->create($params);

        $order->payments()->latest()->first()?->update(['gateway_session_id' => $session->id]);

        return [
            'client_secret' => $session->client_secret,
            'publishable_key' => config('services.stripe.key'),
            'session_id' => $session->id,
        ];
    }

    /**
     * Verify a Stripe session is paid (fallback to the webhook so returning to the success
     * page reliably fulfils the order even if webhooks aren't wired in dev).
     */
    public function stripeSessionPaid(Order $order, ?string $sessionId = null): bool
    {
        if (! config('services.stripe.secret')) {
            return false;
        }

        $sessionId ??= $order->payments()->latest()->first()?->gateway_session_id;
        if (! $sessionId) {
            return false;
        }

        try {
            $session = (new StripeClient(config('services.stripe.secret')))->checkout->sessions->retrieve($sessionId);
        } catch (\Throwable) {
            return false;
        }

        return ($session->payment_status ?? null) === 'paid' || ($session->status ?? null) === 'complete';
    }

    private function paypal(Order $order): string
    {
        $token = $this->paypalToken();
        if (! $token) {
            return $this->devUrl($order);
        }

        $res = Http::withToken($token)->post("{$this->paypalBase()}/v2/checkout/orders", [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->order_number,
                'amount' => ['currency_code' => $order->currency, 'value' => number_format((float) $order->total, 2, '.', '')],
            ]],
            'application_context' => [
                'brand_name' => config('app.name'),
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => $this->frontend("/payment/success?order={$order->order_number}"),
                'cancel_url' => $this->frontend("/payment/cancel?order={$order->order_number}"),
            ],
        ])->json();

        // Store PayPal's order id so we can capture it on return / match it from a webhook.
        $order->payments()->latest()->first()?->update(['gateway_session_id' => $res['id'] ?? null]);
        $approve = collect($res['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return $approve ?? $this->devUrl($order);
    }

    /**
     * Capture an approved PayPal order — money is only taken at capture, not at approval.
     * Called from the success page so the order fulfils synchronously without relying on
     * webhooks (mirrors stripeSessionPaid()). Idempotent: an already-captured order is "paid".
     */
    public function paypalCapture(Order $order, ?string $paypalOrderId = null): bool
    {
        if (! config('services.paypal.client_id') || ! config('services.paypal.secret')) {
            return false;
        }

        $paypalOrderId ??= $order->payments()->latest()->first()?->gateway_session_id;
        if (! $paypalOrderId) {
            return false;
        }

        $token = $this->paypalToken();
        if (! $token) {
            return false;
        }

        try {
            $res = Http::withToken($token)
                ->post("{$this->paypalBase()}/v2/checkout/orders/{$paypalOrderId}/capture")
                ->json();
        } catch (\Throwable) {
            return false;
        }

        // A re-confirm after the webhook already captured returns 422 ORDER_ALREADY_CAPTURED.
        if (data_get($res, 'details.0.issue') === 'ORDER_ALREADY_CAPTURED') {
            return true;
        }

        return ($res['status'] ?? null) === 'COMPLETED'
            || data_get($res, 'purchase_units.0.payments.captures.0.status') === 'COMPLETED';
    }

    private function paypalBase(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function paypalToken(): ?string
    {
        return Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.secret'))
            ->post("{$this->paypalBase()}/v1/oauth2/token", ['grant_type' => 'client_credentials'])
            ->json('access_token');
    }

    /** Local-only fallback so the full flow is testable without real gateway keys. */
    private function devUrl(Order $order): string
    {
        return rtrim((string) config('app.url'), '/')."/api/dev/pay/{$order->order_number}";
    }

    private function frontend(string $path): string
    {
        return rtrim((string) config('services.frontend_url'), '/').$path;
    }
}
