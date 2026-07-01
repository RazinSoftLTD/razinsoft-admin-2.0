<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $p = Product::create([
            'name' => 'Ready X', 'slug' => 'ready-x', 'tagline' => 'X', 'status' => 'published',
            'price' => 49, 'ext_price' => 249, 'version' => '1.0', 'category' => 'eCommerce',
        ]);
        Plan::create(['product_id' => $p->id, 'name' => 'Standard', 'price' => 599, 'is_popular' => true, 'perks' => ['a']]);

        return $p;
    }

    private function customer(): User
    {
        return User::create(['name' => 'Cust', 'email' => 'cust@test.com', 'password' => Hash::make('x'), 'role' => 'customer']);
    }

    public function test_customer_can_checkout_a_plan(): void
    {
        $p = $this->product();
        $plan = $p->plans()->first();
        Sanctum::actingAs($this->customer());

        $res = $this->postJson('/api/checkout', [
            'items' => [['slug' => 'ready-x', 'plan_id' => $plan->id, 'qty' => 1]],
            'gateway' => 'stripe',
            'billing' => ['email' => 'cust@test.com', 'first_name' => 'Cust'],
        ]);

        $res->assertStatus(201)->assertJsonPath('total', 599); // JSON drops .0 on whole numbers
        $this->assertStringContainsString('/api/dev/pay/', $res->json('checkout_url')); // no keys → dev fallback

        $this->assertDatabaseHas('orders', ['order_number' => $res->json('order_number'), 'status' => 'pending', 'total' => 599]);
        $this->assertDatabaseHas('order_items', ['plan_name' => 'Standard', 'unit_price' => 599, 'line_total' => 599]);
        $this->assertDatabaseHas('payments', ['gateway' => 'stripe', 'status' => 'pending', 'amount' => 599]);
    }

    public function test_coupon_applies_discount(): void
    {
        $p = $this->product();
        $plan = $p->plans()->first();
        Coupon::create(['code' => 'RAZIN10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
        Sanctum::actingAs($this->customer());

        $res = $this->postJson('/api/checkout', [
            'items' => [['slug' => 'ready-x', 'plan_id' => $plan->id, 'qty' => 1]],
            'coupon' => 'razin10',
            'gateway' => 'paypal',
        ]);

        $res->assertStatus(201)->assertJsonPath('total', 539.1); // 599 - 10%
    }

    public function test_webhook_marks_order_paid_and_fulfils(): void
    {
        $p = $this->product();
        $plan = $p->plans()->first();
        $customer = $this->customer();
        $order = app(OrderService::class)->createFromCheckout($customer, [
            'items' => [['slug' => 'ready-x', 'plan_id' => $plan->id, 'qty' => 1]], 'gateway' => 'stripe',
        ]);

        // Simulated Stripe event (no signing secret in test → JSON parsed)
        $this->postJson('/api/webhooks/stripe', [
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['client_reference_id' => $order->order_number, 'payment_intent' => 'pi_123']],
        ])->assertOk();

        $this->assertContains($order->fresh()->status, ['paid', 'completed']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'succeeded']);
    }

    public function test_mark_paid_is_idempotent(): void
    {
        $p = $this->product();
        $plan = $p->plans()->first();
        $svc = app(OrderService::class);
        $order = $svc->createFromCheckout($this->customer(), [
            'items' => [['slug' => 'ready-x', 'plan_id' => $plan->id, 'qty' => 1]], 'gateway' => 'stripe',
        ]);

        $svc->markPaid($order);
        $svc->markPaid($order->fresh()); // second time = no-op

        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertEquals(1, $order->payments()->where('status', 'succeeded')->count());
    }

    public function test_checkout_requires_auth(): void
    {
        $this->postJson('/api/checkout', ['items' => [], 'gateway' => 'stripe'])->assertUnauthorized();
    }

    public function test_extended_license_uses_ext_price(): void
    {
        $this->product(); // price 49, ext_price 249
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/checkout', [
            'items' => [['slug' => 'ready-x', 'qty' => 1, 'license_type' => 'extended']],
            'gateway' => 'stripe',
        ])->assertStatus(201)->assertJsonPath('total', 249);
    }

    public function test_repay_returns_url_for_pending_and_blocks_paid(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->product();
        $cust = $this->customer();
        $order = app(OrderService::class)->createFromCheckout($cust, [
            'items' => [['slug' => 'ready-x', 'qty' => 1]], 'gateway' => 'stripe',
        ]);
        Sanctum::actingAs($cust);

        $this->postJson("/api/orders/{$order->order_number}/repay")
            ->assertOk()->assertJsonPath('order_number', $order->order_number);

        app(OrderService::class)->markPaid($order);
        $this->postJson("/api/orders/{$order->order_number}/repay")->assertStatus(409);
    }
}
