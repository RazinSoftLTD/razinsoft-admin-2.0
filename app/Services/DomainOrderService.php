<?php

namespace App\Services;

use App\Models\DomainOrder;
use App\Models\User;
use Stripe\StripeClient;

/**
 * A domain purchase, from Register click to a name registered at ResellerClub.
 *
 * Deliberately its own small flow rather than a product order: the product pipeline fulfils by
 * issuing licences and downloads, carries coupons and plans, and none of that means anything for
 * a domain. What is shared is the pattern — pay first, fulfil after, and never lose the case
 * where fulfilment fails after the money moved.
 */
class DomainOrderService
{
    public function __construct(private ResellerClub $rc) {}

    /**
     * Create the order for a name the buyer just picked.
     *
     * The price is looked up again here and never taken from the browser: between search and
     * checkout the page's number is an old screenshot, and a tampered one is a $0.10 domain.
     */
    public function create(User $user, string $domain, array $registrant): DomainOrder
    {
        [$label, $tld] = [strtok($domain, '.'), (string) strtok('')];

        $offer = collect($this->rc->search($label, [$tld]))->firstWhere('domain', $domain);

        if (! $offer || ! $offer['available']) {
            throw new \RuntimeException('That name is not available to register.');
        }
        if ($offer['price'] === null) {
            throw new \RuntimeException('That extension has no price yet — ask us and we will quote it.');
        }

        return DomainOrder::create([
            'order_number' => $this->newOrderNumber(),
            'user_id' => $user->id,
            'domain' => $domain,
            'years' => 1,
            'price' => $offer['price'],
            'renew_price' => $offer['renew'],
            'currency' => config('services.resellerclub.currency', 'USD'),
            'status' => 'pending',
            'registrant_name' => $registrant['name'],
            'registrant_company' => $registrant['company'] ?? null,
            'registrant_email' => $registrant['email'],
            'registrant_phone' => $registrant['phone'],
            'registrant_address' => $registrant['address'],
            'registrant_city' => $registrant['city'],
            'registrant_country' => strtoupper($registrant['country']),
            'registrant_zip' => $registrant['zip'],
        ]);
    }

    /**
     * Begin payment. Stripe's hosted page when keys exist, the local dev simulator otherwise —
     * the same split the product checkout makes.
     */
    public function initiatePayment(DomainOrder $order): array
    {
        if (config('services.stripe.secret')) {
            $stripe = new StripeClient(config('services.stripe.secret'));

            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'client_reference_id' => $order->order_number,
                'metadata' => ['domain_order' => $order->order_number],
                'customer_email' => $order->registrant_email,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($order->currency),
                        'unit_amount' => (int) round($order->price * 100),
                        'product_data' => ['name' => "Domain registration — {$order->domain} (1 year)"],
                    ],
                ]],
                'success_url' => $this->frontend("/cloud/domains?order={$order->order_number}&session_id={CHECKOUT_SESSION_ID}"),
                'cancel_url' => $this->frontend('/cloud/domains?cancelled=1'),
            ]);

            $order->update(['payment_gateway' => 'stripe', 'gateway_session_id' => $session->id]);

            return ['provider' => 'stripe', 'checkout_url' => $session->url];
        }

        $order->update(['payment_gateway' => 'dev']);

        return [
            'provider' => 'dev',
            'checkout_url' => url("/api/dev/pay-domain/{$order->order_number}"),
        ];
    }

    /**
     * Confirm payment on return from the gateway, then register.
     *
     * Idempotent on purpose: the success page can be reloaded, and the webhook and the return
     * can both arrive. Money is only ever counted once, and a paid order is never re-registered.
     */
    public function confirm(DomainOrder $order, ?string $sessionId): DomainOrder
    {
        if (! $order->isPaid()) {
            if ($order->payment_gateway === 'stripe') {
                if (! $sessionId || $sessionId !== $order->gateway_session_id) {
                    return $order;
                }
                $session = (new StripeClient(config('services.stripe.secret')))
                    ->checkout->sessions->retrieve($sessionId);
                if (($session->payment_status ?? null) !== 'paid') {
                    return $order;
                }
            }
            // dev gateway confirms by being called at all — local only, guarded at the route.

            $order->update(['status' => 'paid', 'paid_at' => now()]);
        }

        return $this->register($order->fresh());
    }

    /**
     * The ResellerClub side: customer, contact, then the registration itself.
     *
     * A failure here is the one outcome that must never be silent — the money has moved. The
     * order lands in action_needed with the reason stored, the exception is reported, and the
     * buyer sees "we are completing it", which is true: the admin finishes it by hand.
     */
    public function register(DomainOrder $order): DomainOrder
    {
        if ($order->status === 'registered' || ! $order->isPaid()) {
            return $order;
        }

        $address = [
            'company' => $order->registrant_company,
            'address' => $order->registrant_address,
            'city' => $order->registrant_city,
            'country' => $order->registrant_country,
            'zip' => $order->registrant_zip,
            'phone' => $order->registrant_phone,
        ];

        try {
            $customerId = $order->rc_customer_id
                ?: $this->rc->ensureCustomer($order->registrant_email, $order->registrant_name, $address);
            $order->update(['rc_customer_id' => $customerId]);

            $contactId = $order->rc_contact_id
                ?: $this->rc->createContact($customerId, $order->registrant_email, $order->registrant_name, $address);
            $order->update(['rc_contact_id' => $contactId]);

            $rcOrderId = $this->rc->registerDomain($order->domain, (int) $order->years, $customerId, $contactId);

            $order->update([
                'status' => 'registered',
                'rc_order_id' => $rcOrderId,
                'registered_at' => now(),
                'registration_error' => null,
            ]);
        } catch (\Throwable $e) {
            $order->update(['status' => 'action_needed', 'registration_error' => $e->getMessage()]);
            report($e);
        }

        return $order->fresh();
    }

    /** DOM-{2-digit year}{5-digit serial} — its own sequence, distinct from product orders. */
    private function newOrderNumber(): string
    {
        $serial = (int) (DomainOrder::whereYear('created_at', now()->year)->max('id') ?? 0) + 1;

        return sprintf('DOM-%s%05d', now()->format('y'), $serial);
    }

    private function frontend(string $path): string
    {
        return rtrim((string) config('services.frontend_url'), '/').$path;   // same key PaymentService uses
    }
}
