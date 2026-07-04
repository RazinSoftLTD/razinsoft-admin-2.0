<?php
namespace Tests\Feature;
use App\Models\RecurringInvoice; use App\Models\ClientInvoice; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class D4RecurringTest extends TestCase {
    use RefreshDatabase;
    public function test_recurring(): void {
        $admin = User::create(['name'=>'A','email'=>'d4@test.local','password'=>'password','role'=>'admin']);
        $client = User::create(['name'=>'Cli','email'=>'d4c@test.local','password'=>'password','role'=>'customer']);
        $this->actingAs($admin);

        // create profile
        $this->post('/admin/recurring',['title'=>'Monthly Hosting','client_id'=>$client->id,'interval'=>'monthly','next_run_at'=>now()->subDay()->toDateString(),'due_days'=>14,'currency'=>'USD','active'=>'1','items'=>[['description'=>'Hosting','qty'=>1,'unit_price'=>150,'discount_percent'=>0,'tax_percent'=>10]]])->assertRedirect();
        $profile = RecurringInvoice::first();
        $this->assertNotNull($profile);
        $this->assertEquals('monthly',$profile->interval);

        // run now -> generates invoice
        $this->post("/admin/recurring/{$profile->id}/run")->assertRedirect();
        $inv = ClientInvoice::latest('id')->first();
        $this->assertNotNull($inv);
        $this->assertEquals(165.0,(float)$inv->total,'150 + 10% tax');
        $profile->refresh();
        $this->assertEquals(1,$profile->generated_count);
        $this->assertTrue($profile->next_run_at->gt(now()),'next_run advanced');

        // console command picks up due profiles
        $profile->update(['next_run_at'=>now()->subDay()->toDateString()]);
        $before = ClientInvoice::count();
        $this->artisan('invoices:recurring')->assertSuccessful();
        $this->assertEquals($before+1, ClientInvoice::count(),'command generated one');
    }
}
