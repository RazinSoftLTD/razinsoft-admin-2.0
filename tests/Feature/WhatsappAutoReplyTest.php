<?php

namespace Tests\Feature;

use App\Models\AiFaq;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappChat;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use App\Services\WhatsappAutoReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The auto-reply's guards, with OpenAI and the gateway faked.
 *
 * Every wrong-speak case matters more than the right-speak one: a reply to a blocked contact,
 * a group, or over an agent's shoulder costs trust that no good answer buys back.
 */
class WhatsappAutoReplyTest extends TestCase
{
    use RefreshDatabase;

    private WhatsappAccount $account;

    private WhatsappChat $chat;

    protected function setUp(): void
    {
        parent::setUp();
        // Pin the host too: a developer's .env may point at a local stand-in, and the fakes
        // below only stub api.openai.com — an unpinned host would let requests through for real.
        config(['services.openai.key' => 'sk-test', 'services.openai.base_url' => 'https://api.openai.com']);

        $settings = WhatsappSetting::current();
        $settings->ai_settings = ['mode' => 'always'];
        $settings->driver = 'baileys';
        $settings->gateway_url = 'http://gateway.test';
        $settings->gateway_secret = 'secret';
        $settings->save();

        $this->account = WhatsappAccount::create([
            'name' => 'Sales', 'session_key' => 'sales', 'driver' => 'baileys',
            'is_connected' => true, 'ai_reply_enabled' => true,
        ]);
        $this->chat = WhatsappChat::create([
            'account_id' => $this->account->id, 'wa_id' => '8801711111111@s.whatsapp.net',
            'chat_type' => 'private', 'status' => 'open',
        ]);

    }

