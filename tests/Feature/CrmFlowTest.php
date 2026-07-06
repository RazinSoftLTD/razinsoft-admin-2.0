<?php
namespace Tests\Feature;
use App\Models\Deal; use App\Models\Lead; use App\Models\User; use App\Models\ClientInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;

class CrmFlowTest extends TestCase {
    use RefreshDatabase;

    private function admin(): User {
        return User::firstOrCreate(['email'=>'crm-admin@test.local'],['name'=>'A','password'=>'password','role'=>'admin']);
    }

    public function test_deal_show_page_links_lead_client_and_invoices(): void {
        $admin = $this->admin();
        $client = User::create(['name'=>'Acme','email'=>'acme@test.local','password'=>'password','role'=>'customer']);
        $lead = Lead::create(['full_name'=>'Jane Doe','phone'=>'123','lead_source'=>'Website','lead_status'=>'qualified','priority'=>'high','assigned_to'=>$admin->id]);
        $deal = Deal::create(['title'=>'Big Deal','client_id'=>$client->id,'lead_id'=>$lead->id,'stage'=>'won','value'=>5000,'currency'=>'USD','assigned_to'=>$admin->id]);
        $this->actingAs($admin);

        $res = $this->get("/admin/deals/{$deal->id}")->assertOk();
        $res->assertSee('Big Deal')->assertSee('5,000.00');
        $res->assertSee(route('admin.clients.show', $client->id), false);   // client link
        $res->assertSee(route('admin.leads.show', $lead->id), false);       // source lead link

        // creating an invoice from the deal links it back, and it shows on the deal page
        $this->post("/admin/deals/{$deal->id}/invoice")->assertRedirect();
        $inv = ClientInvoice::where('deal_id', $deal->id)->first();
        $this->assertNotNull($inv, 'invoice linked to the deal');
        $this->assertEquals(5000.0, (float) $inv->total);
        $this->get("/admin/deals/{$deal->id}")->assertOk()->assertSee($inv->invoice_number);
    }

    public function test_lead_show_lists_its_deals(): void {
        $admin = $this->admin();
        $lead = Lead::create(['full_name'=>'Bob','phone'=>'999','lead_source'=>'Referral','lead_status'=>'new','priority'=>'medium','assigned_to'=>$admin->id]);
        $deal = Deal::create(['title'=>'Bob Deal','lead_id'=>$lead->id,'stage'=>'proposal','value'=>1200,'currency'=>'USD','assigned_to'=>$admin->id]);
        $this->actingAs($admin);

        $this->get("/admin/leads/{$lead->id}")->assertOk()
            ->assertSee('Bob Deal')
            ->assertSee(route('admin.deals.show', $deal->id), false);
    }

    public function test_snooze_reschedules_without_recording_contact(): void {
        $admin = $this->admin();
        $lead = Lead::create(['full_name'=>'Sue','phone'=>'111','lead_source'=>'Website','lead_status'=>'contacted','priority'=>'high','assigned_to'=>$admin->id,'next_follow_up_at'=>now()->subDay()->toDateString()]);
        $this->actingAs($admin);

        $this->post("/admin/leads/{$lead->id}/snooze", ['days'=>7])->assertRedirect();
        $lead->refresh();
        $this->assertNull($lead->last_contacted_at, 'snooze does not record a contact');
        $this->assertSame(now()->addDays(7)->toDateString(), $lead->next_follow_up_at->toDateString());
    }

    public function test_crm_group_shows_in_sidebar_for_staff(): void {
        $staff = User::firstOrCreate(['email'=>'crm-staff@test.local'],['name'=>'S','password'=>'password','role'=>'staff','permissions'=>['leads.view'=>true,'deals.view'=>true]]);
        $this->actingAs($staff);
        // staff with the CRM permissions can reach the pages and see the CRM group
        $this->get('/admin/deals')->assertOk()->assertSee('CRM')->assertSee('Leads')->assertSee('Follow-up')->assertSee('Deals');
    }
}
