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

        // 1. amount-to-receive CANNOT exceed the due (validation)
        $this->post("/admin/invoices/{$inv->id}/request-payment",['requested_amount'=>1500])
            ->assertSessionHasErrors('requested_amount');
        $this->assertNull($inv->fresh()->requested_amount, 'over-due amount rejected');

        // 2. set amount to receive = 300 (<= due 900)
        $this->post("/admin/invoices/{$inv->id}/request-payment",['requested_amount'=>300])->assertRedirect();
        $inv->refresh();
        $this->assertEquals(300.0, $inv->payableAmount(), 'pay link charges exactly the set amount');

        // 3. public pay page charges exactly 300
        $this->get("/invoice/pay/{$inv->public_token}")->assertOk()->assertSee('300.00');

        // 4. dev checkout + success records the payment
        $this->post("/invoice/pay/{$inv->public_token}/checkout")->assertRedirect();
        $this->get("/invoice/pay/{$inv->public_token}/success?dev=1")->assertOk();
        $inv->refresh();
        $this->assertEquals(300.0, (float)$inv->amount_paid, 'payment recorded');
        $this->assertEquals(600.0, $inv->amountDue(), 'due updated');
        $this->assertEquals('partially_paid', $inv->status);
        $this->assertNull($inv->requested_amount, 'amount-to-receive cleared after pay');

        // 5. next amount-to-receive cannot exceed the NEW due (600)
        $this->post("/admin/invoices/{$inv->id}/request-payment",['requested_amount'=>700])
            ->assertSessionHasErrors('requested_amount');

        // 6. save & send (mail log)
        config(['mail.default'=>'log']);
        $this->post("/admin/invoices/{$inv->id}/send")->assertRedirect();

        // admin show renders the amount-to-receive + pay link
        $this->get("/admin/invoices/{$inv->id}")->assertOk()->assertSee('Amount to Receive')->assertSee('Client Pay Link');

        $inv->delete();
        $this->assertTrue(true);
    }
}
