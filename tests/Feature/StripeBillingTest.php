<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The billing address has to reach Stripe in the shape Stripe accepts. */
class StripeBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_country_name_becomes_the_iso_code_stripe_wants(): void
    {
        $code = $this->countryCode(...);

        $this->assertSame('BD', $code('Bangladesh'));
        $this->assertSame('US', $code('United States'));
        $this->assertSame('GB', $code('United Kingdom'));

        // An older order may already hold a code.
        $this->assertSame('BD', $code('bd'));

        // Nothing usable is better than something wrong: a bad country fails Radar's checks.
        $this->assertNull($code('Nowhereland'));
        $this->assertNull($code(null));
        $this->assertNull($code(''));
    }

    public function test_an_order_with_no_address_gets_no_stripe_customer(): void
    {
        // With nothing to send, Stripe is asked to collect an address instead — better than
        // pushing a half-filled one that will not match the card.
        $order = new Order(['billing' => ['email' => 'a@b.com']]);

        $this->assertNull($this->stripeCustomerFor($order));
    }

    private function countryCode(?string $name): ?string
    {
        $m = new \ReflectionMethod(PaymentService::class, 'countryCode');

        return $m->invoke(app(PaymentService::class), $name);
    }

    private function stripeCustomerFor(Order $order): mixed
    {
        // No Stripe key in tests, so a real call would throw — the guard must return first.
        $m = new \ReflectionMethod(PaymentService::class, 'stripeCustomer');
        $stripe = new \Stripe\StripeClient('sk_test_dummy');

        return $m->invoke(app(PaymentService::class), $stripe, $order);
    }
}
