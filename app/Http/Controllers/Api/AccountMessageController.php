<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessagePosted;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

/**
 * Client-facing direct messaging with the RazinSoft team.
 * Each customer has a single "client" conversation; staff who hold `chat.clients`
 * see and reply to it from the admin Teams → Client tab.
 */
class AccountMessageController extends Controller
{
    /** The customer's conversation (created lazily on first visit). */
    private function conversationFor($user): Conversation
    {
        $conversation = Conversation::where('type', 'client')
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create(['type' => 'client', 'created_by' => $user->id]);
            $conversation->members()->attach($user->id);
        }

        return $conversation;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $conversation = $this->conversationFor($user);
        // Mark the customer's own view as read.
        $conversation->members()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        $messages = $conversation->messages()->with('author:id,name,role,photo')->orderBy('id')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'mine' => $m->user_id === $user->id,
                'author' => $m->author?->name ?? 'RazinSoft',
                'attachment' => $m->attachment_url,
                'attachment_name' => $m->attachment_name,
                'is_image' => $m->is_image,
                'time' => $m->created_at->format('d M, g:i A'),
            ]);

        return response()->json(['data' => $messages]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $user = $request->user();
        $conversation = $this->conversationFor($user);

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => clean(trim($data['body'])),
        ]);
        $conversation->update(['last_message_at' => $message->created_at]);
        $conversation->members()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        // Notify any staff already in the thread (best effort — the Client tab also surfaces it).
        broadcast(new ChatMessagePosted($message, $conversation->members->pluck('id')->all()));

        return response()->json([
            'ok' => true,
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
                'mine' => true,
                'author' => $user->name,
                'time' => $message->created_at->format('d M, g:i A'),
            ],
        ], 201);
    }
}
