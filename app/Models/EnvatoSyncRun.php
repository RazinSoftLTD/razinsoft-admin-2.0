<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One attempt at refreshing the CodeCanyon watchlist. */
class EnvatoSyncRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(EnvatoAuthor::class, 'envato_author_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /** Waiting for a worker, or on the wire right now. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', ['queued', 'running']);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['queued', 'running'], true);
    }

    /**
     * Queued long enough that nothing is coming to pick it up.
     *
     * A job that sits in `queued` usually means no `queue:work` is running, and
     * saying so beats a spinner that never stops.
     */
    public function looksStalled(): bool
    {
        return $this->status === 'queued' && $this->created_at?->lt(now()->subMinutes(2));
    }

    public function durationForHumans(): ?string
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        $seconds = $this->finished_at->diffInSeconds($this->started_at);

        return $seconds < 60 ? "{$seconds}s" : round($seconds / 60, 1).'m';
    }

    public function label(): string
    {
        return match ($this->trigger) {
            'schedule' => 'Scheduled',
            'catch-up' => 'Catch-up',
            'author' => 'Author refresh',
            default => 'Manual',
        };
    }
}
