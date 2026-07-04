<?php
namespace Tests\Feature;
use App\Models\ClientInvoice; use App\Models\Plan; use App\Models\Product; use App\Models\User;
use App\Services\FulfillmentService; use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Support\Facades\Storage; use Tests\TestCase;

class SharedInvoiceSerialTest extends TestCase {
    use RefreshDatabase;

    public function test_reloading_create_page_does_not_consume_a_number(): void {
        $yy = date('y');
        $admin = User::create(['name'=>'A','email'=>'ser-admin@test.local','password'=>'password','role'=>'admin']);
        $this->actingAs($admin);

        // reloading the create page shows the same preview every time and burns nothing
        $this->get('/admin/invoices/create')->assertOk()->assertSee("RS-{$yy}00001");
        $this->get('/admin/invoices/create')->assertOk()->assertSee("RS-{$yy}00001");
        $this->get('/admin/invoices/create')->assertOk()->assertSee("RS-{$yy}00001");

        // only saving actually allocates 00001; the next preview then moves to 00002
        $this->post('/admin/invoices', [
            'client_id'=>'','invoice_date'=>date('Y-m-d'),'currency'=>'USD','status'=>'draft',
            'bill_to_name'=>'X','items'=>[['description'=>'A','qty'=>1,'unit_price'=>10]],
        ])->assertRedirect();
        $this->assertSame("RS-{$yy}00001", ClientInvoice::latest('id')->first()->invoice_number);
        $this->get('/admin/invoices/create')->assertOk()->assertSee("RS-{$yy}00002");
    }

    public function test_next_number_is_a_single_incrementing_serial(): void {
        $yy = date('y');
        $this->assertSame("RS-{$yy}00001", ClientInvoice::nextNumber());
        $this->assertSame("RS-{$yy}00002", ClientInvoice::nextNumber());
    }

    public function test_web_order_and_crm_invoices_share_one_serial(): void {
        Storage::fake('local');
        $yy = date('y');

        // 1) an admin CRM invoice takes 00001 (RS- format, same as orders)
        $crm = ClientInvoice::create(['invoice_number'=>ClientInvoice::nextNumber(),'bill_to_name'=>'X','invoice_date'=>date('Y-m-d'),'currency'=>'USD','status'=>'draft','subtotal'=>10,'total'=>10]);
        $this->assertSame("RS-{$yy}00001", $crm->invoice_number);

        // 2) a web order draws the NEXT number → 00002, and its invoice reuses the order number
        $product = Product::create(['name'=>'Ready X','slug'=>'ready-x','tagline'=>'X','status'=>'published','price'=>200,'ext_price'=>400,'version'=>'1.0','category'=>'eCommerce']);
        $plan = Plan::create(['product_id'=>$product->id,'name'=>'Annual','price'=>200]);
        $user = User::create(['name'=>'Buyer','email'=>'buyer@test.local','password'=>'password','role'=>'customer']);
        $order = app(OrderService::class)->createFromCheckout($user, [
            'items'=>[['slug'=>'ready-x','plan_id'=>$plan->id,'qty'=>1]], 'gateway'=>'stripe',
            'billing'=>['first_name'=>'Buyer','email'=>'buyer@test.local'],
        ]);
        $this->assertSame("RS-{$yy}00002", $order->order_number);

        $invoice = app(FulfillmentService::class)->generateInvoice($order->fresh());
        $this->assertSame($order->order_number, $invoice->invoice_number);

        // 3) next CRM invoice continues at 00003 — proving orders + invoices share one serial
        $this->assertSame("RS-{$yy}00003", ClientInvoice::nextNumber());

        // the order invoice PDF was rendered with the shared layout
        Storage::disk('local')->assertExists("invoices/{$invoice->invoice_number}.pdf");
    }
}
