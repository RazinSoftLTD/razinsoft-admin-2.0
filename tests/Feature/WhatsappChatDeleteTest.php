<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappChat;
use App\Models\WhatsappLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Deleting a chat's whole history from the list — a super-admin-only, panel-local erase. */
class WhatsappChatDeleteTest extends TestCase
{
    use RefreshDatabase;

    private WhatsappChat $chat;

    protected function setUp(): void
    {
        parent::setUp();
        $account = WhatsappAccount::create(['name' => 'Main', 'session_key' => 'main', 'driver' => 'baileys']);
        $this->chat = WhatsappChat::create([
            'account_id' => $account->id, 'wa_id' => '8801711111111@s.whatsapp.net', 'chat_type' => 'private',
        ]);
    }

    /** A super admin assigned to the number — even admins only see accounts they are on. */
    private function superAdmin(): User
    {
        $admin = User::firstOrCreate(['email' => 'boss@example.com'], [
            'name' => 'Boss', 'password' => bcrypt('secret123'), 'role' => 'admin', 'status' => 'active',
        ]);
        $this->chat->account->users()->syncWithoutDetaching([$admin->id]);

        return $admin;
    }

    public function test_a_super_admin_erases_the_chat_with_everything_it_owns(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('whatsapp/photo.jpg', 'x');

        $this->chat->messages()->create([
            'direction' => 'in', 'type' => 'image', 'body' => null,
            'media_path' => 'whatsapp/photo.jpg', 'sent_at' => now(),
        ]);
        $this->chat->messages()->create(['direction' => 'out', 'type' => 'text', 'body' => 'hi', 'sent_at' => now()]);
        $this->chat->notes()->create(['body' => 'VIP', 'user_id' => $this->superAdmin()->id]);
        $label = WhatsappLabel::create(['name' => 'Hot', 'color' => '#f00']);
        $this->chat->labels()->attach($label->id);

        $this->actingAs(User::where('email', 'boss@example.com')->first())
            ->deleteJson(route('admin.whatsapp.chat.destroy', $this->chat))
            ->assertOk()->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('whatsapp_chats', ['id' => $this->chat->id]);
        $this->assertDatabaseCount('whatsapp_messages', 0);
        $this->assertDatabaseCount('whatsapp_notes', 0);
        $this->assertDatabaseCount('whatsapp_chat_label', 0);
        Storage::disk('public')->assertMissing('whatsapp/photo.jpg');
    }

    public function test_a_regular_staff_member_may_not(): void
    {
        $staff = User::create([
            'name' => 'Agent', 'email' => 'agent@example.com',
            'password' => bcrypt('secret123'), 'role' => 'staff', 'status' => 'active',
            'permissions' => ['whatsapp' => ['reply' => 'all']],
        ]);

        $this->actingAs($staff)
            ->deleteJson(route('admin.whatsapp.chat.destroy', $this->chat))
            ->assertForbidden();

        $this->assertDatabaseHas('whatsapp_chats', ['id' => $this->chat->id]);
    }
}
