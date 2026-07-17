<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired when a WhatsApp message is received or sent — updates the inbox live. */
class WhatsappMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $chatId,
        public int $messageId,
        public string $direction,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('whatsapp.inbox')];
    }

    public function broadcastAs(): string
    {
        return 'message';
    }

    public function broadcastWith(): array
    {
        return ['chat_id' => $this->chatId, 'message_id' => $this->messageId, 'direction' => $this->direction];
    }
}
