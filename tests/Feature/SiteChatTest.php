<?php

namespace Tests\Feature;

use App\Models\AiFaq;
use App\Models\SiteChat;
use App\Models\SiteChatOption;
use App\Models\User;
use App\Models\WhatsappSetting;
use App\Services\SiteChatAutoReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The website chat: what a visitor can reach, and what the assistant does with it. */
class SiteChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Pin the host: a developer's .env may point at a local stand-in, and the fakes below
        // only stub api.openai.com.
        config(['services.openai.key' => 'sk-test', 'services.openai.base_url' => 'https://api.openai.com']);

        $s = WhatsappSetting::current();
        $s->ai_settings = ['mode' => 'always', 'site_chat' => true];
        $s->save();
    }

    private function startChat(): string
    {
        return $this->postJson('/api/site-chat/start', [])->assertOk()->json('token');
    }

    public function test_a_visitor_can_open_a_conversation_and_send_a_message(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Happy to help!']]]])]);

        $token = $this->startChat();
        $this->assertDatabaseCount('site_chats', 1);

        $this->postJson('/api/site-chat/send', ['token' => $token, 'body' => 'Hello there'])
            ->assertOk()->assertJsonPath('message.body', 'Hello there');

        $this->assertDatabaseHas('site_chat_messages', ['body' => 'Hello there', 'direction' => 'in']);
    }

    /** The token is the whole of the visitor's authority — it must not reach anyone else's thread. */
    public function test_a_wrong_token_reaches_nothing(): void
    {
        $this->startChat();

        $this->postJson('/api/site-chat/send', ['token' => '11111111-1111-1111-1111-111111111111', 'body' => 'Hi'])
            ->assertNotFound();
        $this->getJson('/api/site-chat/poll?token=11111111-1111-1111-1111-111111111111')->assertNotFound();
    }

    public function test_tapping_a_menu_option_records_it_and_answers_from_the_option(): void
    {
        $option = SiteChatOption::create(['label' => 'Ask about our products', 'reply' => 'Which one interests you?', 'position' => 1]);
        $token = $this->startChat();

        $messages = $this->postJson('/api/site-chat/option', ['token' => $token, 'option_id' => $option->id])
            ->assertOk()->json('messages');

        $this->assertSame(['Ask about our products', 'Which one interests you?'], array_column($messages, 'body'));
        $this->assertSame(1, (int) $option->fresh()->taps);
    }

    public function test_the_faq_shelf_answers_without_calling_openai(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'should not be used']]]])]);
        AiFaq::create(['keywords' => 'price, দাম', 'reply' => 'Our products start at $39.']);

        $token = $this->startChat();
        $this->postJson('/api/site-chat/send', ['token' => $token, 'body' => 'what is the price?'])->assertOk();
        app()->terminate();

        $chat = SiteChat::firstWhere('token', $token);
        $reply = $chat->messages()->where('direction', 'out')->first();
        $this->assertStringContainsString('start at $39', $reply->body);
        $this->assertSame('faq', $reply->ai_source);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'openai'));
    }

    public function test_asking_for_a_person_hands_the_conversation_over_once(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '[HANDOVER]']]]])]);

        $token = $this->startChat();
        $this->postJson('/api/site-chat/send', ['token' => $token, 'body' => 'I want a real person'])->assertOk();
        app()->terminate();

        $chat = SiteChat::firstWhere('token', $token);
        $note = $chat->messages()->where('direction', 'out')->first();
        $this->assertStringContainsString('team will take it from here', $note->body);
        $this->assertStringNotContainsString('[HANDOVER]', $note->body);
        $this->assertNotNull($chat->fresh()->ai_handover_at);
        $this->assertSame('pending', $chat->fresh()->status);
    }

    public function test_the_assistant_stays_quiet_once_a_person_has_answered(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'AI answer']]]])]);

        $token = $this->startChat();
        $chat = SiteChat::firstWhere('token', $token);
        $incoming = $chat->messages()->create(['direction' => 'in', 'body' => 'Anyone there?']);
        $chat->messages()->create(['direction' => 'out', 'body' => 'Yes, Lisa here', 'ai_generated' => false]);

        $why = app(SiteChatAutoReplyService::class)->maybeReply($chat, $incoming);

        $this->assertSame('agent already replied', $why);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'openai'));
    }

    public function test_the_team_reads_and_answers_from_the_panel(): void
    {
        $token = $this->startChat();
        $this->postJson('/api/site-chat/send', ['token' => $token, 'body' => 'Need a quote'])->assertOk();

        $admin = User::create([
            'name' => 'Boss', 'email' => 'site-chat-admin@test.local',
            'password' => bcrypt('secret123'), 'role' => 'admin', 'status' => 'active',
        ]);
        $chat = SiteChat::firstWhere('token', $token);

        $this->actingAs($admin)->get('/admin/site-chat')->assertOk()->assertSee('Website Chat');
        $this->getJson("/admin/site-chat/{$chat->id}")->assertOk()->assertJsonPath('chat.id', $chat->id);

        $this->postJson("/admin/site-chat/{$chat->id}/reply", ['body' => 'Sending it over now'])->assertOk();

        // …and the visitor's widget sees it on its next poll.
        $seen = $this->getJson("/api/site-chat/poll?token={$token}&since=1")->assertOk()->json('messages');
        $this->assertSame('Sending it over now', end($seen)['body']);
        $this->assertSame('Boss', end($seen)['agent']);
    }
}
