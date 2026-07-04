<?php
namespace Tests\Feature;
use App\Models\ClientInvoice; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Support\Str; use Tests\TestCase;
class D6InvoicesApiTest extends TestCase {
    use RefreshDatabase;
    public function test_client_invoices_api(): void {
        $client = User::create(['name'=>'Cli','email'=>'d6@test.local','password'=>'password','role'=>'customer']);
        $other = User::create(['name'=>'Other','email'=>'d6o@test.local','password'=>'password','role'=>'customer']);
        $inv = ClientInvoice::create(['invoice_number'=>'INV-2026-9001','public_token'=>Str::random(40),'client_id'=>$client->id,'bill_to_name'=>'Cli','invoice_date'=>now()->toDateString(),'currency'=>'USD','status'=>'sent','subtotal'=>500,'total'=>500,'amount_paid'=>200]);
        $inv->items()->create(['description'=>'X','qty'=>1,'unit_price'=>500,'amount'=>500]);
        ClientInvoice::create(['invoice_number'=>'INV-2026-9002','public_token'=>Str::random(40),'client_id'=>$other->id,'bill_to_name'=>'Other','invoice_date'=>now()->toDateString(),'currency'=>'USD','status'=>'sent','subtotal'=>99,'total'=>99]);

        $token = $client->createToken('t')->plainTextToken;
        $res = $this->withHeader('Authorization','Bearer '.$token)->getJson('/api/account/invoices')->assertOk();
        $res->assertJsonCount(1,'data'); // only own invoice
        $res->assertJsonFragment(['invoice_number'=>'INV-2026-9001','amount_due'=>300.0,'amount_paid'=>200.0]);
        $this->assertStringContainsString('/invoice/pay/', $res->json('data.0.pay_url'));
        // other client's invoice not visible
        $res->assertJsonMissing(['invoice_number'=>'INV-2026-9002']);
    }
}
