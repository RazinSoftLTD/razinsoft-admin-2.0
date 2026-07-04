<?php
namespace Tests\Feature;
use App\Models\ClientInvoice; use App\Models\User; use Illuminate\Support\Str; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class ClientProfileTest extends TestCase {
    use RefreshDatabase;
    public function test_client_profile_shows_details_and_invoices(): void {
        $admin = User::firstOrCreate(['email'=>'cp-admin@test.local'],['name'=>'A','password'=>'password','role'=>'admin']);
        $client = User::create(['name'=>'Acme Corp','email'=>'acme@test.local','password'=>'password','role'=>'customer','company'=>'Acme Ltd','phone'=>'0123']);
        $inv = ClientInvoice::create(['invoice_number'=>'INV-CP-1','public_token'=>Str::random(40),'client_id'=>$client->id,'bill_to_name'=>'Acme Corp','invoice_date'=>date('Y-m-d'),'currency'=>'USD','status'=>'sent','subtotal'=>1000,'total'=>1000,'created_by'=>$admin->id]);
        $inv->items()->create(['description'=>'Work','qty'=>1,'unit_price'=>1000,'amount'=>1000]);
        $inv->payments()->create(['amount'=>400,'method'=>'Cash','paid_at'=>now()]);
        $inv->recomputePaid(); $inv->syncStatus(); $inv->save();

        $this->actingAs($admin);

        // profile page: details + the client's invoice + running totals
        $res = $this->get("/admin/clients/{$client->id}")->assertOk();
        $res->assertSee('Acme Corp')->assertSee('acme@test.local')->assertSee('INV-CP-1');
        $res->assertSee('1,000.00'); // invoiced
        $res->assertSee('600.00');   // due (1000 - 400)

        // invoice list links the client name to the profile
        $this->get('/admin/invoices')->assertOk()
            ->assertSee(route('admin.clients.show', $client->id), false);

        // inside the invoice view, the Bill To customer links to the profile too
        $this->get("/admin/invoices/{$inv->id}")->assertOk()
            ->assertSee(route('admin.clients.show', $client->id), false);

        // non-customer users are not reachable as "clients"
        $this->get("/admin/clients/{$admin->id}")->assertNotFound();
    }

    public function test_new_invoice_from_profile_preselects_client(): void {
        $admin = User::firstOrCreate(['email'=>'cp-admin2@test.local'],['name'=>'A','password'=>'password','role'=>'admin']);
        $client = User::create(['name'=>'Beta','email'=>'beta@test.local','password'=>'password','role'=>'customer']);
        $this->actingAs($admin);

        $this->get(route('admin.invoices.create', ['client_id'=>$client->id]))->assertOk()
            ->assertSee("clientId: '{$client->id}'", false);
    }
}
