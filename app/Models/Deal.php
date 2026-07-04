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
    ];

    public const STAGES = [
        'new' => 'New',
        'qualified' => 'Qualified',
        'proposal' => 'Proposal',
        'negotiation' => 'Negotiation',
        'won' => 'Won',
        'lost' => 'Lost',
    ];

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

    /** Invoices raised from this deal. */
    public function invoices(): HasMany
    {
        return $this->hasMany(ClientInvoice::class);
    }

    public function isWon(): bool
    {
        return $this->stage === 'won';
    }

    /** Open (not won/lost) and past its expected close date. */
    public function isOverdue(): bool
    {
        return $this->expected_close_date
            && ! in_array($this->stage, ['won', 'lost'], true)
            && $this->expected_close_date->isPast();
    }
}
