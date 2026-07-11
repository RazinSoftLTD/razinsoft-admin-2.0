<?php

namespace App\Http\Controllers\Admin;

use App\Events\ChatMessagePosted;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /** Full chat workspace; optionally with one conversation open. */
    public function index(?Conversation $conversation = null)
    {
        $me = auth()->user();

        // My conversations, most-recently-active first.
        $conversations = $me->conversations()
            ->with(['members', 'latestMessage'])
            ->orderByRaw('COALESCE(last_message_at, conversations.created_at) DESC')
            ->get();

        // Everyone I could start a direct message with (staff + admins, minus me).
        $people = User::assignable()->where('id', '!=', $me->id)
            ->orderBy('name')->get(['id', 'name', 'photo', 'designation_id', 'last_seen_at'])
            ->load('designation');

        $active = null;
        $messages = collect();
        if ($conversation && $conversation->exists) {
            abort_unless($conversation->members->contains($me->id), 403);
            $active = $conversation;
            $messages = $conversation->messages()->with('author')->orderBy('id')->get();
            $this->markRead($conversation, $me);
        }

        return view('admin.chat.index', compact('conversations', 'people', 'active', 'messages'));
    }

    public function show(Request $request, Conversation $conversation)
    {
        if ($this->wantsPartial($request)) {
            return $this->threadPartial($conversation);
        }

        return $this->index($conversation);
    }

    /** Open (or lazily create) the 1:1 conversation between me and another user. */
    public function direct(Request $request, User $user)
    {
        $me = auth()->user();
        abort_if($user->id === $me->id, 400);
        abort_unless($user->isPanelUser(), 404);

        $conversation = $this->findDirect($me->id, $user->id)
            ?? tap(Conversation::create(['type' => 'direct', 'created_by' => $me->id]), function ($c) use ($me, $user) {
                $c->members()->attach([$me->id, $user->id]);
            });

        if ($this->wantsPartial($request)) {
            return $this->threadPartial($conversation);
        }

        return redirect()->route('admin.chat.show', $conversation);
    }

    private function wantsPartial(Request $request): bool
    {
        return $request->boolean('partial') || $request->hasHeader('X-Chat-Partial');
    }

    /** Just the right-pane thread markup — swapped in via AJAX for smooth navigation. */
    private function threadPartial(Conversation $conversation)
    {
        $me = auth()->user();
        abort_unless($conversation->members->contains($me->id), 403);

        $active = $conversation->load('members');
        $messages = $conversation->messages()->with('author')->orderBy('id')->get();
        $this->markRead($conversation, $me);

        return view('admin.chat._thread', compact('active', 'messages', 'me'));
    }

    public function createGroup()
    {
        $people = User::assignable()->where('id', '!=', auth()->id())
            ->orderBy('name')->get(['id', 'name', 'photo', 'designation_id'])->load('designation');

        return view('admin.chat.group-form', compact('people'));
    }

    public function storeGroup(Request $request)
    {
        $me = auth()->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);

        $group = Conversation::create([
            'type' => 'group',
            'name' => $data['name'],
            'slug' => Conversation::uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'created_by' => $me->id,
        ]);

        // Only real panel users, plus the creator (as manager).
        $memberIds = User::assignable()->whereIn('id', $data['members'] ?? [])->pluck('id')->all();
        $attach = [];
        foreach ($memberIds as $id) {
            $attach[$id] = ['is_manager' => false];
        }
        $attach[$me->id] = ['is_manager' => true];
        $group->members()->attach($attach);

        return redirect()->route('admin.chat.show', $group)->with('status', 'Group created.');
    }

    /** Channel settings page — managers & admins only. */
    public function editGroup(Conversation $conversation)
    {
        abort_unless($conversation->isGroup(), 404);
        $conversation->load('members');
        abort_unless($conversation->isManagedBy(auth()->user()), 403);

        $people = User::assignable()->orderBy('name')
            ->get(['id', 'name', 'photo', 'designation_id'])->load('designation');

        return view('admin.chat.group-settings', compact('conversation', 'people'));
    }

    /** Save channel name/slug/photo/description and add/remove members. */
    public function updateGroup(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->isGroup(), 404);
        $conversation->load('members');
        $me = auth()->user();
        abort_unless($conversation->isManagedBy($me), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);

        // Name / slug / description
        $conversation->name = $data['name'];
        $conversation->description = $data['description'] ?? null;
        $slugSource = ! empty($data['slug']) ? $data['slug'] : $data['name'];
        $conversation->slug = Conversation::uniqueSlug($slugSource, $conversation->id);

        // Photo
        if ($request->hasFile('photo')) {
            if ($conversation->photo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($conversation->photo);
            }
            $conversation->photo = $request->file('photo')->store('chat/avatars', 'public');
        }
        $conversation->save();

        // Members: add newly-checked, remove unchecked — but never drop managers.
        $selected = User::assignable()->whereIn('id', $data['members'] ?? [])->pluck('id')->all();
        $current = $conversation->members->pluck('id')->all();
        $managers = $conversation->members->where('pivot.is_manager', true)->pluck('id')->all();

        $toAdd = array_diff($selected, $current);
        $toRemove = array_diff(array_diff($current, $selected), $managers);

        if ($toAdd) {
            $conversation->members()->attach(
                collect($toAdd)->mapWithKeys(fn ($id) => [$id => ['is_manager' => false]])->all()
            );
        }
        if ($toRemove) {
            $conversation->members()->detach(array_values($toRemove));
        }

        return redirect()->route('admin.chat.show', $conversation)->with('status', 'Channel updated.');
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        $me = auth()->user();
        abort_unless($conversation->members->contains($me->id), 403);

        $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,csv'],
        ]);

        // Sanitize the rich-text HTML (bold/italic/links/highlight) before it's stored & shown to others.
        $body = trim((string) $request->input('body'));
        $body = $body !== '' ? clean($body) : null;

        $path = $name = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $name = $file->getClientOriginalName();
            $path = $file->store('chat', 'public');
        }

        // Need either text or a file.
        if (($body === null || strip_tags($body) === '' && ! str_contains($body, '<img')) && ! $path) {
            return $request->wantsJson()
                ? response()->json(['error' => 'Message is empty.'], 422)
                : back();
        }

        $message = $conversation->messages()->create([
            'user_id' => $me->id,
            'body' => $body,
            'attachment' => $path,
            'attachment_name' => $name,
        ]);
        $conversation->update(['last_message_at' => $message->created_at]);
        $this->markRead($conversation, $me);

        $memberIds = $conversation->members->pluck('id')->all();
        broadcast(new ChatMessagePosted($message, $memberIds))->toOthers();

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $message->id,
                'body' => $message->body,
                'attachment' => $message->attachment_url,
                'attachment_name' => $message->attachment_name,
                'is_image' => $message->is_image,
                'time' => $message->created_at->format('g:i A'),
            ]);
        }

        return redirect()->route('admin.chat.show', $conversation);
    }

    /** Broadcast a transient "typing…" ping to the other members (nothing stored). */
    public function typing(Request $request, Conversation $conversation)
    {
        $me = auth()->user();
        abort_unless($conversation->members->contains($me->id), 403);

        broadcast(new \App\Events\ChatTyping($conversation->id, $me->id, $me->name))->toOthers();

        return response()->json(['ok' => true]);
    }

    /** Mark a conversation fully read (on open, and as messages arrive while it's open). */
    public function read(Conversation $conversation)
    {
        $me = auth()->user();
        abort_unless($conversation->members->contains($me->id), 403);
        $this->markRead($conversation, $me);

        return response()->json(['ok' => true]);
    }

    /** Delete a message — its author or an admin only. Removes any attached file. */
    public function destroyMessage(ChatMessage $message)
    {
        $me = auth()->user();
        abort_unless($message->user_id === $me->id || $me->isAdmin(), 403);

        if ($message->attachment) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($message->attachment);
        }
        $conversationId = $message->conversation_id;
        $message->delete();

        broadcast(new \App\Events\ChatMessageDeleted($conversationId, $message->id));

        return response()->json(['ok' => true]);
    }

    /** Keep the current user's presence fresh and report who else is online. */
    public function heartbeat()
    {
        $me = auth()->user();
        $me->forceFill(['last_seen_at' => now()])->saveQuietly();

        $online = User::assignable()
            ->where('last_seen_at', '>', now()->subSeconds(User::ONLINE_WINDOW_SECONDS))
            ->pluck('id');

        return response()->json(['online' => $online]);
    }

    /** Mark the user offline the moment they close the tab/browser (sendBeacon on pagehide). */
    public function offline()
    {
        if ($me = auth()->user()) {
            $me->forceFill(['last_seen_at' => null])->saveQuietly();
        }

        return response()->noContent();
    }

    /** Recent conversations with unread messages — powers the top-bar bell dropdown. */
    public static function recentUnread(User $me, int $limit = 8): array
    {
        $rows = $me->conversations()
            ->with(['members', 'latestMessage.author'])
            ->orderByRaw('COALESCE(last_message_at, conversations.created_at) DESC')
            ->get();

        $out = [];
        foreach ($rows as $c) {
            $unread = $c->unreadCountFor($me);
            if ($unread < 1 || ! $c->latestMessage) {
                continue;
            }
            $out[] = [
                'id' => $c->id,
                'title' => $c->titleFor($me),
                'is_group' => $c->isGroup(),
                'author' => $c->latestMessage->author->name ?? '',
                'avatar' => $c->isGroup() ? null : optional($c->counterpart($me))->photo_url,
                'preview' => $c->latestMessage->preview,
                'time' => optional($c->latestMessage->created_at)->diffForHumans(null, true),
                'unread' => $unread,
                'url' => route('admin.chat.show', $c),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** Total unread across all my conversations — for the sidebar badge. */
    public static function unreadTotal(User $me): int
    {
        return (int) DB::table('chat_messages as m')
            ->join('conversation_user as cu', 'cu.conversation_id', '=', 'm.conversation_id')
            ->where('cu.user_id', $me->id)
            ->where('m.user_id', '!=', $me->id)
            ->where(function ($q) {
                $q->whereNull('cu.last_read_at')->orWhereColumn('m.created_at', '>', 'cu.last_read_at');
            })
            ->count();
    }

    private function markRead(Conversation $conversation, User $me): void
    {
        $conversation->members()->updateExistingPivot($me->id, ['last_read_at' => now()]);
    }

    /** Existing direct conversation shared by exactly these two users, if any. */
    private function findDirect(int $a, int $b): ?Conversation
    {
        return Conversation::where('type', 'direct')
            ->whereHas('members', fn ($q) => $q->where('users.id', $a))
            ->whereHas('members', fn ($q) => $q->where('users.id', $b))
            ->first();
    }
}
