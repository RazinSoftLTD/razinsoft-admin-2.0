<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private function seedOrder(User $user): Order
    {
        $this->product = Product::create([
            'name' => 'Ready X', 'slug' => 'ready-x', 'tagline' => 'X', 'status' => 'published',
            'price' => 49, 'ext_price' => 249, 'version' => '1.0', 'category' => 'eCommerce',
        ]);
        $plan = Plan::create(['product_id' => $this->product->id, 'name' => 'Extended', 'price' => 599, 'perks' => ['Unlimited domains']]);
        ProductFile::create(['product_id' => $this->product->id, 'version' => '1.0', 'file_path' => 'sources/ready-x-1.0.zip', 'is_latest' => true]);
        Storage::disk('local')->put('sources/ready-x-1.0.zip', 'ZIPDATA');

        $order = app(OrderService::class)->createFromCheckout($user, [
            'items' => [['slug' => 'ready-x', 'plan_id' => $plan->id, 'qty' => 1]], 'gateway' => 'stripe',
            'billing' => ['first_name' => 'Cust', 'email' => $user->email],
        ]);
        app(OrderService::class)->markPaid($order);

        return $order->fresh();
    }

    private function customer(string $email = 'cust@test.com'): User
    {
        return User::create(['name' => 'Cust', 'email' => $email, 'password' => Hash::make('x'), 'role' => 'customer']);
    }

    public function test_dashboard_returns_stats_and_licenses(): void
    {
        Storage::fake('local');
        Mail::fake();
        $user = $this->customer();
        $this->seedOrder($user);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/account/dashboard')->assertOk();
        $res->assertJsonPath('stats.total_orders', 1)
            ->assertJsonPath('stats.completed_orders', 1)
            ->assertJsonPath('stats.total_spent', 599)
            ->assertJsonPath('stats.active_licenses', 1)
            ->assertJsonPath('stats.products_owned', 1);
        $this->assertNotNull($res->json('licenses.0.license_key'));
        $this->assertNotNull($res->json('licenses.0.source_download_url'));
    }

    public function test_orders_and_detail_with_downloads(): void
    {
        Storage::fake('local');
        Mail::fake();
        $user = $this->customer();
        $order = $this->seedOrder($user);
        Sanctum::actingAs($user);

        $this->getJson('/api/account/orders')->assertOk()->assertJsonPath('meta.total', 1);

        $res = $this->getJson("/api/account/orders/{$order->order_number}")->assertOk();
        $res->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.items.0.plan_name', 'Extended');
        $this->assertNotNull($res->json('data.invoice.download_url'));
        $this->assertNotNull($res->json('data.items.0.license.download_url'));
        $this->assertNotNull($res->json('data.items.0.source_download_url'));
    }

    public function test_invoice_and_license_download_stream_files(): void
    {
        Storage::fake('local');
        Mail::fake();
        $user = $this->customer();
        $order = $this->seedOrder($user);
        Sanctum::actingAs($user);

        $detail = $this->getJson("/api/account/orders/{$order->order_number}")->json('data');
        $this->get($detail['invoice']['download_url'])->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename='.$order->invoice->invoice_number.'.pdf');
        $this->get($detail['items'][0]['license']['download_url'])->assertOk();
    }

    public function test_gated_source_download_allows_owner_blocks_others(): void
    {
        Storage::fake('local');
        Mail::fake();
        $owner = $this->customer('owner@test.com');
        $order = $this->seedOrder($owner);
        Sanctum::actingAs($owner);

        $url = $this->getJson("/api/account/orders/{$order->order_number}")->json('data.items.0.source_download_url');
        $this->get($url)->assertOk(); // owner with valid signature

        // non-owner hitting the same signed URL is rejected by ownership check
        $stranger = $this->customer('stranger@test.com');
        Sanctum::actingAs($stranger);
        $this->get($url)->assertForbidden();
    }

    public function test_account_requires_auth(): void
    {
        $this->getJson('/api/account/dashboard')->assertUnauthorized();
    }
}
