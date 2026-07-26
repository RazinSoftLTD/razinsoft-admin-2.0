<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A one-off send to many people — a newsletter, an announcement, a campaign. */
class EmailCampaign extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'sending' => 'Sending',
        'sent' => 'Sent',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = [];

    protected $casts = [
        'audience' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(EmailConfig::class, 'email_config_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return [
            'draft' => 'bg-gray-100 text-gray-600',
            'scheduled' => 'bg-violet-50 text-violet-700',
            'sending' => 'bg-sky-50 text-sky-700',
            'sent' => 'bg-emerald-50 text-emerald-700',
            'cancelled' => 'bg-gray-100 text-gray-500',
        ][$this->status] ?? 'bg-gray-100 text-gray-600';
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }

    /** How far along a send is, for the progress bar. */
    public function progress(): array
    {
        $total = max(1, $this->total_recipients);
        $done = $this->recipients()->where('status', '!=', 'pending')->count();

        return ['done' => $done, 'total' => $this->total_recipients, 'percent' => (int) round($done / $total * 100)];
    }
}
