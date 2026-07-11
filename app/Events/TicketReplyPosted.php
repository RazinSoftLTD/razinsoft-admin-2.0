<?php

namespace App\Events;

use App\Models\TicketReply;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketReplyPosted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TicketReply $reply)
    {
    }

    /** Public per-ticket channel. (Swap to PrivateChannel + auth for stricter access.) */
    public function broadcastOn(): Channel
    {
        return new Channel('tickets.'.$this->reply->ticket_id);
    }

    public function broadcastAs(): string
    {
        return 'reply.posted';
    }

    public function broadcastWith(): array
    {
        $r = $this->reply->loadMissing('author');

        return [
            'id' => $r->id,
            'ticket_id' => $r->ticket_id,
            'is_admin' => (bool) $r->is_admin,
            'author' => $r->author->name ?? ($r->is_admin ? 'Support' : 'Customer'),
            'message' => $r->message,
            'attachment' => $r->attachment ? asset('storage/'.$r->attachment) : null,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