    /** The happy path: OpenAI answers, the gateway accepts. Installed per test, not in setUp — a later Http::fake cannot override an earlier stub. */
    private function fakeHappy(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Hello! How can we help?']]]]),
            'gateway.test/*' => Http::response(['id' => 'WA-SENT-1']),
        ]);
    }

    private function inbound(string $body = 'Hi there'): WhatsappMessage
    {
        return $this->chat->messages()->create([
            'direction' => 'in', 'type' => 'text', 'body' => $body, 'sent_at' => now(),
        ]);
    }

    private function service(): WhatsappAutoReplyService
    {
        return app(WhatsappAutoReplyService::class);
    }

    public function test_it_replies_and_marks_the_message_as_ai(): void
    {
        $this->fakeHappy();
        $why = $this->service()->maybeReply($this->chat, $this->inbound());

        $this->assertNull($why);
        $reply = $this->chat->messages()->reorder()->where('direction', 'out')->first();
        $this->assertNotNull($reply);
        $this->assertTrue((bool) $reply->ai_generated);
        $this->assertSame('Hello! How can we help?', $reply->body);
        $this->assertSame('WA-SENT-1', $reply->wa_message_id);
        $this->assertStringContainsString('Hello! How can we help?', $this->chat->fresh()->last_message_preview);
    }

    public function test_silent_when_the_number_is_not_enabled(): void
    {
        $this->fakeHappy();
        $this->account->update(['ai_reply_enabled' => false]);

        $this->assertSame('number not enabled', $this->service()->maybeReply($this->chat, $this->inbound()));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'openai'));
    }

    public function test_silent_when_mode_is_off(): void
    {
        $this->fakeHappy();
        $s = WhatsappSetting::current();
        $s->ai_settings = ['mode' => 'off'];
        $s->save();

        $this->assertSame('mode off', $this->service()->maybeReply($this->chat, $this->inbound()));
    }

    public function test_silent_for_blocked_chats_and_groups(): void
    {
        $this->fakeHappy();
        $this->chat->update(['blocked_at' => now()]);
        $this->assertSame('blocked or group', $this->service()->maybeReply($this->chat->fresh(), $this->inbound()));

        $group = WhatsappChat::create([
            'account_id' => $this->account->id, 'wa_id' => '123@g.us', 'chat_type' => 'group', 'status' => 'open',
        ]);
        $msg = $group->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'hi', 'sent_at' => now()]);
        $this->assertSame('blocked or group', $this->service()->maybeReply($group, $msg));
    }

    public function test_silent_once_an_agent_has_answered(): void
    {
        $this->fakeHappy();
        // Audience everyone, so the takeover guard is the one that speaks — under new_only the
        // earlier new-customer guard would already have silenced this chat.
        $s = WhatsappSetting::current();
        $s->ai_settings = ['mode' => 'always', 'audience' => 'everyone'];
        $s->save();
        $incoming = $this->inbound();
        $this->chat->messages()->create(['direction' => 'out', 'type' => 'text', 'body' => 'Agent here', 'sent_at' => now()]);

        $this->assertSame('agent already replied', $this->service()->maybeReply($this->chat, $incoming));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'openai'));
    }

    public function test_never_answers_the_same_message_twice(): void
    {
        $this->fakeHappy();
        $incoming = $this->inbound();
        $this->assertNull($this->service()->maybeReply($this->chat, $incoming));

        // The gateway redelivers; the second pass sees its own earlier answer and stays quiet.
        $this->assertSame('already replied', $this->service()->maybeReply($this->chat->fresh(), $incoming));
    }

    public function test_outside_hours_mode_stays_quiet_during_the_office_day(): void
    {
        $this->fakeHappy();
        $s = WhatsappSetting::current();
        $s->ai_settings = [
            'mode' => 'outside_hours',
            'office_days' => [now('Asia/Dhaka')->isoWeekday()],
            'office_start' => '00:00',
            'office_end' => '24:00',
        ];
        $s->save();

        $this->assertSame('inside office hours', $this->service()->maybeReply($this->chat, $this->inbound()));
    }

    public function test_outside_hours_mode_speaks_when_the_office_is_closed(): void
    {
        $this->fakeHappy();
        $s = WhatsappSetting::current();
        $s->ai_settings = [
            'mode' => 'outside_hours',
            'office_days' => [],   // never open — always outside hours
        ];
        $s->save();

        $this->assertNull($this->service()->maybeReply($this->chat, $this->inbound()));

        // And the model was told it is out of hours, so it can say when the team returns.
        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), 'openai')) {
                return true;
            }

            return str_contains($r->body(), 'OUTSIDE office hours');
        });
    }

    public function test_the_daily_cap_hands_the_chat_to_a_person(): void
    {
        $this->fakeHappy();
        $s = WhatsappSetting::current();
        $s->ai_settings = ['mode' => 'always', 'max_replies_per_chat_per_day' => 2];
        $s->save();

        $this->assertNull($this->service()->maybeReply($this->chat, $this->inbound('one')));
        $this->assertNull($this->service()->maybeReply($this->chat->fresh(), $this->inbound('two')));
        $this->assertSame('daily cap reached', $this->service()->maybeReply($this->chat->fresh(), $this->inbound('three')));
    }

    public function test_silent_without_an_api_key(): void
    {
        $this->fakeHappy();
        config(['services.openai.key' => null]);

        $this->assertSame('no api key', $this->service()->maybeReply($this->chat, $this->inbound()));
    }

    /** The shelf is plain database text — it must work before anyone has an OpenAI account. */
    public function test_the_faq_shelf_answers_even_without_an_api_key(): void
    {
        $this->fakeHappy();
        config(['services.openai.key' => null]);
        AiFaq::create(['keywords' => 'demo', 'reply' => 'Here is the demo: demo.razinsoft.com']);

        $this->assertNull($this->service()->maybeReply($this->chat, $this->inbound('demo link ta den')));
        $reply = $this->chat->messages()->reorder()->where('direction', 'out')->first();
        $this->assertStringContainsString('demo.razinsoft.com', $reply->body);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'openai'));
    }

    public function test_a_keyword_hit_answers_from_the_database_without_openai(): void
    {
        $this->fakeHappy();
        AiFaq::create(['keywords' => 'price, দাম', 'reply' => 'Prices start at $39 — see razinsoft.com/products.']);

        $why = $this->service()->maybeReply($this->chat, $this->inbound('What is the price of Ready eCommerce?'));

        $this->assertNull($why);
        $reply = $this->chat->messages()->reorder()->where('direction', 'out')->first();
        $this->assertStringContainsString('Prices start at $39', $reply->body);
        $this->assertTrue((bool) $reply->ai_generated);
        $this->assertSame(1, (int) AiFaq::first()->hit_count);
        // The whole point of the shelf: OpenAI was never called.
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'openai'));
    }

    public function test_handover_sends_one_note_and_then_leaves_the_chat_to_the_team(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '[HANDOVER]']]]]),
            'gateway.test/*' => Http::response(['id' => 'WA-H1']),
        ]);

        $why = $this->service()->maybeReply($this->chat, $this->inbound('I want to talk to a real person'));

        $this->assertSame('handed over to the team', $why);
        $this->assertNotNull($this->chat->fresh()->ai_handover_at);
        $note = $this->chat->messages()->reorder()->where('direction', 'out')->first();
        $this->assertStringContainsString('team will take it from here', $note->body);
        $this->assertStringNotContainsString('[HANDOVER]', $note->body);

        // The next message stays with the team — no second AI reply.
        $this->assertSame('handed over to the team', $this->service()->maybeReply($this->chat->fresh(), $this->inbound('hello?')));
    }

    public function test_new_only_skips_chats_the_team_has_spoken_in(): void
    {
        $this->fakeHappy();
        // A human replied last month; this relationship is theirs.
        $this->chat->messages()->create(['direction' => 'out', 'type' => 'text', 'body' => 'Hi, Lisa here', 'sent_at' => now()->subMonth()]);

        $this->assertSame('not a new customer', $this->service()->maybeReply($this->chat, $this->inbound()));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'openai'));
    }

    public function test_new_only_skips_chats_linked_to_a_client(): void
    {
        $this->fakeHappy();
        $client = User::factory()->create();
        $this->chat->update(['client_id' => $client->id]);

        $this->assertSame('not a new customer', $this->service()->maybeReply($this->chat->fresh(), $this->inbound()));
    }

    public function test_everyone_mode_replies_despite_old_history(): void
    {
        $this->fakeHappy();
        $s = WhatsappSetting::current();
        $s->ai_settings = ['mode' => 'always', 'audience' => 'everyone'];
        $s->save();
        $this->chat->messages()->create(['direction' => 'out', 'type' => 'text', 'body' => 'Hi, Lisa here', 'sent_at' => now()->subMonth()]);

        $this->assertNull($this->service()->maybeReply($this->chat, $this->inbound()));
    }

    /**
     * A Cloud API number must answer too.
     *
     * The hook lived only on the paired-phone gateway, so the two numbers the team actually
     * enabled — both Cloud API — sat silent with nothing in the logs to explain it.
     */
    public function test_a_cloud_api_number_answers_from_its_webhook(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]])]);

        $cloud = WhatsappAccount::create([
            'name' => 'WA 89', 'session_key' => 'wa89', 'driver' => 'cloud_api',
            'phone_number_id' => '11122233', 'access_token' => 'EAAtoken', 'api_version' => 'v21.0',
            'is_connected' => true, 'ai_reply_enabled' => true,
        ]);
        AiFaq::create(['keywords' => 'demo', 'reply' => 'Demo: demo.razinsoft.com']);

        $this->postJson('/api/whatsapp/webhook', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '11122233'],
                        'contacts' => [['wa_id' => '8801999999999', 'profile' => ['name' => 'Rafi']]],
                        'messages' => [[
                            'id' => 'wamid.IN1', 'from' => '8801999999999', 'type' => 'text',
                            'text' => ['body' => 'demo link ta den'], 'timestamp' => (string) now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        // terminating() callbacks run once the response is sent.
        app()->terminate();

        $chat = WhatsappChat::where('account_id', $cloud->id)->firstOrFail();
        $reply = $chat->messages()->reorder()->where('direction', 'out')->first();
        $this->assertNotNull($reply, 'The Cloud API webhook never triggered the auto-reply.');
        $this->assertStringContainsString('demo.razinsoft.com', $reply->body);
        $this->assertSame('faq', $reply->ai_source);
    }

    /**
     * The team's own line answers whatever the policy says.
     *
     * Every rule that protects customers — office hours, new-customers-only, the human takeover —
     * conspires to silence exactly the number the team would test with.
     */
    public function test_a_test_number_is_answered_through_every_policy_guard(): void
    {
        $this->fakeHappy();
        $s = WhatsappSetting::current();
        $s->ai_settings = [
            'mode' => 'off',                                  // off for everyone else
            'audience' => 'new_only',
            'test_numbers' => '01316885500',                  // local form; the chat holds 8801…
        ];
        $s->save();

        $chat = WhatsappChat::create([
            'account_id' => $this->account->id, 'wa_id' => '8801316885500@s.whatsapp.net',
            'phone' => '8801316885500', 'chat_type' => 'private', 'status' => 'open',
        ]);
        // A long-standing conversation the team has answered by hand — normally silenced twice over.
        $chat->messages()->create(['direction' => 'out', 'type' => 'text', 'body' => 'Hi, Lisa here', 'sent_at' => now()->subMonth()]);
        $incoming = $chat->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'testing', 'sent_at' => now()]);

        $this->assertNull($this->service()->maybeReply($chat, $incoming));
        $this->assertSame('Hello! How can we help?', $chat->messages()->reorder()->where('ai_generated', true)->first()->body);
    }

    /** The bypass is for the listed number only — everyone else still obeys the mode. */
    public function test_another_number_is_not_let_through_by_the_test_list(): void
    {
        $this->fakeHappy();
        $s = WhatsappSetting::current();
        $s->ai_settings = ['mode' => 'off', 'test_numbers' => '01316885500'];
        $s->save();

        $this->assertSame('mode off', $this->service()->maybeReply($this->chat, $this->inbound()));
    }

    public function test_a_failed_draft_sends_nothing(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'overloaded'], 500),
            'gateway.test/*' => Http::response(['id' => 'X']),
        ]);

        $this->assertSame('draft failed', $this->service()->maybeReply($this->chat, $this->inbound()));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'gateway.test'));
    }
}
