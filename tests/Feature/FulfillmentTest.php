<?php

namespace Tests\Feature;

use App\Mail\OrderFulfilledMail;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use App\Services\FulfillmentService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $p = Product::create([
            'name' => 'Ready X', 'slug' => 'ready-x', 'tagline' => 'X', 'status' => 'published',
            'price' => 49, 'ext_price' => 249, 'version' => '1.0', 'category' => 'eCommerce',
        ]);
        $plan = Plan::create(['product_id' => $p->id, 'name' => 'Extended', 'price' => 599, 'perks' => ['Unlimited domains', '1 year updates', 'Priority support']]);
        ProductFile::create(['product_id' => $p->id, 'version' => '1.0', 'file_path' => 'sources/ready-x-1.0.zip', 'is_latest' => true]);
        $user = User::create(['name' => 'Cust', 'email' => 'cust@test.com', 'password' => Hash::make('x'), 'role' => 'customer']);

        return app(OrderService::class)->createFromCheckout($user, [
            'items' => [['slug' => 'ready-x', 'plan_id' => $plan->id, 'qty' => 1]], 'gateway' => 'stripe',
            'billing' => ['first_name' => 'Cust', 'email' => 'cust@test.com'],
        ]);
    }

    public function test_paid_order_generates_invoice_and_license(): void
    {
        Storage::fake('local');
        Mail::fake();
        $order = $this->order();

        app(OrderService::class)->markPaid($order);

        // Invoice
        $this->assertDatabaseHas('invoices', ['order_id' => $order->id]);
        $invoice = $order->fresh()->invoice;
        $this->assertNotNull($invoice->pdf_path);
        Storage::disk('local')->assertExists($invoice->pdf_path);
        $this->assertMatchesRegularExpression('/^RS-\d{2}\d{5}$/', $invoice->invoice_number);

        // License per item
        $item = $order->items()->first();
        $this->assertDatabaseHas('licenses', [
            'order_item_id' => $item->id, 'product_id' => $item->product_id,
            'user_id' => $order->user_id, 'plan_name' => 'Extended', 'status' => 'active',
        ]);
        $license = $item->license;
        $this->assertStringStartsWith('RZN-', $license->license_key);
        $this->assertStringEndsWith('.pdf', $license->file_path); // license is now a PDF certificate
        Storage::disk('local')->assertExists($license->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($license->file_path));
        $this->assertStringContainsString('Unlimited domains', $license->terms);

        $this->assertEquals('completed', $order->fresh()->status);
        Mail::assertQueued(OrderFulfilledMail::class);
    }

    public function test_fulfillment_is_idempotent(): void
    {
        Storage::fake('local');
        Mail::fake();
        $svc = app(OrderService::class);
        $order = $this->order();

        $svc->markPaid($order);
        $svc->markPaid($order->fresh());            // re-run
        app(FulfillmentService::class)->fulfill($order->fresh()); // direct re-run

        $this->assertEquals(1, $order->invoice()->count());
        $this->assertEquals(1, $order->items()->first()->license()->count());
    }
}
