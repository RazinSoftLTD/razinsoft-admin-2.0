<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment/delivery step agreed on a deal: what, how much, by when. Kept separate from
 * ProjectMilestone — a project may import these once and then manage its own.
 */
class DealMilestone extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'deal_id', 'title', 'amount', 'due_date', 'position',
        'status', 'completed_at', 'cancelled_at', 'status_by',
    ];

    // The column defaults to pending, but that only lands after a reload — without this a
    // freshly created milestone reads back as null and looks neither pending nor settled.
    protected $attributes = ['status' => 'pending'];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** Whoever marked it done or called it off. */
    public function statusBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_by');
    }

    public function isSettled(): bool
    {
        return $this->status !== 'pending';
    }

    /** When it was settled, whichever way it went. */
    public function settledAt(): ?\Illuminate\Support\Carbon
    {
        return $this->completed_at ?? $this->cancelled_at;
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** Only a milestone still waiting on someone can be overdue. */
    public function isOverdue(): bool
    {
        return $this->status === 'pending'
            && $this->due_date !== null
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }
}
