<?php

namespace App\Services;

use App\Models\DomainOrder;
use App\Models\User;

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
    public function create(User $user, string $domain, array $registrant, ?int $orderId = null): DomainOrder
    {
        [$label, $tld] = [strtok($domain, '.'), (string) strtok('')];

        $offer = collect($this->rc->search($label, [$tld]))->firstWhere('domain', $domain);

        if (! $offer || ! $offer['available']) {
            throw new \RuntimeException("{$domain} is not available to register.");
        }
        if ($offer['price'] === null) {
            throw new \RuntimeException("{$domain} has no price yet — ask us and we will quote it.");
        }

        return DomainOrder::create([
            'order_number' => $this->newOrderNumber(),
            'user_id' => $user->id,
            'order_id' => $orderId,
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
     * The ResellerClub side: customer, contact, then the registration itself.
     *
     * A failure here is the one outcome that must never be silent — the money has moved. The
     * order lands in action_needed with the reason stored, the exception is reported, and the
     * buyer sees "we are completing it", which is true: the admin finishes it by hand.
     */
    public function register(DomainOrder $order): DomainOrder
    {
        // Paid either directly (the early standalone flow) or through the cart order that
        // carries it. Unpaid is never registered; registered is never repeated.
        $paid = $order->isPaid() || $order->order?->isPaid();

        if ($order->status === 'registered' || ! $paid) {
            return $order;
        }

        if (! $order->paid_at && $order->order?->paid_at) {
            $order->update(['status' => 'paid', 'paid_at' => $order->order->paid_at]);
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
