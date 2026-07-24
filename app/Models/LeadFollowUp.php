<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFollowUp extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** Follow-up channels. */
    public const TYPES = [
        'call' => 'Phone Call',
        'whatsapp' => 'WhatsApp',
        'meeting' => 'Meeting',
        'email' => 'Email',
        'sms' => 'SMS',
        'other' => 'Other',
    ];

    public const PRIORITIES = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];

    /** Stored statuses. "Overdue" is derived (pending + past due), never stored. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_DONE => 'Done',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    // ----- Relations -----
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** The sales person responsible for this follow-up. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // ----- Status helpers -----
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /** Pending and its scheduled time has already passed. */
    public function isOverdue(): bool
    {
        return $this->isPending() && $this->scheduled_at && $this->scheduled_at->isPast();
    }

    /** The status to show the user: 'overdue' folds in for past-due pending items. */
    public function effectiveStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    public function statusLabel(): string
    {
        return $this->isOverdue() ? 'Overdue' : (self::STATUSES[$this->status] ?? ucfirst($this->status));
    }

    /** Tailwind badge classes per (effective) status — Pending orange, Done green, Overdue red, Cancelled gray. */
    public function statusBadge(): string
    {
        return [
            'pending' => 'bg-orange-50 text-orange-700 ring-orange-200',
            'overdue' => 'bg-red-50 text-red-700 ring-red-200',
            'done' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'cancelled' => 'bg-gray-100 text-gray-500 ring-gray-200',
        ][$this->effectiveStatus()] ?? 'bg-gray-100 text-gray-500 ring-gray-200';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst((string) $this->priority);
    }

    // ----- Query scopes -----
    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeDone(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_DONE);
    }

    /** Pending items whose scheduled time is in the past. */
    public function scopeOverdue(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING)->where('scheduled_at', '<', now());
    }

    /** Pending items scheduled within the given day. */
    public function scopeOnDay(Builder $q, $date): Builder
    {
        return $q->whereDate('scheduled_at', $date);
    }
}
