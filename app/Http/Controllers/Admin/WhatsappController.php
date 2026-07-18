<?php

namespace App\Http\Controllers\Admin;

use App\Events\WhatsappMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WhatsappChat;
use App\Models\WhatsappLabel;
use App\Models\WhatsappMessage;
use App\Models\WhatsappQuickReply;
use App\Models\WhatsappSetting;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin › Messenger › WhatsApp — the inbox. */
class WhatsappController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.whatsapp.index', [
            'chats' => $this->chatList($request),
            'labels' => WhatsappLabel::orderBy('position')->get(),
            'agents' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'quickReplies' => WhatsappQuickReply::orderBy('shortcut')->get(),
            'settings' => WhatsappSetting::current(),
            'interestOptions' => $this->interestOptions(),
            'leadQualities' => WhatsappChat::LEAD_QUALITIES,
            'stats' => [
                'open' => WhatsappChat::where('status', 'open')->count(),
                'unread' => WhatsappChat::where('unread_count', '>', 0)->count(),
                'mine' => WhatsappChat::where('assigned_to', $request->user()->id)->where('status', '!=', 'resolved')->count(),
            ],
        ]);
    }

    /** JSON chat list (used by the live filter/search sidebar). */
    public function chats(Request $request)
    {
        return response()->json([
            'chats' => $this->chatList($request)->map(fn ($c) => $this->chatSummary($c))->values(),
            'unread' => WhatsappChat::where('unread_count', '>', 0)->count(),
        ]);
    }

    /** JSON thread for one chat + mark read. */
    public function show(Request $request, WhatsappChat $chat)
    {
        $chat->update(['unread_count' => 0]);
        $chat->load(['labels:id,name,color', 'assignee:id,name', 'client:id,name,email,phone,company', 'notes.user:id,name']);

        // Tell WhatsApp we've seen the incoming messages (blue ticks on the sender's side).
        try {
            app(WhatsappService::class)->markRead($chat->wa_id);
        } catch (\Throwable) {
        }

        return response()->json([
            'chat' => $this->chatDetail($chat),
            'messages' => $chat->messages()->with('agent:id,name')->get()->map(fn ($m) => $this->messagePayload($m)),
        ]);
    }

    public function send(Request $request, WhatsappChat $chat)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4096']]);
        $settings = WhatsappSetting::current();
        if (! $settings->isConfigured()) {
            return response()->json(['error' => 'WhatsApp is not connected. Configure it in Settings › WhatsApp API.'], 422);
        }

        $message = $chat->messages()->create([
            'direction' => 'out', 'type' => 'text', 'body' => $data['body'],
            'status' => 'sent', 'agent_id' => $request->user()->id, 'sent_at' => now(),
        ]);

        try {
            $waId = app(WhatsappService::class)->sendText($chat->wa_id, $data['body']);
            $message->update(['wa_message_id' => $waId]);
        } catch (\Throwable $e) {
            $message->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        $chat->update([
            'last_message_at' => now(),
            'last_message_preview' => \Illuminate\Support\Str::limit($data['body'], 120),
            'status' => $chat->status === 'resolved' ? 'open' : $chat->status,
        ]);
        try {
            event(new WhatsappMessageReceived($chat->id, $message->id, 'out'));
        } catch (\Throwable) {
        }

        return response()->json(['message' => $this->messagePayload($message->load('agent:id,name'))]);
    }

    /** Send an attachment (image / video / audio / document) to the chat. */
    public function sendMediaMessage(Request $request, WhatsappChat $chat)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:16384'], // 16 MB — WhatsApp's practical ceiling
            'caption' => ['nullable', 'string', 'max:1024'],
        ]);
        $settings = WhatsappSetting::current();
        if (! $settings->isConfigured()) {
            return response()->json(['error' => 'WhatsApp is not connected. Configure it in Settings › WhatsApp Config.'], 422);
        }

        $file = $request->file('file');
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $type = str_starts_with($mime, 'image/') ? 'image'
            : (str_starts_with($mime, 'video/') ? 'video'
            : (str_starts_with($mime, 'audio/') ? 'audio' : 'document'));

        $path = $file->store('whatsapp', 'public');
        $caption = $request->input('caption');
        $filename = $file->getClientOriginalName();

        $message = $chat->messages()->create([
            'direction' => 'out', 'type' => $type, 'body' => $caption,
            'media_path' => $path, 'media_mime' => $mime, 'media_name' => $filename,
            'status' => 'sent', 'agent_id' => $request->user()->id, 'sent_at' => now(),
        ]);

        try {
            // The gateway fetches this public URL to attach the file.
            $waId = app(WhatsappService::class)->sendMedia($chat->wa_id, $type, asset('storage/'.$path), $caption, $filename);
            $message->update(['wa_message_id' => $waId]);
        } catch (\Throwable $e) {
            $message->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        $chat->update([
            'last_message_at' => now(),
            'last_message_preview' => \Illuminate\Support\Str::limit($caption ?: ucfirst($type), 120),
            'status' => $chat->status === 'resolved' ? 'open' : $chat->status,
        ]);
        try {
            event(new WhatsappMessageReceived($chat->id, $message->id, 'out'));
        } catch (\Throwable) {
        }

        return response()->json(['message' => $this->messagePayload($message->load('agent:id,name'))]);
    }

    /** Edit one of our own outgoing text messages (WhatsApp allows this within ~15 minutes). */
    public function editMessage(Request $request, WhatsappChat $chat, WhatsappMessage $message)
    {
        abort_unless($message->chat_id === $chat->id, 404);
        if ($message->direction !== 'out' || $message->type !== 'text' || $message->deleted_at) {
            return response()->json(['error' => 'Only your own text messages can be edited.'], 422);
        }
        $data = $request->validate(['body' => ['required', 'string', 'max:4096']]);

        $sentAt = $message->sent_at ?? $message->created_at;
        if ($sentAt->lt(now()->subMinutes(15))) {
            return response()->json(['error' => 'WhatsApp only allows editing within 15 minutes of sending.'], 422);
        }
        if (! $message->wa_message_id) {
            return response()->json(['error' => 'This message can no longer be edited.'], 422);
        }

        try {
            app(WhatsappService::class)->editText($chat->wa_id, $message->wa_message_id, $data['body']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $message->update(['body' => $data['body'], 'edited_at' => now()]);
        if ($chat->messages()->max('id') === $message->id) {
            $chat->update(['last_message_preview' => \Illuminate\Support\Str::limit($data['body'], 120)]);
        }

        return response()->json(['message' => $this->messagePayload($message->load('agent:id,name'))]);
    }

    /** Delete one of our own outgoing messages for everyone. */
    public function deleteMessage(Request $request, WhatsappChat $chat, WhatsappMessage $message)
    {
        abort_unless($message->chat_id === $chat->id, 404);
        if ($message->direction !== 'out') {
            return response()->json(['error' => 'You can only delete your own messages.'], 422);
        }

        if ($message->wa_message_id) {
            try {
                app(WhatsappService::class)->deleteMessage($chat->wa_id, $message->wa_message_id);
            } catch (\Throwable $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        $message->update(['deleted_at' => now(), 'body' => null, 'media_path' => null]);
        if ($chat->messages()->max('id') === $message->id) {
            $chat->update(['last_message_preview' => 'You deleted this message']);
        }

        return response()->json(['message' => $this->messagePayload($message->load('agent:id,name'))]);
    }

    /** React to a message with an emoji (empty removes the reaction). */
    public function reactMessage(Request $request, WhatsappChat $chat, WhatsappMessage $message)
    {
        abort_unless($message->chat_id === $chat->id, 404);
        if ($message->deleted_at) {
            return response()->json(['error' => 'This message is no longer available.'], 422);
        }
        $data = $request->validate(['emoji' => ['nullable', 'string', 'max:16']]);
        $emoji = $data['emoji'] ?? '';

        if ($message->wa_message_id) {
            try {
                app(WhatsappService::class)->sendReaction($chat->wa_id, $message->wa_message_id, $emoji, $message->direction === 'out');
            } catch (\Throwable $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        $message->update(['reaction' => $emoji ?: null]);

        return response()->json(['message' => $this->messagePayload($message->load('agent:id,name'))]);
    }

    public function assign(Request $request, WhatsappChat $chat)
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'exists:users,id']]);
        $chat->update(['assigned_to' => $data['assigned_to'] ?? null]);

        return response()->json(['ok' => true]);
    }

    public function status(Request $request, WhatsappChat $chat)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(WhatsappChat::STATUSES))]]);
        $chat->update(['status' => $data['status']]);

        return response()->json(['ok' => true]);
    }

    /** Flag a chat as unread again (so it stays highlighted until reopened). */
    public function markUnread(Request $request, WhatsappChat $chat)
    {
        $chat->update(['unread_count' => max(1, $chat->unread_count)]);

        return response()->json(['ok' => true, 'unread' => $chat->unread_count]);
    }

    public function toggleLabel(Request $request, WhatsappChat $chat)
    {
        $data = $request->validate(['label_id' => ['required', 'exists:whatsapp_labels,id']]);
        $chat->labels()->toggle($data['label_id']);

        return response()->json(['labels' => $chat->labels()->get(['id', 'name', 'color'])]);
    }

    public function addNote(Request $request, WhatsappChat $chat)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $note = $chat->notes()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return response()->json(['note' => ['id' => $note->id, 'body' => $note->body, 'user' => $request->user()->name, 'at' => $note->created_at->diffForHumans()]]);
    }

    /** Update the CRM-ish contact fields: manual phone, lead quality, interested product. */
    public function updateDetails(Request $request, WhatsappChat $chat)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
            'lead_quality' => ['nullable', Rule::in(array_keys(WhatsappChat::LEAD_QUALITIES))],
            'interested_product' => ['nullable', 'string', 'max:191'],
        ]);

        // Normalise a manually entered number to digits (keep it E.164-ish, no spaces/dashes).
        $phone = isset($data['phone']) ? preg_replace('/[^\d]/', '', $data['phone']) : null;

        $chat->update([
            'name' => filled($data['name'] ?? null) ? $data['name'] : null,
            'phone' => $phone ?: ($chat->isGroup() ? null : $chat->phone),
            'lead_quality' => $data['lead_quality'] ?? null,
            'interested_product' => $data['interested_product'] ?? null,
        ]);

        return response()->json([
            'name' => $chat->displayName(),
            'raw_name' => $chat->name,
            'initials' => collect(explode(' ', $chat->displayName()))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join(''),
            'phone' => $chat->realNumber() ? '+'.$chat->realNumber() : null,
            'country' => $chat->country(),
            'lead_quality' => $chat->lead_quality,
            'interested_product' => $chat->interested_product,
        ]);
    }

    /** Upload / replace the contact avatar. */
    public function updateAvatar(Request $request, WhatsappChat $chat)
    {
        $request->validate(['avatar' => ['required', 'image', 'max:4096']]);

        // Remove the previous file so we don't leave orphans.
        if ($chat->avatar_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($chat->avatar_path);
        }
        $path = $request->file('avatar')->store('whatsapp/avatars', 'public');
        $chat->update(['avatar_path' => $path]);

        return response()->json(['avatar' => $chat->avatarUrl()]);
    }

    /** A stable, distinct colour per group so different groups look different. */
    private function groupColor(int $id): string
    {
        $palette = ['#6366f1', '#ec4899', '#f59e0b', '#0ea5e9', '#8b5cf6', '#ef4444', '#14b8a6', '#f97316', '#3b82f6', '#d946ef'];

        return $palette[$id % count($palette)];
    }

    /** Product names (live) plus any custom options an admin added in WhatsApp settings. */
    private function interestOptions(): array
    {
        $products = \App\Models\Product::query()->orderBy('name')->pluck('name')->all();
        $custom = WhatsappSetting::current()->interest_options ?: [];

        return collect($products)->merge($custom)->filter()->unique()->values()->all();
    }

    // ---------------------------------------------------------------- internals

    private function chatList(Request $request)
    {
        $q = WhatsappChat::query()->with('labels:id,name,color', 'assignee:id,name');

        if (($status = $request->query('status')) && $status !== 'all') {
            $status === 'unread' ? $q->where('unread_count', '>', 0) : $q->where('status', $status);
        }
        if ($request->query('mine')) {
            $q->where('assigned_to', $request->user()->id);
        }
        if (($type = $request->query('type')) && in_array($type, ['single', 'group'], true)) {
            $type === 'group' ? $q->where('chat_type', 'group') : $q->where('chat_type', '!=', 'group');
        }
        if ($label = $request->query('label')) {
            $q->whereHas('labels', fn ($l) => $l->where('whatsapp_labels.id', $label));
        }
        if ($search = trim((string) $request->query('search'))) {
            $digits = preg_replace('/\D/', '', $search); // for phone-number matching
            $q->where(function ($x) use ($search, $digits) {
                $x->where('name', 'like', "%{$search}%")
                    ->orWhere('profile_name', 'like', "%{$search}%")
                    ->orWhere('wa_id', 'like', "%{$search}%")
                    ->orWhere('last_message_preview', 'like', "%{$search}%")
                    ->orWhere('interested_product', 'like', "%{$search}%")
                    // Match anything the client actually wrote in the conversation.
                    ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', "%{$search}%"));
                if ($digits !== '') {
                    $x->orWhere('phone', 'like', "%{$digits}%");
                }
            });
        }

        return $q->orderByDesc('last_message_at')->orderByDesc('id')->limit(200)->get();
    }

    private function chatSummary(WhatsappChat $c): array
    {
        return [
            'id' => $c->id, 'name' => $c->displayName(), 'wa_id' => $c->phoneLabel(),
            'preview' => $c->last_message_preview, 'at' => $c->last_message_at?->diffForHumans(),
            'unread' => $c->unread_count, 'status' => $c->status,
            'is_group' => $c->isGroup(),
            'color' => $c->isGroup() ? $this->groupColor($c->id) : null,
            'avatar' => $c->avatarUrl(),
            'assignee' => $c->assignee?->name,
            'labels' => $c->labels->map(fn ($l) => ['name' => $l->name, 'color' => $l->color]),
            'initials' => collect(explode(' ', $c->displayName()))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join(''),
        ];
    }

    private function chatDetail(WhatsappChat $c): array
    {
        return array_merge($this->chatSummary($c), [
            'assigned_to' => $c->assigned_to,
            'label_ids' => $c->labels->pluck('id'),
            'phone' => $c->realNumber() ? '+'.$c->realNumber() : null,
            'country' => $c->country(),
            'last_seen' => $c->last_message_at?->diffForHumans(),
            'lead_quality' => $c->lead_quality,
            'interested_product' => $c->interested_product,
            'raw_name' => $c->name,
            'client' => $c->client ? ['name' => $c->client->name, 'email' => $c->client->email, 'phone' => $c->client->phone, 'company' => $c->client->company] : null,
            'notes' => $c->notes->map(fn ($n) => ['id' => $n->id, 'body' => $n->body, 'user' => $n->user?->name, 'at' => $n->created_at->diffForHumans()]),
        ]);
    }

    private function messagePayload(WhatsappMessage $m): array
    {
        $at = $m->sent_at ?? $m->created_at;

        return [
            'id' => $m->id, 'direction' => $m->direction, 'type' => $m->type,
            'sender_name' => $m->sender_name,
            'body' => $m->body, 'media' => $m->mediaUrl(), 'media_mime' => $m->media_mime, 'media_name' => $m->media_name,
            'status' => $m->status, 'agent' => $m->agent?->name,
            'edited' => (bool) $m->edited_at,
            'deleted' => (bool) $m->deleted_at,
            'reaction' => $m->reaction,
            'at' => $at->format('h:i A'),
            'date_key' => $at->toDateString(),
            'day' => $at->isToday() ? 'Today' : ($at->isYesterday() ? 'Yesterday' : $at->format('d F Y')),
        ];
    }
}
