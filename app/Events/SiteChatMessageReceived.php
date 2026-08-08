<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A website-chat message, in either direction.
 *
 * Two audiences: the panel's inbox (a private channel for the team) and the visitor's own
 * widget, which listens on a channel named after its token so it hears only its own thread.
 */
class SiteChatMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $chatId,
        public int $messageId,
        public string $direction,
        public ?string $token = null,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new Channel('site-chat.inbox')];

        if ($this->token) {
            $channels[] = new Channel('site-chat.'.$this->token);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message';
    }
}
