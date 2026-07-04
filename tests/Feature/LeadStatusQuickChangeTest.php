<?php
namespace Tests\Feature;
use App\Models\Lead; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;

class LeadStatusQuickChangeTest extends TestCase {
    use RefreshDatabase;

    public function test_status_can_be_changed_from_the_endpoint(): void {
        $admin = User::firstOrCreate(['email'=>'ls-admin@test.local'],['name'=>'A','password'=>'password','role'=>'admin']);
        $lead = Lead::create(['full_name'=>'Jane','phone'=>'1','lead_source'=>'Website','lead_status'=>'new','priority'=>'high','assigned_to'=>$admin->id,'last_contacted_at'=>null]);
        $this->actingAs($admin);

        $this->post("/admin/leads/{$lead->id}/status", ['lead_status'=>'qualified'])->assertRedirect();
        $lead->refresh();
        $this->assertSame('qualified', $lead->lead_status);
        $this->assertNull($lead->last_contacted_at, 'quick status change does not touch contact fields');

        // invalid status rejected
        $this->post("/admin/leads/{$lead->id}/status", ['lead_status'=>'bogus'])->assertSessionHasErrors('lead_status');

        // list + detail render the status dropdown wired to the status route
        $url = route('admin.leads.status', $lead);
        $this->get('/admin/leads')->assertOk()->assertSee($url, false);
        $this->get("/admin/leads/{$lead->id}")->assertOk()->assertSee($url, false);
    }

    public function test_staff_cannot_change_another_users_lead(): void {
        $staff = User::firstOrCreate(['email'=>'ls-staff@test.local'],['name'=>'S','password'=>'password','role'=>'staff']);
        $other = User::firstOrCreate(['email'=>'ls-other@test.local'],['name'=>'O','password'=>'password','role'=>'staff']);
        $lead = Lead::create(['full_name'=>'X','phone'=>'2','lead_source'=>'Website','lead_status'=>'new','priority'=>'high','assigned_to'=>$other->id]);
        $this->actingAs($staff);

        $this->post("/admin/leads/{$lead->id}/status", ['lead_status'=>'won'])->assertForbidden();
    }
}
