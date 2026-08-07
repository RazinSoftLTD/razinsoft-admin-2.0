<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappChat;
use App\Models\WhatsappLabel;
use App\Models\WhatsappSetting;
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

    /** The point of the headstone: the phone replays its history and must not undo the wipe. */
    public function test_a_history_resync_cannot_bring_the_deleted_chat_back(): void
    {
        $settings = WhatsappSetting::current();
        $settings->gateway_secret = 'secret';
        $settings->save();

        $this->chat->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'old talk', 'sent_at' => now()->subDay()]);

        $this->actingAs($this->superAdmin())
            ->deleteJson(route('admin.whatsapp.chat.destroy', $this->chat))->assertOk();

        // The gateway replays a message from before the wipe.
        $this->postJson('/api/whatsapp/gateway', [
            'event' => 'message', 'session' => 'main', 'historic' => true,
            'from' => '8801711111111@s.whatsapp.net', 'phone' => '8801711111111',
            'text' => 'old talk', 'timestamp' => now()->subDay()->timestamp,
        ], ['X-Gateway-Secret' => 'secret'])->assertOk();

        $this->assertDatabaseCount('whatsapp_chats', 0);
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    /** But the contact writing again is a new conversation, not a resurrection. */
    public function test_a_new_message_after_the_wipe_starts_a_fresh_chat(): void
    {
        $settings = WhatsappSetting::current();
        $settings->gateway_secret = 'secret';
        $settings->save();

        $this->chat->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'old talk', 'sent_at' => now()->subDay()]);
        $this->actingAs($this->superAdmin())
            ->deleteJson(route('admin.whatsapp.chat.destroy', $this->chat))->assertOk();

        $this->postJson('/api/whatsapp/gateway', [
            'event' => 'message', 'session' => 'main',
            'from' => '8801711111111@s.whatsapp.net', 'phone' => '8801711111111',
            'text' => 'Hello again', 'timestamp' => now()->addMinute()->timestamp,
        ], ['X-Gateway-Secret' => 'secret'])->assertOk();

        $this->assertDatabaseCount('whatsapp_chats', 1);
        $this->assertDatabaseHas('whatsapp_messages', ['body' => 'Hello again']);
        // …and only the new message, never the old history.
        $this->assertDatabaseMissing('whatsapp_messages', ['body' => 'old talk']);
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
