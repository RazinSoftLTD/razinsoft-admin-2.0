<?php
namespace Tests\Feature;
use App\Models\Lead; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class D2FollowupTest extends TestCase {
    use RefreshDatabase;
    public function test_followup(): void {
        $admin = User::create(['name'=>'A','email'=>'d2@test.local','password'=>'password','role'=>'admin']);
        $this->actingAs($admin);
        $mk = fn($name,$date)=>Lead::create(['full_name'=>$name,'email'=>strtolower($name).'@x.com','phone'=>'1','lead_source'=>'Website','lead_status'=>'contacted','assigned_to'=>$admin->id,'priority'=>'high','next_follow_up_at'=>$date]);
        $overdue = $mk('Over', now()->subDays(2)->toDateString());
        $mk('Todayy', now()->toDateString());
        $mk('Soon', now()->addDays(3)->toDateString());

        $r = $this->get('/admin/leads/follow-up')->assertOk();
        $r->assertSee('Overdue')->assertSee('Today')->assertSee('Upcoming')->assertSee('Over')->assertSee('Soon');

        // mark contacted with next date
        $this->post("/admin/leads/{$overdue->id}/mark-contacted",['next_follow_up_at'=>now()->addDays(7)->toDateString()])->assertRedirect();
        $overdue->refresh();
        $this->assertNotNull($overdue->last_contacted_at,'last_contacted set');
        $this->assertTrue($overdue->next_follow_up_at->isSameDay(now()->addDays(7)),'rescheduled');

        // mark contacted done (no next)
        $this->post("/admin/leads/{$overdue->id}/mark-contacted",[])->assertRedirect();
        $this->assertNull($overdue->fresh()->next_follow_up_at,'cleared when done');
    }
}
