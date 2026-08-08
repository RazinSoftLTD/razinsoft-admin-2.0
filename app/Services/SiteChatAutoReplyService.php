<?php

namespace App\Services;

use App\Events\SiteChatMessageReceived;
use App\Models\AiFaq;
use App\Models\SiteChat;
use App\Models\SiteChatMessage;
use Illuminate\Support\Str;

/**
 * Razin AI on the website chat.
 *
 * The same shelf and the same model as WhatsApp — one FAQ list, one voice — because a customer
 * who asks the same question in two places should not get two different answers. What differs is
 * only who is asking: a visitor with no history, so the "new customer" rule that guards WhatsApp
 * has nothing to decide here.
 */
class SiteChatAutoReplyService
{
    public function __construct(private AiReplyService $ai) {}

    /** Returns the reason it stayed quiet, or null if it answered. */
    public function maybeReply(SiteChat $chat, SiteChatMessage $incoming): ?string
    {
        $settings = AiReplyService::settings();

        if (! ($settings['site_chat'] ?? true)) {
            return 'website chat off';
        }
        if (($settings['mode'] ?? 'off') === 'off') {
            return 'mode off';
        }
        if ($incoming->direction !== 'in') {
            return 'not an inbound message';
        }
        if (($settings['mode'] ?? '') === 'outside_hours' && $this->ai->insideOfficeHours($settings)) {
            return 'inside office hours';
        }

        // A person has picked this conversation up — the assistant does not talk over them.
        $answered = $chat->messages()
            ->where('direction', 'out')
            ->where('ai_generated', false)
            ->where('id', '>', $incoming->id)
            ->exists();
        if ($answered) {
            return 'agent already replied';
        }

        // Never answer the same message twice.
        if ($chat->messages()->where('ai_generated', true)->where('id', '>', $incoming->id)->exists()) {
            return 'already replied';
        }

        if ($chat->ai_handover_at && $chat->ai_handover_at->gt(now()->subDay())) {
            return 'handed over to the team';
        }

        $today = $chat->messages()->where('ai_generated', true)
            ->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= (int) ($settings['max_replies_per_chat_per_day'] ?? 20)) {
            return 'daily cap reached';
        }

        // ---- 1. The FAQ shelf answers first, free and instantly.
        if ($faq = AiFaq::match((string) $incoming->body)) {
            $faq->increment('hit_count');
            $this->speak($chat, $faq->reply, 'faq');

            return null;
        }

        // ---- 2. OpenAI. The shelf above needs no account; the key is only required here.
        if (! $this->ai->configured()) {
            return 'no api key';
        }

        $text = $this->ai->draft($this->transcript($chat), array_filter([
            $chat->name ? 'The visitor\'s name is '.$chat->name.'.' : null,
            $chat->page_url ? 'They are reading '.$chat->page_url.'.' : null,
            'You are replying in the chat widget on the RazinSoft website — keep it short.',
            'If they ask for a person, or you cannot answer with confidence, reply with exactly [HANDOVER] and nothing else.',
        ]), $settings);

        if ($text === null) {
            return 'draft failed';
        }

        // ---- 3. Handing over: said once, then the team owns the conversation.
        if (str_contains($text, '[HANDOVER]')) {
            $chat->update(['ai_handover_at' => now(), 'status' => 'pending']);
            $this->speak($chat, $settings['handover_message']
                ?? 'Thanks for reaching out! A member of our team will take it from here and reply to you shortly.', 'handover');

            return 'handed over to the team';
        }

        $this->speak($chat, $text, 'openai');

        return null;
    }

    private function speak(SiteChat $chat, string $text, string $source): void
    {
        $message = $chat->messages()->create([
            'direction' => 'out',
            'type' => 'text',
            'body' => $text,
            'ai_generated' => true,
            'ai_source' => $source,
        ]);

        $chat->update([
            'last_message_preview' => Str::limit($text, 140),
            'last_message_at' => now(),
        ]);

        try {
            event(new SiteChatMessageReceived($chat->id, $message->id, 'out', $chat->token));
        } catch (\Throwable) {
        }
    }

    /** @return array<int, array{role: 'user'|'assistant', text: string}> */
    private function transcript(SiteChat $chat): array
    {
        return $chat->messages()->whereNotNull('body')
            ->orderByDesc('id')->limit(12)->get()
            ->reverse()
            ->map(fn ($m) => [
                'role' => $m->direction === 'in' ? 'user' : 'assistant',
                'text' => (string) $m->body,
            ])
            ->values()
            ->all();
    }
}
