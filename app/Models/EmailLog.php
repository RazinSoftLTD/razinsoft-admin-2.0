<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One message — queued, sent, failed or cancelled. This row IS the queue entry as well as the log,
 * so the two can never disagree about what happened to it.
 */
class EmailLog extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'sending' => 'Sending',
        'sent' => 'Sent',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = [];

    protected $casts = [
        'cc' => 'array',
        'bcc' => 'array',
        'bounced' => 'boolean',
        'complained' => 'boolean',
        'scheduled_at' => 'datetime',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'first_opened_at' => 'datetime',
        'first_clicked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            $log->tracking_id ??= (string) Str::uuid();
        });
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(EmailConfig::class, 'email_config_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function related()
    {
        return $this->morphTo();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function opens(): HasMany
    {
        return $this->hasMany(EmailOpen::class)->latest('opened_at');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(EmailClick::class)->latest('clicked_at');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Tailwind classes for the status pill — one place so every screen agrees. */
    public function statusTone(): string
    {
        return [
            'pending' => 'bg-gray-100 text-gray-600',
            'sending' => 'bg-sky-50 text-sky-700',
            'sent' => 'bg-emerald-50 text-emerald-700',
            'failed' => 'bg-red-50 text-red-600',
            'cancelled' => 'bg-gray-100 text-gray-500',
        ][$this->status] ?? 'bg-gray-100 text-gray-600';
    }

    public function isRetryable(): bool
    {
        return in_array($this->status, ['failed', 'cancelled'], true);
    }

    /** How long the provider took to accept it, for the analytics average. */
    public function deliverySeconds(): ?int
    {
        return $this->queued_at && $this->sent_at
            ? max(0, $this->sent_at->diffInSeconds($this->queued_at))
            : null;
    }

    // ---------------------------------------------------------------- scopes

    public function scopeStatus(Builder $q, ?string $status): Builder
    {
        return $status ? $q->where('status', $status) : $q;
    }

    /** Due to go out now: pending, and either unscheduled or past its time. */
    public function scopeDue(Builder $q): Builder
    {
        return $q->where('status', 'pending')
            ->where(fn ($w) => $w->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()));
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('to_email', 'like', "%{$term}%")
            ->orWhere('to_name', 'like', "%{$term}%")
            ->orWhere('subject', 'like', "%{$term}%"));
    }
}
