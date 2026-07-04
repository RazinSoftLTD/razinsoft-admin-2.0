<?php
namespace Tests\Feature;
use App\Models\ClientInvoice; use App\Models\User; use Illuminate\Support\Str; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class C6FlowTest extends TestCase {
    use RefreshDatabase;
    public function test_c6_flow(): void {
        $admin = User::firstOrCreate(['email'=>'c6admin@test.local'],['name'=>'A','password'=>'password','role'=>'admin']);
        $client = User::firstOrCreate(['email'=>'c6client@test.local'],['name'=>'C6 Client','password'=>'password','role'=>'customer']);
        $this->actingAs($admin);
        $inv = ClientInvoice::create(['invoice_number'=>'INV-C6-'.rand(1000,9999),'public_token'=>Str::random(40),'client_id'=>$client->id,'bill_to_name'=>$client->name,'bill_to_email'=>$client->email,'invoice_date'=>date('Y-m-d'),'due_date'=>date('Y-m-d',strtotime('+30 days')),'currency'=>'USD','status'=>'sent','subtotal'=>900,'total'=>900,'created_by'=>$admin->id]);
        $inv->items()->create(['description'=>'Project','qty'=>1,'unit_price'=>900,'amount'=>900]);

        // 1. split into 3
        $this->post("/admin/invoices/{$inv->id}/installments",['parts'=>3,'interval_days'=>30])->assertRedirect();
        $inv->refresh();
        $this->assertEquals(3, $inv->installments->count());
        $this->assertEquals(900.0, (float)$inv->installments->sum('amount'), 'installments sum to total');

        // 2. payment request 300
        $this->post("/admin/invoices/{$inv->id}/request-payment",['requested_amount'=>300])->assertRedirect();
        $inv->refresh();
        $this->assertEquals(300.0, $inv->payableAmount(), 'payable = requested');

        // 3. public pay page shows requested
        $this->get("/invoice/pay/{$inv->public_token}")->assertOk()->assertSee('Requested payment')->assertSee('300.00');

        // 4. dev checkout + success records payment
        $this->post("/invoice/pay/{$inv->public_token}/checkout")->assertRedirect();
        $this->get("/invoice/pay/{$inv->public_token}/success?dev=1")->assertOk();
        $inv->refresh();
        $this->assertEquals(300.0, (float)$inv->amount_paid, 'payment recorded');
        $this->assertEquals(600.0, $inv->amountDue(), 'due updated');
        $this->assertEquals('partially_paid', $inv->status);
        $this->assertNull($inv->requested_amount, 'request cleared after pay');

        // 5. save & send (mail log)
        config(['mail.default'=>'log']);
        $this->post("/admin/invoices/{$inv->id}/send")->assertRedirect();

        // admin show renders new cards
        $this->get("/admin/invoices/{$inv->id}")->assertOk()->assertSee('Installment Plan')->assertSee('Client Pay Link');

        $inv->delete();
        $this->assertTrue(true);
    }
}
