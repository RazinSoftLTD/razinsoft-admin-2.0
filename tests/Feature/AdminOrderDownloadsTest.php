<?php
namespace Tests\Feature;
use App\Models\Plan; use App\Models\Product; use App\Models\ProductFile; use App\Models\User;
use App\Services\OrderService; use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash; use Illuminate\Support\Facades\Storage; use Tests\TestCase;

class AdminOrderDownloadsTest extends TestCase {
    use RefreshDatabase;

    private function paidOrder() {
        $p = Product::create(['name'=>'Ready X','slug'=>'ready-x','tagline'=>'X','status'=>'published','price'=>49,'ext_price'=>249,'version'=>'1.0','category'=>'eCommerce']);
        $plan = Plan::create(['product_id'=>$p->id,'name'=>'Extended','price'=>599,'perks'=>['Updates']]);
        ProductFile::create(['product_id'=>$p->id,'version'=>'1.0','file_path'=>'sources/ready-x-1.0.zip','is_latest'=>true]);
        $buyer = User::create(['name'=>'Cust','email'=>'cust@test.local','password'=>Hash::make('x'),'role'=>'customer']);
        $order = app(OrderService::class)->createFromCheckout($buyer, [
            'items'=>[['slug'=>'ready-x','plan_id'=>$plan->id,'qty'=>1]], 'gateway'=>'stripe',
            'billing'=>['first_name'=>'Cust','email'=>'cust@test.local'],
        ]);
        app(OrderService::class)->markPaid($order); // fulfils: generates invoice + license PDFs
        return $order->fresh(['invoice','items.license']);
    }

    public function test_admin_can_download_order_invoice_and_license(): void {
        Storage::fake('local');
        $admin = User::create(['name'=>'A','email'=>'od-admin@test.local','password'=>'password','role'=>'admin']);
        $order = $this->paidOrder();
        $this->actingAs($admin);

        // invoice PDF
        $this->get(route('admin.orders.invoice.download', $order))
            ->assertOk()->assertHeader('content-disposition', 'attachment; filename='.$order->invoice->invoice_number.'.pdf');

        // license certificate
        $license = $order->items->first()->license;
        $this->assertNotNull($license->file_path);
        $this->get(route('admin.orders.license.download', [$order, $license]))->assertOk();

        // buttons render on the order page
        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Download Invoice PDF')
            ->assertSee(route('admin.orders.invoice.download', $order), false);
    }

    public function test_staff_without_orders_permission_cannot_download(): void {
        $staff = User::create(['name'=>'S','email'=>'od-staff@test.local','password'=>'password','role'=>'staff','permissions'=>['leads']]);
        $order = $this->paidOrder();
        $this->actingAs($staff);

        $this->get(route('admin.orders.invoice.download', $order))->assertForbidden();
    }
}
