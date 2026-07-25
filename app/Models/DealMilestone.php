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
    protected $fillable = ['deal_id', 'title', 'amount', 'due_date', 'position'];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast() && ! $this->due_date->isToday();
    }
}
