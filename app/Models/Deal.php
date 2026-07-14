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

    /**
     * The open pipeline stages, configurable in Settings → CRM Settings. Each key is
     * the slug of its label, so the seeded New/Qualified/Proposal/Negotiation keep the
     * existing keys. Falls back to the defaults when nothing is configured.
     */
    public static function pipelineStages(): array
    {
        $labels = LeadOption::labels('deal_stage');
        if (empty($labels)) {
            return array_intersect_key(self::STAGES, array_flip(self::OPEN_STAGES));
        }
        $out = [];
        foreach ($labels as $label) {
            $out[\Illuminate\Support\Str::slug($label)] = $label;
        }

        return $out;
    }

    /** Open pipeline stages + the two terminal stages (Won / Lost). */
    public static function stages(): array
    {
        return self::pipelineStages() + ['won' => 'Won', 'lost' => 'Lost'];
    }

    /** key => [dot class, badge class]. Open stages cycle a palette; Won/Lost are fixed. */
    public static function stageColors(): array
    {
        $palette = [
            ['bg-gray-400', 'bg-gray-100 text-gray-600'],
            ['bg-indigo-500', 'bg-indigo-50 text-indigo-700'],
            ['bg-orange-500', 'bg-orange-50 text-orange-700'],
            ['bg-amber-500', 'bg-amber-50 text-amber-700'],
            ['bg-sky-500', 'bg-sky-50 text-sky-700'],
            ['bg-violet-500', 'bg-violet-50 text-violet-700'],
            ['bg-pink-500', 'bg-pink-50 text-pink-700'],
            ['bg-teal-500', 'bg-teal-50 text-teal-700'],
        ];
        $colors = [];
        foreach (array_keys(self::pipelineStages()) as $i => $key) {
            $colors[$key] = $palette[$i % count($palette)];
        }
        $colors['won'] = ['bg-emerald-500', 'bg-emerald-50 text-emerald-700'];
        $colors['lost'] = ['bg-red-500', 'bg-red-50 text-red-600'];

        return $colors;
    }

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
