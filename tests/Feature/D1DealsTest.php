<?php
namespace Tests\Feature;
use App\Models\Deal; use App\Models\ClientInvoice; use App\Models\Lead; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class D1DealsTest extends TestCase {
    use RefreshDatabase;
    public function test_deals_flow(): void {
        $admin = User::create(['name'=>'A','email'=>'d1@test.local','password'=>'password','role'=>'admin']);
        $client = User::create(['name'=>'Cli','email'=>'d1c@test.local','password'=>'password','role'=>'customer','company'=>'Acme']);
        $this->actingAs($admin);

        // board renders
        $this->get('/admin/deals')->assertOk()->assertSee('Deals')->assertSee('Qualified');

        // create
        $this->post('/admin/deals',['title'=>'Acme Project','client_id'=>$client->id,'stage'=>'proposal','value'=>5000,'currency'=>'USD','assigned_to'=>$admin->id])->assertRedirect();
        $deal = Deal::first();
        $this->assertEquals('proposal',$deal->stage);

        // move to won
        $this->post("/admin/deals/{$deal->id}/stage",['stage'=>'won'])->assertRedirect();
        $this->assertEquals('won',$deal->fresh()->stage);

        // won -> invoice
        $this->post("/admin/deals/{$deal->id}/invoice")->assertRedirect();
        $inv = ClientInvoice::first();
        $this->assertNotNull($inv,'invoice created from deal');
        $this->assertEquals(5000.0,(float)$inv->total);
        $this->assertEquals('Acme Project',$inv->items()->first()->description);
        $this->assertEquals($client->id,$inv->client_id);

        // lead -> deal prefill
        $lead = Lead::create(['full_name'=>'John','company_name'=>'BigCo','email'=>'j@x.com','phone'=>'1','lead_source'=>'Website','lead_status'=>'qualified','assigned_to'=>$admin->id,'priority'=>'high']);
        $this->get("/admin/deals/create?lead={$lead->id}")->assertOk()->assertSee('BigCo');
    }
}
