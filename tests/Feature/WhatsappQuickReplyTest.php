<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappQuickReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A quick reply is written once and shown on whichever numbers it belongs to. */
class WhatsappQuickReplyTest extends TestCase
{
    use RefreshDatabase;

    private WhatsappAccount $sales;

    private WhatsappAccount $support;

    private WhatsappAccount $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sales = WhatsappAccount::create(['name' => 'Sales', 'session_key' => 'sales', 'driver' => 'baileys']);
        $this->support = WhatsappAccount::create(['name' => 'Support', 'session_key' => 'support', 'driver' => 'baileys']);
        $this->other = WhatsappAccount::create(['name' => 'Nobody', 'session_key' => 'nobody', 'driver' => 'baileys']);
    }

    private function agent(): User
    {
        $u = User::create([
            'name' => 'Agent', 'email' => 'qr-agent@test.local', 'password' => bcrypt('secret123'),
            'role' => 'staff', 'status' => 'active',
            'permissions' => ['whatsapp.settings' => 'all', 'whatsapp.quick_replies' => 'all'],
        ]);
        $this->sales->users()->syncWithoutDetaching([$u->id]);
        $this->support->users()->syncWithoutDetaching([$u->id]);

        return $u;
    }

    public function test_one_reply_can_be_shown_on_several_numbers(): void
    {
        $this->actingAs($this->agent())->post(route('admin.whatsapp-settings.quick.store'), [
            'shortcut' => '/greet-test', 'body' => 'Hello there',
            'account_ids' => [$this->sales->id, $this->support->id],
        ])->assertRedirect();

        $reply = WhatsappQuickReply::firstWhere('shortcut', '/greet-test');
        $this->assertEqualsCanonicalizing(
            [$this->sales->id, $this->support->id],
            $reply->accounts->pluck('id')->all()
        );
    }

    public function test_editing_moves_it_between_numbers(): void
    {
        $agent = $this->agent();
        $reply = WhatsappQuickReply::create(['shortcut' => '/x', 'body' => 'Text', 'account_id' => $this->sales->id]);
        $reply->accounts()->sync([$this->sales->id]);

        $this->actingAs($agent)->put(route('admin.whatsapp-settings.quick.update', $reply), [
            'shortcut' => '/x', 'body' => 'Text', 'account_ids' => [$this->support->id],
        ])->assertRedirect();

        $this->assertSame([$this->support->id], $reply->fresh()->accounts->pluck('id')->all());
    }

    /** Numbers you cannot manage are dropped, not saved because they were posted. */
    public function test_a_number_you_do_not_manage_is_ignored(): void
    {
        $this->actingAs($this->agent())->post(route('admin.whatsapp-settings.quick.store'), [
            'shortcut' => '/y', 'body' => 'Text',
            'account_ids' => [$this->sales->id, $this->other->id],
        ])->assertRedirect();

        $reply = WhatsappQuickReply::firstWhere('shortcut', '/y');
        $this->assertSame([$this->sales->id], $reply->accounts->pluck('id')->all());
    }

    public function test_picking_only_numbers_you_cannot_manage_is_refused(): void
    {
        $this->actingAs($this->agent())->post(route('admin.whatsapp-settings.quick.store'), [
            'shortcut' => '/z', 'body' => 'Text', 'account_ids' => [$this->other->id],
        ])->assertForbidden();

        $this->assertDatabaseMissing('whatsapp_quick_replies', ['shortcut' => '/z']);
    }

    /** The field shows the slash as a prefix, so it is added back on the way in. */
    public function test_a_shortcut_is_stored_with_its_slash(): void
    {
        $this->actingAs($this->agent())->post(route('admin.whatsapp-settings.quick.store'), [
            'shortcut' => 'ping', 'body' => 'Still here?', 'account_ids' => [$this->sales->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('whatsapp_quick_replies', ['shortcut' => '/ping']);

        // Typing it with a slash is the same thing, not "//ping".
        $this->actingAs($this->agent())->post(route('admin.whatsapp-settings.quick.store'), [
            'shortcut' => '/pong', 'body' => 'Here', 'account_ids' => [$this->sales->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('whatsapp_quick_replies', ['shortcut' => '/pong']);
    }
}
