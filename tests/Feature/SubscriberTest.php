<?php
namespace Tests\Feature;
use App\Models\Subscriber; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;

class SubscriberTest extends TestCase {
    use RefreshDatabase;

    public function test_public_subscribe_endpoint_captures_email(): void {
        $this->postJson('/api/subscribe', ['email'=>'Reader@Example.com', 'source'=>'blog', 'article'=>'My Post'])
            ->assertCreated();

        $this->assertDatabaseHas('subscribers', ['email'=>'reader@example.com', 'source'=>'blog', 'article'=>'My Post', 'is_active'=>true]);

        // idempotent — same email doesn't duplicate
        $this->postJson('/api/subscribe', ['email'=>'reader@example.com'])->assertCreated();
        $this->assertSame(1, Subscriber::where('email','reader@example.com')->count());

        // invalid email rejected
        $this->postJson('/api/subscribe', ['email'=>'not-an-email'])->assertStatus(422);
    }

    public function test_admin_can_manage_subscribers(): void {
        $admin = User::create(['name'=>'A','email'=>'sub-admin@test.local','password'=>'password','role'=>'admin']);
        $sub = Subscriber::create(['email'=>'a@b.com','source'=>'blog','is_active'=>true]);
        $this->actingAs($admin);

        $this->get('/admin/subscribers')->assertOk()->assertSee('a@b.com')->assertSee('Subscribers');

        // manual add
        $this->post('/admin/subscribers', ['email'=>'new@b.com','name'=>'New'])->assertRedirect();
        $this->assertDatabaseHas('subscribers', ['email'=>'new@b.com', 'source'=>'manual']);

        // toggle active
        $this->put("/admin/subscribers/{$sub->id}", ['is_active'=>0])->assertRedirect();
        $this->assertFalse($sub->fresh()->is_active);

        // delete
        $this->delete("/admin/subscribers/{$sub->id}")->assertRedirect();
        $this->assertDatabaseMissing('subscribers', ['id'=>$sub->id]);
    }

    public function test_staff_needs_subscribers_permission(): void {
        $staff = User::create(['name'=>'S','email'=>'sub-staff@test.local','password'=>'password','role'=>'staff','permissions'=>['leads']]);
        $this->actingAs($staff);
        $this->get('/admin/subscribers')->assertForbidden();
    }
}
