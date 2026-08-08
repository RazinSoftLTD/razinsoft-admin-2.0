<?php

namespace App\Http\Controllers\Api;

use App\Events\SiteChatMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\SiteChat;
use App\Models\SiteChatMessage;
use App\Models\SiteChatOption;
use App\Models\User;
use App\Models\WhatsappSetting;
use App\Services\SiteChatAutoReplyService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The website chat, from the visitor's side.
 *
 * No login: a conversation is identified by a token the widget keeps in the browser. That is
 * enough to come back to your own thread and nothing else — every route here is scoped by the
 * token, never by an id from the request.
 */
class SiteChatController extends Controller
{
    /** Open (or resume) a conversation and hand back everything the widget needs to draw itself. */
    public function start(Request $request)
    {
        $data = $request->validate([
            'token' => ['nullable', 'uuid'],
            'page_url' => ['nullable', 'string', 'max:500'],
            'referrer' => ['nullable', 'string', 'max:500'],
        ]);

        $chat = $this->chatFor($data['token'] ?? null);

        if (! $chat) {
            $chat = SiteChat::create([
                'token' => (string) Str::uuid(),
                'status' => 'open',
                'page_url' => $data['page_url'] ?? null,
                'referrer' => $data['referrer'] ?? null,
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ]);
        }

        return response()->json([
            'token' => $chat->token,
            'chat' => $this->chatPayload($chat),
            'messages' => $chat->messages()->get()->map(fn ($m) => $this->messagePayload($m)),
            'options' => SiteChatOption::active()->get(['id', 'label', 'emoji']),
            'settings' => $this->widgetSettings(),
        ]);
    }

    /** Anything new since the last message the widget has seen. */
    public function poll(Request $request)
    {
        $chat = $this->chatFor($request->query('token'));
        abort_unless($chat, 404);

        $since = (int) $request->query('since', 0);

        return response()->json([
            'messages' => $chat->messages()->where('id', '>', $since)->get()->map(fn ($m) => $this->messagePayload($m)),
            'status' => $chat->status,
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $chat = $this->chatFor($data['token']);
        abort_unless($chat, 404);

        $message = $this->store($chat, 'in', $data['body']);
        $this->letTheAssistantAnswer($chat, $message);

        return response()->json(['message' => $this->messagePayload($message)]);
    }

    /** A tap on one of the menu buttons: recorded as the visitor's message, answered if we can. */
    public function option(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'option_id' => ['required', 'integer'],
        ]);

        $chat = $this->chatFor($data['token']);
        abort_unless($chat, 404);

        $option = SiteChatOption::active()->whereKey($data['option_id'])->first();
        abort_unless($option, 404);
        $option->increment('taps');

        $asked = $this->store($chat, 'in', $option->label, 'option');

        $answer = null;
        if (filled($option->reply)) {
            $answer = $this->store($chat, 'out', $option->reply, 'text', ['ai_generated' => true, 'ai_source' => 'option']);
        } else {
            // "Talk to a Support Agent" and friends: nothing canned to say, so either the
            // assistant answers or the team picks it up.
            $this->letTheAssistantAnswer($chat, $asked);
        }

        return response()->json([
            'messages' => array_values(array_filter([
                $this->messagePayload($asked),
                $answer ? $this->messagePayload($answer) : null,
            ])),
        ]);
    }

    /** Who they are, offered once the conversation is under way. */
    public function identify(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'name' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $chat = $this->chatFor($data['token']);
        abort_unless($chat, 404);

        $chat->fill(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]));

        // A visitor who leaves an email we already know is a client, not a stranger.
        if ($chat->email && ! $chat->client_id) {
            $chat->client_id = User::clients()->where('email', $chat->email)->value('id');
        }
        $chat->save();

        return response()->json(['chat' => $this->chatPayload($chat->fresh())]);
    }

    /**
     * Hand the message to Razin AI after the response is sent.
     *
     * The visitor's widget polls, so an answer a second later still feels immediate — and a slow
     * or failing model must never hold up (or break) the message they just sent.
     */
    private function letTheAssistantAnswer(SiteChat $chat, SiteChatMessage $incoming): void
    {
        $chatId = $chat->id;
        $messageId = $incoming->id;

        app()->terminating(function () use ($chatId, $messageId) {
            try {
                $chat = SiteChat::find($chatId);
                $message = SiteChatMessage::find($messageId);
                if ($chat && $message) {
                    app(SiteChatAutoReplyService::class)->maybeReply($chat, $message);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    // ---------------------------------------------------------------- internals

    private function chatFor(?string $token): ?SiteChat
    {
        return $token ? SiteChat::where('token', $token)->first() : null;
    }

    /** Write one message and keep the conversation's summary honest. */
    private function store(SiteChat $chat, string $direction, string $body, string $type = 'text', array $extra = []): SiteChatMessage
    {
        $message = $chat->messages()->create($extra + [
            'direction' => $direction,
            'type' => $type,
            'body' => $body,
        ]);

        $chat->update([
            'last_message_preview' => Str::limit($body, 140),
            'last_message_at' => now(),
            'unread_count' => $direction === 'in' ? $chat->unread_count + 1 : $chat->unread_count,
            'status' => $chat->status === 'resolved' && $direction === 'in' ? 'open' : $chat->status,
        ]);

        try {
            event(new SiteChatMessageReceived($chat->id, $message->id, $direction, $chat->token));
        } catch (\Throwable) {
        }

        return $message;
    }

    private function chatPayload(SiteChat $chat): array
    {
        return [
            'id' => $chat->id,
            'status' => $chat->status,
            'name' => $chat->name,
            'email' => $chat->email,
            'identified' => filled($chat->name) || filled($chat->email),
        ];
    }

    private function messagePayload(SiteChatMessage $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'type' => $m->type,
            'body' => $m->body,
            'attachment' => $m->attachmentUrl(),
            'attachment_name' => $m->attachment_name,
            'agent' => $m->agent?->name,
            'ai' => (bool) $m->ai_generated,
            'at' => $m->created_at?->toIso8601String(),
            'time' => $m->created_at?->format('h:i A'),
        ];
    }

    /** What the widget shows before anyone has said anything. */
    private function widgetSettings(): array
    {
        $s = (array) (WhatsappSetting::current()->site_chat_settings ?? []);

        return [
            'title' => $s['title'] ?? 'RazinSoft AI Support',
            'welcome' => $s['welcome'] ?? "Hi! 👋\nWelcome to RazinSoft. How can I help you today?",
            'note' => $s['note'] ?? 'We typically reply in a few minutes 💙',
            'placeholder' => $s['placeholder'] ?? 'Type your message…',
        ];
    }
}
