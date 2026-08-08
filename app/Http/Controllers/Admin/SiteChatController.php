<?php

namespace App\Http\Controllers\Admin;

use App\Events\SiteChatMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\SiteChat;
use App\Models\SiteChatMessage;
use App\Models\SiteChatOption;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Communication › Website Chat — the team's side of the widget on razinsoft.com. */
class SiteChatController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.site-chat.index', [
            'chats' => $this->chatList($request),
            'agents' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'statuses' => SiteChat::STATUSES,
            'openCount' => SiteChat::where('status', 'open')->count(),
            'unreadCount' => SiteChat::where('unread_count', '>', 0)->count(),
        ]);
    }

    /** JSON list for the live sidebar. */
    public function chats(Request $request)
    {
        return response()->json([
            'chats' => $this->chatList($request)->map(fn ($c) => $this->summary($c))->values(),
            'unread' => SiteChat::where('unread_count', '>', 0)->count(),
        ]);
    }

    public function show(Request $request, SiteChat $chat)
    {
        $chat->update(['unread_count' => 0]);
        $chat->load('assignee:id,name', 'client:id,name,email,phone,company');

        return response()->json([
            'chat' => $this->detail($chat),
            'messages' => $chat->messages()->with('agent:id,name')->get()->map(fn ($m) => $this->message($m)),
        ]);
    }

    public function reply(Request $request, SiteChat $chat)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        $message = $chat->messages()->create([
            'direction' => 'out',
            'type' => 'text',
            'body' => $data['body'],
            'agent_id' => $request->user()->id,
        ]);

        $chat->update([
            'last_message_preview' => Str::limit($data['body'], 140),
            'last_message_at' => now(),
            'status' => $chat->status === 'resolved' ? 'open' : $chat->status,
        ]);

        try {
            event(new SiteChatMessageReceived($chat->id, $message->id, 'out', $chat->token));
        } catch (\Throwable) {
        }

        return response()->json(['message' => $this->message($message->load('agent:id,name'))]);
    }

    public function status(Request $request, SiteChat $chat)
    {
        $data = $request->validate(['status' => ['required', 'in:open,pending,resolved']]);
        $chat->update(['status' => $data['status']]);

        return response()->json(['ok' => true]);
    }

    public function assign(Request $request, SiteChat $chat)
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'exists:users,id']]);
        $chat->update(['assigned_to' => $data['assigned_to'] ?: null]);

        return response()->json(['assignee' => $chat->fresh()->assignee?->name]);
    }

    // ---- the menu buttons the widget offers ----

    public function optionStore(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'reply' => ['nullable', 'string', 'max:2000'],
        ]);

        SiteChatOption::create($data + ['position' => (int) SiteChatOption::max('position') + 1]);

        return back()->with('status', 'Option added.');
    }

    public function optionUpdate(Request $request, SiteChatOption $option)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'reply' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $option->update($data + ['is_active' => (bool) ($data['is_active'] ?? false)]);

        return back()->with('status', 'Option updated.');
    }

    public function optionDestroy(SiteChatOption $option)
    {
        $option->delete();

        return back()->with('status', 'Option removed.');
    }

    // ---------------------------------------------------------------- internals

    private function chatList(Request $request)
    {
        $q = SiteChat::with('assignee:id,name')->orderByDesc('last_message_at')->orderByDesc('id');

        $status = (string) $request->query('status', 'all');
        if ($status === 'unread') {
            $q->where('unread_count', '>', 0);
        } elseif (array_key_exists($status, SiteChat::STATUSES)) {
            $q->where('status', $status);
        }

        if ($search = trim((string) $request->query('search'))) {
            $q->where(function ($x) use ($search) {
                $x->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('last_message_preview', 'like', "%{$search}%");
            });
        }

        return $q->limit(200)->get();
    }

    private function summary(SiteChat $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->displayName(),
            'initials' => $c->initials(),
            'preview' => $c->last_message_preview,
            'at' => $c->last_message_at?->diffForHumans(),
            'unread' => (int) $c->unread_count,
            'status' => $c->status,
            'assignee' => $c->assignee?->name,
        ];
    }

    private function detail(SiteChat $c): array
    {
        return $this->summary($c) + [
            'email' => $c->email,
            'phone' => $c->phone,
            'page_url' => $c->page_url,
            'referrer' => $c->referrer,
            'assigned_to' => $c->assigned_to,
            'started' => $c->created_at?->format('d M Y, h:i A'),
            'client' => $c->client ? ['name' => $c->client->name, 'email' => $c->client->email] : null,
        ];
    }

    private function message(SiteChatMessage $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'type' => $m->type,
            'body' => $m->body,
            'attachment' => $m->attachmentUrl(),
            'agent' => $m->agent?->name,
            'ai' => (bool) $m->ai_generated,
            'time' => $m->created_at?->format('h:i A'),
            'day' => $m->created_at?->format('d M Y'),
        ];
    }
}
