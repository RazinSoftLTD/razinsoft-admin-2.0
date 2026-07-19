<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageReacted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array $reactions raw {userId: emoji} map after the toggle */
    public function __construct(public int $conversationId, public int $messageId, public array $reactions)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('chat.conversation.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'message.reacted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->messageId, 'reactions' => (object) $this->reactions];
    }
}
