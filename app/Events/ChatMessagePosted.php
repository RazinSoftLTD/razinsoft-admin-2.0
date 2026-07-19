<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessagePosted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param int[] $memberIds recipients whose personal channel should ping (for badges/lists) */
    public function __construct(public ChatMessage $message, public array $memberIds = [])
    {
    }

    /** Broadcast to the open thread AND to every member's personal channel (live badge). */
    public function broadcastOn(): array
    {
        $channels = [new Channel('chat.conversation.'.$this->message->conversation_id)];
        foreach ($this->memberIds as $id) {
            $channels[] = new Channel('chat.user.'.$id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.posted';
    }

    public function broadcastWith(): array
    {
        $m = $this->message->loadMissing('author', 'conversation', 'replyTo.author');

        return [
            'id' => $m->id,
            'conversation_id' => $m->conversation_id,
            'conv_type' => $m->conversation->type ?? 'direct',
            'conv_name' => $m->conversation->name,          // group name (null for direct)
            'user_id' => $m->user_id,
            'author' => $m->author->name ?? 'Someone',
            'avatar' => $m->author->photo_url,
            'body' => $m->body,                 // sanitized HTML
            'preview' => $m->preview,           // plain-text snippet for notifications
            'attachment' => $m->attachment_url,
            'attachment_name' => $m->attachment_name,
            'is_image' => $m->is_image,
            'created_at' => $m->created_at?->toIso8601String(),
            'time' => $m->created_at?->format('g:i A'),
            'quoted' => $m->quoted(),
            'reactions' => (object) $m->reactionMap(),
        ];
    }
}
