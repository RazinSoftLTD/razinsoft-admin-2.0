<?php

namespace App\Services;

use App\Events\WhatsappMessageReceived;
use App\Models\AiFaq;
use App\Models\WhatsappChat;
use App\Models\WhatsappMessage;
use Illuminate\Support\Str;

/**
 * The WhatsApp side of auto-reply: decides WHETHER to speak, then speaks.
 *
 * Every guard errs towards silence. A missing reply costs a follow-up; a wrong one — to a group,
 * to a blocked contact, on top of an agent already typing — costs the customer's trust in the
 * whole inbox. The drafting itself is AiReplyService, which knows nothing about WhatsApp.
 */
class WhatsappAutoReplyService
{
    public function __construct(private AiReplyService $ai) {}

    /** Called after an inbound message lands. Returns the reason it stayed silent, null if it spoke. */
    public function maybeReply(WhatsappChat $chat, WhatsappMessage $incoming): ?string
    {
        $settings = AiReplyService::settings();

        if (! $this->ai->configured()) {
            return 'no api key';
        }
        if (($settings['mode'] ?? 'off') === 'off') {
            return 'mode off';
        }
        if (! $chat->account?->ai_reply_enabled) {
            return 'number not enabled';
        }
        if ($chat->blocked_at || $chat->isGroup()) {
            return 'blocked or group';
        }
        if ($incoming->direction !== 'in' || $incoming->deleted_at) {
            return 'not an inbound message';
        }
        if (($settings['mode'] ?? '') === 'outside_hours' && $this->ai->insideOfficeHours($settings)) {
            return 'inside office hours';
        }

        // New customers only: someone the team has never spoken to and who is not a known
        // client. An existing relationship belongs to the people who built it.
        if (($settings['audience'] ?? 'new_only') === 'new_only') {
            $known = $chat->client_id !== null
                || $chat->messages()->reorder()
                    ->where('direction', 'out')
                    ->where('ai_generated', false)
                    ->exists();
            if ($known) {
                return 'not a new customer';
            }
        }

        // Human takeover: an agent reply after this customer message means a person has the
        // conversation — the assistant does not talk over them.
        $answered = $chat->messages()->reorder()
            ->where('direction', 'out')
            ->where('ai_generated', false)
            ->where('id', '>', $incoming->id)
            ->exists();
        if ($answered) {
            return 'agent already replied';
        }

        // Never answer the same inbound twice (the gateway can redeliver).
        $alreadyReplied = $chat->messages()->reorder()
            ->where('ai_generated', true)
            ->where('id', '>', $incoming->id)
            ->exists();
        if ($alreadyReplied) {
            return 'already replied';
        }

        // A handed-over chat belongs to the team until they finish with it.
        if ($chat->ai_handover_at && $chat->ai_handover_at->gt(now()->subDay())) {
            return 'handed over to the team';
        }

        // Runaway brake: a chat that needed this many answers today needs a person.
        $today = $chat->messages()->reorder()
            ->where('ai_generated', true)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        if ($today >= (int) ($settings['max_replies_per_chat_per_day'] ?? 20)) {
            return 'daily cap reached';
        }

        // ---- 1. The FAQ shelf: a keyword hit answers from the database, instantly and free.
        if ($faq = AiFaq::match((string) $incoming->body)) {
            $faq->increment('hit_count');

            return $this->speak($chat, $faq->reply) ? null : 'send failed';
        }

        // ---- 2. OpenAI, told it may raise its hand.
        $text = $this->ai->draft($this->transcript($chat), array_filter([
            $chat->displayName() !== $chat->phoneLabel() ? 'The customer\'s name is '.$chat->displayName().'.' : null,
            'You are replying inside WhatsApp — keep it short.',
            'If the customer asks for a human, or you cannot help with confidence, reply with exactly [HANDOVER] and nothing else.',
        ]), $settings);

        if ($text === null) {
            return 'draft failed';
        }

        // ---- 3. Human handover: the assistant bows out, says so once, and stays out.
        if (str_contains($text, '[HANDOVER]')) {
            $chat->update(['ai_handover_at' => now()]);
            $note = $settings['handover_message']
                ?? 'Thanks for reaching out! A member of our team will take it from here and reply to you shortly.';
            $this->speak($chat, $note);

            return 'handed over to the team';
        }

        return $this->speak($chat, $text) ? null : 'send failed';
    }

    /** Send one assistant message and record it the way the inbox expects. */
    private function speak(WhatsappChat $chat, string $text): bool
    {
        try {
            $waId = WhatsappService::for($chat->account)->sendText($chat->wa_id, $text);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        $message = $chat->messages()->create([
            'direction' => 'out',
            'type' => 'text',
            'body' => $text,
            'wa_message_id' => $waId,
            'status' => 'sent',
            'ai_generated' => true,
            'sent_at' => now(),
        ]);

        $chat->update([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($text, 120),
        ]);

        try {
            event(new WhatsappMessageReceived($chat->id, $message->id, 'out'));
        } catch (\Throwable) {
        }

        return true;
    }

    /** The chat's recent history in the shape the drafting service wants. */
    private function transcript(WhatsappChat $chat): array
    {
        return $chat->messages()->reorder()
            ->whereNull('deleted_at')
            ->where('type', 'text')
            ->whereNotNull('body')
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
