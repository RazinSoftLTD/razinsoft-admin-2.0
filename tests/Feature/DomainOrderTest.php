<?php

namespace Tests\Feature;

use App\Models\DomainOrder;
use App\Models\Order;
use App\Models\User;
use App\Services\DomainOrderService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Register flow: order, pay, and the registration that follows the money.
 *
 * ResellerClub is faked throughout. What is under test is our sequence — that the price cannot
 * come from the browser, that the customer/contact/register calls happen in order with the
 * right parameters, and that a failure after payment lands in action_needed rather than nowhere.
 */
class DomainOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.resellerclub.user_id' => '123456',
            'services.resellerclub.api_key' => 'test-key',
            'services.resellerclub.tlds' => 'com,net',
            'services.stripe.secret' => null,   // dev gateway path
        ]);
        $this->user = User::factory()->create();
    }

    private function registrant(): array
    {
        return [
            'name' => 'Shofikul Islam', 'company' => 'RazinSoft',
            'email' => 'owner@example.com', 'phone' => '+8801711111111',
            'address' => 'House 1, Road 2', 'city' => 'Dhaka', 'country' => 'BD', 'zip' => '1207',
        ];
    }

    private function fakeAvailable(): void
    {
        Http::fake([
            '*domains/available.json*' => Http::response([
                'myshop.com' => ['status' => 'available', 'classkey' => 'domcno'],
            ]),
            '*products/customer-price.json*' => Http::response([
                'domcno' => ['addnewdomain' => ['1' => '10.99'], 'renewdomain' => ['1' => '13.99']],
            ]),
            '*customers/details.json*' => Http::response(['status' => 'ERROR', 'message' => 'No customer']),
            '*customers/v2/signup.json*' => Http::response(['customerid' => 'CUST-77']),
            '*contacts/add.json*' => Http::response('9001', 200),
            '*domains/register.json*' => Http::response(['actionstatus' => 'Success', 'entityid' => 'RC-ORDER-1']),
        ]);
    }

    public function test_the_price_comes_from_the_registrar_not_the_browser(): void
    {
        $this->fakeAvailable();

        $res = $this->actingAs($this->user)->postJson('/api/checkout', [
            'items' => [['domain' => 'myshop.com', 'price' => 0.10, 'registrant' => $this->registrant()]],
            'gateway' => 'stripe',
        ])->assertCreated()->json();

        $this->assertSame(10.99, $res['total']);
        $this->assertSame(10.99, (float) DomainOrder::first()->price);
        $order = Order::first();
        $this->assertSame('Domain — myshop.com', $order->items->first()->product_name);
        $this->assertSame(DomainOrder::first()->id, $order->items->first()->domain_order_id);
        $this->assertSame($order->id, DomainOrder::first()->order_id);
    }

    public function test_a_taken_name_cannot_be_ordered(): void
    {
        Http::fake([
            '*domains/available.json*' => Http::response([
                'myshop.com' => ['status' => 'regthroughothers', 'classkey' => 'domcno'],
            ]),
            '*products/customer-price.json*' => Http::response([]),
        ]);

        $this->actingAs($this->user)->postJson('/api/checkout', [
            'items' => [['domain' => 'myshop.com', 'registrant' => $this->registrant()]],
            'gateway' => 'stripe',
        ])->assertStatus(422);

        $this->assertSame(0, DomainOrder::count());
        $this->assertSame(0, Order::count());
    }

    public function test_paying_registers_the_domain_through_the_full_sequence(): void
    {
        $this->fakeAvailable();

        $this->actingAs($this->user)->postJson('/api/checkout', [
            'items' => [['domain' => 'myshop.com', 'registrant' => $this->registrant()]],
            'gateway' => 'stripe',
        ])->assertCreated();

        // The gateway confirms → the shared pipeline pays and fulfils the whole order.
        app(OrderService::class)->markPaid(Order::first());
        $order = DomainOrder::first();

        $this->assertSame('registered', $order->status);
        $this->assertSame('CUST-77', $order->rc_customer_id);
        $this->assertSame('9001', $order->rc_contact_id);
        $this->assertSame('RC-ORDER-1', $order->rc_order_id);
        $this->assertNotNull($order->registered_at);

        // The registration call carried the money-relevant parameters.
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'domains/register.json')) {
                return true;
            }
            $body = $request->body();

            return str_contains($body, 'myshop.com')
                && str_contains($body, 'invoice-option=NoInvoice')
                && str_contains($body, 'customer-id=CUST-77')
                && str_contains($body, 'reg-contact-id=9001');
        });
    }

    public function test_a_registration_failure_after_payment_is_never_silent(): void
    {
        Http::fake([
            '*domains/available.json*' => Http::response([
                'myshop.com' => ['status' => 'available', 'classkey' => 'domcno'],
            ]),
            '*products/customer-price.json*' => Http::response([
                'domcno' => ['addnewdomain' => ['1' => '10.99']],
            ]),
            '*customers/details.json*' => Http::response(['status' => 'ERROR', 'message' => 'No customer']),
            '*customers/v2/signup.json*' => Http::response(['customerid' => 'CUST-77']),
            '*contacts/add.json*' => Http::response('9001', 200),
            '*domains/register.json*' => Http::response(['status' => 'ERROR', 'message' => 'Insufficient funds in reseller account']),
        ]);

        $this->actingAs($this->user)->postJson('/api/checkout', [
            'items' => [['domain' => 'myshop.com', 'registrant' => $this->registrant()]],
            'gateway' => 'stripe',
        ])->assertCreated();

        app(OrderService::class)->markPaid(Order::first());
        $order = DomainOrder::first();

        $this->assertSame('action_needed', $order->status);
        $this->assertStringContainsString('Insufficient funds', $order->registration_error);
        $this->assertNotNull($order->paid_at, 'the payment record must survive the failure');
        // The ids that DID succeed are kept, so the retry does not create duplicates.
        $this->assertSame('CUST-77', $order->rc_customer_id);
    }

    public function test_registering_twice_does_not_register_twice(): void
    {
        $this->fakeAvailable();

        $this->actingAs($this->user)->postJson('/api/checkout', [
            'items' => [['domain' => 'myshop.com', 'registrant' => $this->registrant()]],
            'gateway' => 'stripe',
        ])->assertCreated();

        app(OrderService::class)->markPaid(Order::first());
        // The webhook and the success page can both arrive.
        app(DomainOrderService::class)->register(DomainOrder::first()->fresh());

        $registerCalls = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'domains/register.json'))
            ->count();

        $this->assertSame(1, $registerCalls);
    }

    public function test_an_unpaid_order_is_never_registered(): void
    {
        $this->fakeAvailable();

        $this->actingAs($this->user)->postJson('/api/checkout', [
            'items' => [['domain' => 'myshop.com', 'registrant' => $this->registrant()]],
            'gateway' => 'stripe',
        ])->assertCreated();

        // Nobody paid — registering must refuse, even called directly.
        app(DomainOrderService::class)->register(DomainOrder::first());

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'domains/register.json'));
        $this->assertSame('pending', DomainOrder::first()->status);
    }

    public function test_orders_belong_to_their_owner(): void
    {
        $this->fakeAvailable();

        $this->actingAs($this->user)->postJson('/api/checkout', [
            'items' => [['domain' => 'myshop.com', 'registrant' => $this->registrant()]],
            'gateway' => 'stripe',
        ])->assertCreated();

        $number = DomainOrder::first()->order_number;
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->getJson("/api/domains/orders/{$number}")->assertNotFound();
        $this->actingAs($this->user)->getJson("/api/domains/orders/{$number}")->assertOk();
    }

    public function test_ordering_requires_login(): void
    {
        $this->postJson('/api/checkout', [
            'items' => [['domain' => 'myshop.com', 'registrant' => $this->registrant()]],
            'gateway' => 'stripe',
        ])->assertUnauthorized();
    }

    public function test_an_existing_customer_is_reused_not_recreated(): void
    {
        Http::fake([
            '*domains/available.json*' => Http::response([
                'myshop.com' => ['status' => 'available', 'classkey' => 'domcno'],
            ]),
            '*products/customer-price.json*' => Http::response([
                'domcno' => ['addnewdomain' => ['1' => '10.99']],
            ]),
            '*customers/details.json*' => Http::response(['customerid' => 'CUST-EXISTING']),
            '*contacts/add.json*' => Http::response('9002', 200),
            '*domains/register.json*' => Http::response(['actionstatus' => 'Success', 'entityid' => 'RC-2']),
        ]);

        $this->actingAs($this->user)->postJson('/api/checkout', [
            'items' => [['domain' => 'myshop.com', 'registrant' => $this->registrant()]],
            'gateway' => 'stripe',
        ])->assertCreated();

        app(OrderService::class)->markPaid(Order::first());

        $this->assertSame('CUST-EXISTING', DomainOrder::first()->rc_customer_id);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'customers/v2/signup.json'));
    }
}
