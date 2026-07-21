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
        $canClients = $me->allows('chat', 'clients');
        $tab = request('tab') === 'client' && $canClients ? 'client' : 'team';

        // My team conversations (staff DMs + groups), most-recently-active first.
        $conversations = $me->conversations()
            ->whereIn('type', ['direct', 'group'])
            ->with(['members', 'latestMessage'])
            ->orderByRaw('COALESCE(last_message_at, conversations.created_at) DESC')
            ->get();

        // Client (customer) conversations — a shared inbox for staff who hold `chat.clients`.
        $clientConversations = $canClients
            ? Conversation::where('type', 'client')->with(['members', 'latestMessage'])
                ->orderByRaw('COALESCE(last_message_at, conversations.created_at) DESC')->get()
            : collect();

        // Everyone I could start a direct message with (staff + admins, minus me).
        $people = User::assignable()->where('id', '!=', $me->id)
            ->orderBy('name')->get(['id', 'name', 'photo', 'designation_id', 'last_seen_at'])
            ->load('designation');

        $active = null;
        $messages = collect();
        $hasMore = false;
        if ($conversation && $conversation->exists) {
            abort_unless($this->canAccess($me, $conversation), 403);
            // First staff to open a client conversation joins it (so read/unread tracking works).
            if ($conversation->type === 'client' && ! $conversation->members->contains($me->id)) {
                $conversation->members()->attach($me->id);
                $conversation->load('members');
            }
            $active = $conversation;
            $tab = $conversation->type === 'client' ? 'client' : 'team';
            [$messages, $hasMore] = $this->recentMessages($conversation);
            $this->markRead($conversation, $me);
        } else {
            // No conversation in the URL → open the one with the most recent message
            // (so a fresh load / refresh lands on the latest chat, like WhatsApp).
            $default = $conversations->concat($clientConversations)
                ->filter(fn ($c) => $c->last_message_at !== null)
                ->sortByDesc(fn ($c) => $c->last_message_at->getTimestamp())
                ->first();
            if ($default) {
                if ($default->type === 'client' && ! $default->members->contains($me->id)) {
                    $default->members()->attach($me->id);
                    $default->load('members');
                }
                $active = $default;
                $tab = $default->type === 'client' ? 'client' : 'team';
                [$messages, $hasMore] = $this->recentMessages($default);
                $this->markRead($default, $me);
            }
        }

        return view('admin.chat.index', compact('conversations', 'clientConversations', 'people', 'active', 'messages', 'hasMore', 'tab', 'canClients'));
    }

    /** The most recent $limit messages (chronological) + whether older ones exist. */
    private function recentMessages(Conversation $conversation, int $limit = 40): array
    {
        $recent = $conversation->messages()->with('author', 'replyTo.author')->orderByDesc('id')->limit($limit + 1)->get();
        $hasMore = $recent->count() > $limit;

        return [$recent->take($limit)->sortBy('id')->values(), $hasMore];
    }

    /** Older messages before a given id — powers the "Load earlier messages" button. */
    public function olderMessages(Request $request, Conversation $conversation)
    {
        $me = auth()->user();
        abort_unless($this->canAccess($me, $conversation), 403);

        $beforeId = (int) $request->query('before_id');
        $limit = 40;
        $older = $conversation->messages()->with('author', 'replyTo.author')->where('id', '<', $beforeId)
            ->orderByDesc('id')->limit($limit + 1)->get();
        $hasMore = $older->count() > $limit;
        $messages = $older->take($limit)->sortBy('id')->values();

        return response()->json([
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->id, 'user_id' => $m->user_id,
                'author' => $m->author->name ?? '—', 'author_photo' => $m->author->photo_url ?? null,
                'body' => $m->body, 'attachment' => $m->attachment_url, 'attachment_name' => $m->attachment_name,
                'is_image' => $m->is_image, 'time' => $m->created_at->format('g:i A'),
                'created_at' => $m->created_at->timestamp, 'edited' => (bool) $m->edited_at,
                'quoted' => $m->quoted(), 'reactions' => (object) $m->reactionMap(),
            ]),
            'has_more' => $hasMore,
        ]);
    }

    /** Team conversations need membership; client conversations need the `chat.clients` permission. */
    private function canAccess(User $me, Conversation $conversation): bool
    {
        if ($conversation->type === 'client') {
            return $me->allows('chat', 'clients');
        }

        return $conversation->members->contains($me->id);
    }

    public function show(Request $request, Conversation $conversation)
    {
        // AJAX thread-swap → just the right pane (only the thread changes, no page flash); else full page.
        if ($request->hasHeader('X-Chat-Partial')) {
            return $this->pane($conversation);
        }

        return $this->index($conversation);
    }

    /** Open (or lazily create) the 1:1 conversation between me and another user, then show it. */
    public function direct(Request $request, User $user)
    {
        $me = auth()->user();
        abort_if($user->id === $me->id, 400);
        abort_unless($user->isPanelUser(), 404);

        $conversation = $this->findDirect($me->id, $user->id)
            ?? tap(Conversation::create(['type' => 'direct', 'created_by' => $me->id]), function ($c) use ($me, $user) {
                $c->members()->attach([$me->id, $user->id]);
            });

        if ($request->hasHeader('X-Chat-Partial')) {
            return $this->pane($conversation);
        }

        return redirect()->route('admin.chat.show', $conversation);
    }

    /** The right-pane thread only — fetched and swapped in on conversation switch (no full-page render). */
    private function pane(Conversation $conversation)
    {
        $me = auth()->user();
        abort_unless($this->canAccess($me, $conversation), 403);
        if ($conversation->type === 'client' && ! $conversation->members->contains($me->id)) {
            $conversation->members()->attach($me->id);
        }

        $active = $conversation->load('members');
        [$messages, $hasMore] = $this->recentMessages($conversation);
        $this->markRead($conversation, $me);

        return view('admin.chat._pane', compact('active', 'messages', 'hasMore', 'me'));
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

    /**
     * Broadcast an event without ever letting a transport failure (Reverb down,
     * wrong host, capacity) turn into a 500. Realtime is best-effort — the message
     * is already persisted, so the sender must still get a clean success response.
     * The PendingBroadcast temporary is destroyed at the `;` inside the closure, so
     * its dispatch (and any exception) happens within this try/catch.
     */
    private function broadcastSafe(\Closure $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Tick / untick a shared checklist item on a message (anyone in the conversation). */
    public function toggleChecklist(Request $request, ChatMessage $message)
    {
        $conversation = $message->conversation;
        abort_unless($this->canAccess(auth()->user(), $conversation), 403);

        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'checked' => ['required', 'boolean'],
        ]);

        $list = $message->checklist ?? [];
        if (! array_key_exists($data['index'], $list)) {
            abort(404);
        }
        $list[$data['index']]['checked'] = $request->boolean('checked');
        $message->update(['checklist' => $list]);

        $this->broadcastSafe(fn () => broadcast(new \App\Events\ChatChecklistToggled($message->conversation_id, $message->id, $data['index'], $request->boolean('checked')))->toOthers());

        return response()->json(['ok' => true]);
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        $me = auth()->user();
        abort_unless($this->canAccess($me, $conversation), 403);
        // A staff replying to a client for the first time joins the conversation.
        if ($conversation->type === 'client' && ! $conversation->members->contains($me->id)) {
            $conversation->members()->attach($me->id);
            $conversation->load('members');
        }

        $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,csv'],
            'reply_to_id' => ['nullable', 'integer'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*' => ['string', 'max:500'],
            'checklist_title' => ['nullable', 'string', 'max:200'],
        ]);

        // Shared to-do items — each starts unchecked.
        $checklist = collect((array) $request->input('checklist', []))
            ->map(fn ($t) => trim((string) $t))->filter()->take(50)
            ->map(fn ($t) => ['text' => mb_substr($t, 0, 500), 'checked' => false])->values()->all();
        $checklist = $checklist ?: null;
        // Optional heading for the checklist (only meaningful when there are items).
        $checklistTitle = $checklist ? (trim((string) $request->input('checklist_title')) ?: null) : null;

        // A reply must point at a message that lives in THIS conversation.
        $replyToId = null;
        if ($request->filled('reply_to_id')) {
            $replyToId = ChatMessage::where('id', $request->input('reply_to_id'))
                ->where('conversation_id', $conversation->id)->value('id');
        }

        // Sanitize the rich-text HTML (bold/italic/links/highlight) before it's stored & shown to others.
        $body = trim((string) $request->input('body'));
        $body = $body !== '' ? \App\Support\Html::clean($body) : null;

        $path = $name = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $name = $file->getClientOriginalName();
            $path = $file->store('chat', 'public');
        }

        // Need either text or a file.
        if (($body === null || strip_tags($body) === '' && ! str_contains($body, '<img')) && ! $path && ! $checklist) {
            return $request->wantsJson()
                ? response()->json(['error' => 'Message is empty.'], 422)
                : back();
        }

        $message = $conversation->messages()->create([
            'user_id' => $me->id,
            'reply_to_id' => $replyToId,
            'body' => $body,
            'checklist' => $checklist,
            'checklist_title' => $checklistTitle,
            'attachment' => $path,
            'attachment_name' => $name,
        ]);
        $conversation->update(['last_message_at' => $message->created_at]);
        $this->markRead($conversation, $me);

        $memberIds = $conversation->members->pluck('id')->all();
        $this->broadcastSafe(fn () => broadcast(new ChatMessagePosted($message, $memberIds))->toOthers());

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $message->id,
                'body' => $message->body,
                'attachment' => $message->attachment_url,
                'attachment_name' => $message->attachment_name,
                'is_image' => $message->is_image,
                'checklist' => $message->checklist,
                'checklist_title' => $message->checklist_title,
                'created_at' => $message->created_at->timestamp,
                'time' => $message->created_at->format('g:i A'),
                'quoted' => $message->quoted(),
                'reactions' => (object) $message->reactionMap(),
            ]);
        }

        return redirect()->route('admin.chat.show', $conversation);
    }

    /** Broadcast a transient "typing…" ping to the other members (nothing stored). */
    public function typing(Request $request, Conversation $conversation)
    {
        $me = auth()->user();
        abort_unless($this->canAccess($me, $conversation), 403);

        $this->broadcastSafe(fn () => broadcast(new \App\Events\ChatTyping($conversation->id, $me->id, $me->name))->toOthers());

        return response()->json(['ok' => true]);
    }

    /** Mark a conversation fully read (on open, and as messages arrive while it's open). */
    public function read(Conversation $conversation)
    {
        $me = auth()->user();
        abort_unless($this->canAccess($me, $conversation), 403);
        $this->markRead($conversation, $me);

        return response()->json(['ok' => true]);
    }

    /** Delete a message — its author (within 15 minutes) or an admin (anytime). Removes any file. */
    public function destroyMessage(ChatMessage $message)
    {
        $me = auth()->user();
        $isAuthor = $message->user_id === $me->id;
        abort_unless($isAuthor || $me->isAdmin(), 403);
        // Authors lose the ability to delete after the 15-minute window; admins may always remove.
        if ($isAuthor && ! $me->isAdmin() && ! $message->withinMutateWindow()) {
            return response()->json(['error' => 'The 15-minute window to delete this message has passed.'], 422);
        }

        if ($message->attachment) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($message->attachment);
        }
        $conversationId = $message->conversation_id;
        $message->delete();

        $this->broadcastSafe(fn () => broadcast(new \App\Events\ChatMessageDeleted($conversationId, $message->id)));

        return response()->json(['ok' => true]);
    }

    /** Edit a message — its author within the 15-minute window (admins are exempt). Text only. */
    public function editMessage(Request $request, ChatMessage $message)
    {
        $me = auth()->user();
        abort_unless($message->user_id === $me->id, 403);
        if (! $me->isAdmin() && ! $message->withinMutateWindow()) {
            return response()->json(['error' => 'The 15-minute window to edit this message has passed.'], 422);
        }

        $request->validate(['body' => ['required', 'string', 'max:20000']]);
        $body = trim((string) $request->input('body'));
        $body = $body !== '' ? \App\Support\Html::clean($body) : null;
        if ($body === null || (strip_tags($body) === '' && ! str_contains($body, '<img'))) {
            return response()->json(['error' => 'Message cannot be empty.'], 422);
        }

        $message->update(['body' => $body, 'edited_at' => now()]);

        $this->broadcastSafe(fn () => broadcast(new \App\Events\ChatMessageEdited($message->conversation_id, $message->id, $body))->toOthers());

        return response()->json(['ok' => true, 'id' => $message->id, 'body' => $body, 'edited' => true]);
    }

    /** Toggle an emoji reaction on a message for the current user. */
    public function reactMessage(Request $request, ChatMessage $message)
    {
        $me = auth()->user();
        abort_unless($this->canAccess($me, $message->conversation), 403);

        $data = $request->validate(['emoji' => ['required', 'string', 'max:16']]);
        $map = $message->reactionMap();
        $key = (string) $me->id;

        // Same emoji again → remove it; otherwise set/replace this user's reaction.
        if (($map[$key] ?? null) === $data['emoji']) {
            unset($map[$key]);
        } else {
            $map[$key] = $data['emoji'];
        }
        $message->update(['reactions' => $map ?: null]);

        $this->broadcastSafe(fn () => broadcast(new \App\Events\ChatMessageReacted($message->conversation_id, $message->id, $map)));

        return response()->json(['ok' => true, 'id' => $message->id, 'reactions' => (object) $map]);
    }

    /** Forward a message's content to a teammate's direct conversation. */
    public function forwardMessage(Request $request, ChatMessage $message)
    {
        $me = auth()->user();
        // Must be able to see the source conversation to forward from it.
        abort_unless($this->canAccess($me, $message->conversation), 403);

        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $target = User::findOrFail($data['user_id']);
        abort_if($target->id === $me->id, 400);
        abort_unless($target->isPanelUser(), 404);

        // Find or create my 1:1 conversation with the target.
        $conversation = $this->findDirect($me->id, $target->id)
            ?? tap(Conversation::create(['type' => 'direct', 'created_by' => $me->id]), function ($c) use ($me, $target) {
                $c->members()->attach([$me->id, $target->id]);
            });
        $conversation->loadMissing('members');

        // Copy the attachment (if any) so deleting the original never breaks the forward.
        $path = $name = null;
        if ($message->attachment && \Illuminate\Support\Facades\Storage::disk('public')->exists($message->attachment)) {
            $ext = pathinfo($message->attachment, PATHINFO_EXTENSION);
            $path = 'chat/'.\Illuminate\Support\Str::random(40).($ext ? '.'.$ext : '');
            \Illuminate\Support\Facades\Storage::disk('public')->copy($message->attachment, $path);
            $name = $message->attachment_name;
        }

        $forwarded = $conversation->messages()->create([
            'user_id' => $me->id,
            'body' => $message->body,
            'attachment' => $path,
            'attachment_name' => $name,
        ]);
        $conversation->update(['last_message_at' => $forwarded->created_at]);
        $this->markRead($conversation, $me);

        $this->broadcastSafe(fn () => broadcast(new ChatMessagePosted($forwarded, $conversation->members->pluck('id')->all()))->toOthers());

        return response()->json([
            'ok' => true,
            'to' => $target->name,
            'conversation_url' => route('admin.chat.show', $conversation),
        ]);
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
