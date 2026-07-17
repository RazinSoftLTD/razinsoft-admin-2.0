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
        return response()->json(['chats' => $this->chatList($request)->map(fn ($c) => $this->chatSummary($c))->values()]);
    }

    /** JSON thread for one chat + mark read. */
    public function show(Request $request, WhatsappChat $chat)
    {
        $chat->update(['unread_count' => 0]);
        $chat->load(['labels:id,name,color', 'assignee:id,name', 'client:id,name,email,phone,company', 'notes.user:id,name']);

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
        if ($label = $request->query('label')) {
            $q->whereHas('labels', fn ($l) => $l->where('whatsapp_labels.id', $label));
        }
        if ($search = $request->query('search')) {
            $q->where(fn ($x) => $x->where('name', 'like', "%{$search}%")->orWhere('profile_name', 'like', "%{$search}%")->orWhere('wa_id', 'like', "%{$search}%"));
        }

        return $q->orderByDesc('last_message_at')->orderByDesc('id')->limit(200)->get();
    }

    private function chatSummary(WhatsappChat $c): array
    {
        return [
            'id' => $c->id, 'name' => $c->displayName(), 'wa_id' => $c->wa_id,
            'preview' => $c->last_message_preview, 'at' => $c->last_message_at?->diffForHumans(),
            'unread' => $c->unread_count, 'status' => $c->status,
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
            'client' => $c->client ? ['name' => $c->client->name, 'email' => $c->client->email, 'phone' => $c->client->phone, 'company' => $c->client->company] : null,
            'notes' => $c->notes->map(fn ($n) => ['id' => $n->id, 'body' => $n->body, 'user' => $n->user?->name, 'at' => $n->created_at->diffForHumans()]),
        ]);
    }

    private function messagePayload(WhatsappMessage $m): array
    {
        return [
            'id' => $m->id, 'direction' => $m->direction, 'type' => $m->type,
            'body' => $m->body, 'media' => $m->mediaUrl(), 'media_mime' => $m->media_mime, 'media_name' => $m->media_name,
            'status' => $m->status, 'agent' => $m->agent?->name,
            'at' => ($m->sent_at ?? $m->created_at)->format('d M, h:i A'),
        ];
    }
}
