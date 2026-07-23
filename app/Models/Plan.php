<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'perks' => 'array',
        'is_popular' => 'boolean',
        'price' => 'decimal:2',
        'offer_value' => 'decimal:2',
        'offer_starts_at' => 'datetime',
        'offer_ends_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Whether this plan's own offer (percent/flat) is currently within its date window. */
    public function hasActiveOffer(): bool
    {
        if (! $this->offer_type || $this->offer_value === null) {
            return false;
        }
        $now = now();
        if ($this->offer_starts_at && $now->lt($this->offer_starts_at)) {
            return false;
        }
        if ($this->offer_ends_at && $now->gt($this->offer_ends_at)) {
            return false;
        }

        return true;
    }

    /** Apply this plan's active offer to its price. Unchanged if no active offer. */
    public function discountedPrice(float $price): float
    {
        if (! $this->hasActiveOffer()) {
            return $price;
        }

        $discount = $this->offer_type === 'percent'
            ? $price * ((float) $this->offer_value) / 100
            : (float) $this->offer_value;

        return max(0, round($price - $discount, 2));
    }

    /** Percent-off for display (works for both percent and flat offer types), or null if inactive. */
    public function offerPercentOff(float $price): ?int
    {
        if (! $this->hasActiveOffer() || $price <= 0) {
            return null;
        }

        return (int) round((($price - $this->discountedPrice($price)) / $price) * 100);
    }
}
