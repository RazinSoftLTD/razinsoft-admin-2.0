<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealFollowUp extends Model
{
    protected $fillable = ['deal_id', 'user_id', 'title', 'note', 'due_at', 'completed_at'];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDone(): bool
    {
        return ! is_null($this->completed_at);
    }

    /** Pending and its time has arrived / passed. */
    public function isDue(): bool
    {
        return ! $this->isDone() && $this->due_at->copy()->startOfDay()->lte(now()->startOfDay());
    }
}
