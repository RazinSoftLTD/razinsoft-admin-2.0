<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatChecklistToggled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $conversationId, public int $messageId, public int $index, public bool $checked)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('chat.conversation.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'checklist.toggled';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->messageId, 'index' => $this->index, 'checked' => $this->checked];
    }
}
