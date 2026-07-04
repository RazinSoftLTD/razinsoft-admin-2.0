<?php
namespace Tests\Feature;
use App\Models\Lead; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;

class LeadCodeTest extends TestCase {
    use RefreshDatabase;

    public function test_new_leads_get_a_sequential_ld_code(): void {
        $yy = date('y');
        $a = Lead::create(['full_name'=>'A','phone'=>'1','lead_source'=>'Website','lead_status'=>'new','priority'=>'high']);
        $b = Lead::create(['full_name'=>'B','phone'=>'2','lead_source'=>'Website','lead_status'=>'new','priority'=>'high']);

        $this->assertSame("LD-{$yy}0001", $a->lead_code);
        $this->assertSame("LD-{$yy}0002", $b->lead_code);
    }

    public function test_lead_code_is_shown_on_the_lead_pages(): void {
        $admin = User::firstOrCreate(['email'=>'lc-admin@test.local'],['name'=>'A','password'=>'password','role'=>'admin']);
        $lead = Lead::create(['full_name'=>'Jane','phone'=>'9','lead_source'=>'Referral','lead_status'=>'new','priority'=>'high','assigned_to'=>$admin->id]);
        $this->actingAs($admin);

        $this->get('/admin/leads')->assertOk()->assertSee($lead->lead_code);
        $this->get("/admin/leads/{$lead->id}")->assertOk()->assertSee($lead->lead_code);
        // old raw "LEAD-{id}" style is gone
        $this->get("/admin/leads/{$lead->id}")->assertDontSee("LEAD-{$lead->id}");
    }
}
