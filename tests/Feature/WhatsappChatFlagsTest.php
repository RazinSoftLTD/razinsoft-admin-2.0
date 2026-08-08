<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pins and "unread" belong to the person who set them, not to the whole team. */
class WhatsappChatFlagsTest extends TestCase
{
    use RefreshDatabase;

    private WhatsappAccount $account;

    private WhatsappChat $older;

    private WhatsappChat $newer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = WhatsappAccount::create(['name' => 'Main', 'session_key' => 'main', 'driver' => 'baileys']);
        $this->older = WhatsappChat::create([
            'account_id' => $this->account->id, 'wa_id' => '8801711111111@s.whatsapp.net',
            'chat_type' => 'private', 'status' => 'open', 'last_message_at' => now()->subDays(3),
        ]);
        $this->newer = WhatsappChat::create([
            'account_id' => $this->account->id, 'wa_id' => '8801722222222@s.whatsapp.net',
            'chat_type' => 'private', 'status' => 'open', 'last_message_at' => now(),
        ]);
    }

    private function agent(string $email): User
    {
        $u = User::create([
            'name' => 'Agent', 'email' => $email, 'password' => bcrypt('secret123'),
            'role' => 'staff', 'status' => 'active', 'permissions' => ['whatsapp.view' => 'all'],
        ]);
        $this->account->users()->syncWithoutDetaching([$u->id]);

        return $u;
    }

    private function listFor(User $u): array
    {
        return $this->actingAs($u)->getJson(route('admin.whatsapp.chats'))->assertOk()->json('chats');
    }

    public function test_a_pin_lifts_the_chat_only_for_the_person_who_pinned_it(): void
    {
        $lisa = $this->agent('lisa@example.com');
        $rafi = $this->agent('rafi@example.com');

        // Newest first to begin with, for both of them.
        $this->assertSame($this->newer->id, $this->listFor($lisa)[0]['id']);

        $this->actingAs($lisa)->postJson(route('admin.whatsapp.pin', $this->older))
            ->assertOk()->assertJson(['pinned' => true]);

        $lisaList = $this->listFor($lisa);
        $this->assertSame($this->older->id, $lisaList[0]['id'], 'Her pinned chat should lead her list.');
        $this->assertTrue($lisaList[0]['pinned']);

        // Rafi's list is untouched — the pin was hers.
        $rafiList = $this->listFor($rafi);
        $this->assertSame($this->newer->id, $rafiList[0]['id']);
        $this->assertFalse(collect($rafiList)->firstWhere('id', $this->older->id)['pinned']);

        // And it unpins again.
        $this->actingAs($lisa)->postJson(route('admin.whatsapp.pin', $this->older))
            ->assertOk()->assertJson(['pinned' => false]);
        $this->assertSame($this->newer->id, $this->listFor($lisa)[0]['id']);
    }

    public function test_marking_unread_sticks_and_is_private_to_that_person(): void
    {
        $lisa = $this->agent('lisa2@example.com');
        $rafi = $this->agent('rafi2@example.com');

        $this->actingAs($lisa)->postJson(route('admin.whatsapp.unread', $this->newer))->assertOk();

        // It survives a plain list refresh — the old shared counter did not.
        $mine = collect($this->listFor($lisa))->firstWhere('id', $this->newer->id);
        $this->assertGreaterThan(0, $mine['unread']);

        // Rafi opening the chat does not clear her mark.
        $this->actingAs($rafi)->getJson(route('admin.whatsapp.show', $this->newer))->assertOk();
        $stillMine = collect($this->listFor($lisa))->firstWhere('id', $this->newer->id);
        $this->assertGreaterThan(0, $stillMine['unread'], "A colleague's read should not clear her mark.");

        // Her own visit does.
        $this->actingAs($lisa)->getJson(route('admin.whatsapp.show', $this->newer))->assertOk();
        $cleared = collect($this->listFor($lisa))->firstWhere('id', $this->newer->id);
        $this->assertSame(0, $cleared['unread']);
    }

    public function test_the_unread_filter_includes_chats_marked_unread_by_hand(): void
    {
        $lisa = $this->agent('lisa3@example.com');

        $this->actingAs($lisa)->postJson(route('admin.whatsapp.unread', $this->older))->assertOk();

        $filtered = $this->actingAs($lisa)->getJson(route('admin.whatsapp.chats').'?status=unread')
            ->assertOk()->json('chats');

        $this->assertSame([$this->older->id], collect($filtered)->pluck('id')->all());
    }
}
