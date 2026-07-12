<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends Model
{
    protected $guarded = [];

    protected $casts = [
        'value' => 'decimal:2',
        'expected_close_date' => 'date',
        'next_follow_up_at' => 'datetime',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
    ];

    public const STAGES = [
        'new' => 'New',
        'qualified' => 'Qualified',
        'proposal' => 'Proposal',
        'negotiation' => 'Negotiation',
        'won' => 'Won',
        'lost' => 'Lost',
    ];

    /** Default win-probability per stage — powers the weighted (forecast) pipeline value. */
    public const STAGE_PROBABILITY = [
        'new' => 10, 'qualified' => 30, 'proposal' => 55, 'negotiation' => 80, 'won' => 100, 'lost' => 0,
    ];

    /** Project types for a software house. */
    public const PROJECT_TYPES = [
        'Web App', 'Mobile App', 'SaaS Product', 'E-commerce', 'Custom Software',
        'UI/UX Design', 'API / Integration', 'Maintenance & Support', 'Consulting', 'Other',
    ];

    public const PRIORITIES = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];

    public const OPEN_STAGES = ['new', 'qualified', 'proposal', 'negotiation'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ClientInvoice::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DealActivity::class)->latest('id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(DealFollowUp::class)->orderByDesc('due_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DealAttachment::class)->latest('id');
    }

    /** Re-point the deal's cached "next follow-up" (used by the board/list highlight) at the earliest pending one. */
    public function syncNextFollowUp(): void
    {
        $next = $this->followUps()->whereNull('completed_at')->reorder('due_at')->first();

        $this->forceFill([
            'next_follow_up_at' => $next?->due_at,
            'follow_up_title' => $next?->title,
            'follow_up_note' => $next?->note,
        ])->save();
    }

    public function isWon(): bool
    {
        return $this->stage === 'won';
    }

    public function isOpen(): bool
    {
        return in_array($this->stage, self::OPEN_STAGES, true);
    }

    public function isOverdue(): bool
    {
        return $this->expected_close_date
            && ! in_array($this->stage, ['won', 'lost'], true)
            && $this->expected_close_date->isPast();
    }

    /** A follow-up is due when its date is today or already past (and the deal is still open). */
    public function isFollowUpDue(): bool
    {
        return $this->next_follow_up_at
            && $this->isOpen()
            && $this->next_follow_up_at->copy()->startOfDay()->lte(now()->startOfDay());
    }

    /** Invoices can only be raised for a won deal that is linked to a client. */
    public function canInvoice(): bool
    {
        return $this->isWon() && $this->client_id;
    }

    /** Effective win probability (manual override, else the stage default). */
    public function getEffectiveProbabilityAttribute(): int
    {
        return $this->probability ?? (self::STAGE_PROBABILITY[$this->stage] ?? 0);
    }

    /** Forecast value = deal value × win probability. */
    public function getWeightedValueAttribute(): float
    {
        return round((float) $this->value * $this->effective_probability / 100, 2);
    }
}
