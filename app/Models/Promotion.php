<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A site-wide promo — either a thin "Top Banner" shown above the main nav, or a
 * "Popup" modal shown once per page load. Only `published` rows within their
 * starts_at/ends_at window are ever live.
 */
class Promotion extends Model
{
    public const TYPE_TOP_BANNER = 'top_banner';
    public const TYPE_POPUP = 'popup';
    public const TYPES = [
        self::TYPE_TOP_BANNER => 'Top Banner',
        self::TYPE_POPUP => 'Popup',
    ];

    protected $fillable = ['image', 'type', 'status', 'starts_at', 'ends_at', 'published_at', 'created_by'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    /** Published rows whose schedule window includes right now. */
    public function scopeLive(Builder $q): Builder
    {
        $now = now();

        return $q->published()
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Published AND within its schedule window right now. */
    public function isLive(): bool
    {
        if (! $this->isPublished()) {
            return false;
        }
        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
