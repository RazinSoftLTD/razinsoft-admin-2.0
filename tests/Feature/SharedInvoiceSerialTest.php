<?php
namespace Tests\Feature;
use App\Models\ClientInvoice; use App\Models\Plan; use App\Models\Product; use App\Models\User;
use App\Services\FulfillmentService; use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Support\Facades\Storage; use Tests\TestCase;

class SharedInvoiceSerialTest extends TestCase {
    use RefreshDatabase;

    public function test_next_number_is_a_single_incrementing_serial(): void {
        $a = ClientInvoice::nextNumber();
        $b = ClientInvoice::nextNumber();
        $year = date('Y');
        $this->assertSame("INV-{$year}-0001", $a);
        $this->assertSame("INV-{$year}-0002", $b);
    }

    public function test_web_order_and_crm_invoices_share_one_serial(): void {
        Storage::fake('local');
        $year = date('Y');

        // 1) an admin CRM invoice takes 0001
        $crm = ClientInvoice::create(['invoice_number'=>ClientInvoice::nextNumber(),'bill_to_name'=>'X','invoice_date'=>date('Y-m-d'),'currency'=>'USD','status'=>'draft','subtotal'=>10,'total'=>10]);
        $this->assertSame("INV-{$year}-0001", $crm->invoice_number);

        // 2) a web order invoice takes the NEXT number in the same sequence → 0002
        $product = Product::create(['name'=>'Ready X','slug'=>'ready-x','tagline'=>'X','status'=>'published','price'=>200,'ext_price'=>400,'version'=>'1.0','category'=>'eCommerce']);
        $plan = Plan::create(['product_id'=>$product->id,'name'=>'Annual','price'=>200]);
        $user = User::create(['name'=>'Buyer','email'=>'buyer@test.local','password'=>'password','role'=>'customer']);
        $order = app(OrderService::class)->createFromCheckout($user, [
            'items'=>[['slug'=>'ready-x','plan_id'=>$plan->id,'qty'=>1]], 'gateway'=>'stripe',
            'billing'=>['first_name'=>'Buyer','email'=>'buyer@test.local'],
        ]);

        $invoice = app(FulfillmentService::class)->generateInvoice($order->fresh());
        $this->assertSame("INV-{$year}-0002", $invoice->invoice_number);

        // 3) next CRM invoice continues at 0003 — proving the counter is shared
        $this->assertSame("INV-{$year}-0003", ClientInvoice::nextNumber());

        // the order invoice PDF was rendered with the shared layout
        Storage::disk('local')->assertExists("invoices/{$invoice->invoice_number}.pdf");
    }
}
