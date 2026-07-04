<?php
namespace Tests\Feature;
use App\Models\Lead; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;

class LeadScheduleFollowUpTest extends TestCase {
    use RefreshDatabase;

    public function test_follow_up_date_can_be_set_from_the_lead_view(): void {
        $admin = User::firstOrCreate(['email'=>'sf-admin@test.local'],['name'=>'A','password'=>'password','role'=>'admin']);
        $lead = Lead::create(['full_name'=>'Karim','phone'=>'1','lead_source'=>'Website','lead_status'=>'new','priority'=>'high','assigned_to'=>$admin->id]);
        $this->actingAs($admin);

        // the lead detail page renders the inline follow-up form
        $this->get("/admin/leads/{$lead->id}")->assertOk()->assertSee(route('admin.leads.schedule-follow-up', $lead), false);

        // setting a date schedules it — without recording a contact
        $date = now()->addDays(5)->toDateString();
        $this->post(route('admin.leads.schedule-follow-up', $lead), ['next_follow_up_at'=>$date])->assertRedirect();
        $lead->refresh();
        $this->assertSame($date, $lead->next_follow_up_at->toDateString());
        $this->assertNull($lead->last_contacted_at, 'scheduling does not record a contact');

        // it now appears on the Follow-up page
        $this->get('/admin/leads/follow-up')->assertOk()->assertSee('Karim');

        // clearing the date removes it from follow-ups
        $this->post(route('admin.leads.schedule-follow-up', $lead), ['next_follow_up_at'=>''])->assertRedirect();
        $this->assertNull($lead->refresh()->next_follow_up_at);
    }
}
