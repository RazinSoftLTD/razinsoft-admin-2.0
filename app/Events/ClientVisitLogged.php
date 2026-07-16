<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired after a website visit is logged — lets the Client Activity pages refresh live. */
class ClientVisitLogged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $logId) {}

    public function broadcastOn(): array
    {
        return [new Channel('activity.visits')];
    }

    public function broadcastAs(): string
    {
        return 'logged';
    }
}
