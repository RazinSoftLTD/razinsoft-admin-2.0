<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One tracked CodeCanyon item; columns hold the latest values from the API. */
class EnvatoProduct extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
        'trending' => 'boolean',
        'rating' => 'decimal:2',
        'published_at' => 'datetime',
        'item_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(EnvatoAuthor::class, 'envato_author_id');
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(EnvatoNiche::class, 'envato_niche_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(EnvatoSnapshot::class)->orderBy('captured_on');
    }

    public function price(): float
    {
        return $this->price_cents / 100;
    }

    /** Estimated gross revenue (sales × price) — not real earnings; the API never exposes those. */
    public function estimatedRevenue(): float
    {
        return $this->number_of_sales * $this->price_cents / 100;
    }

    /** Sales added since the snapshot closest to N days ago (null until we have history). */
    public function salesGrowth(int $days = 7): ?int
    {
        $past = $this->snapshots()
            ->whereDate('captured_on', '<=', now()->subDays($days)->toDateString())
            ->orderByDesc('captured_on')->first()
            ?? $this->snapshots()->orderBy('captured_on')->first();

        return $past ? max(0, $this->number_of_sales - $past->number_of_sales) : null;
    }

    /** Top-level CodeCanyon category, e.g. "wordpress/ecommerce" → "wordpress". */
    public function categoryLabel(): string
    {
        return $this->classification ? str($this->classification)->explode('/')->map(fn ($p) => str($p)->headline())->join(' › ') : '—';
    }
}
