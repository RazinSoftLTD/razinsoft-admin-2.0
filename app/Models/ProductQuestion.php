<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductQuestion extends Model
{
    protected $guarded = [];

    protected $casts = ['is_public' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** All answers in this thread (oldest first). */
    public function answers(): HasMany
    {
        return $this->hasMany(ProductAnswer::class)->oldest();
    }

    /** Public questions shown on the storefront. */
    public function scopePublic(Builder $q): Builder
    {
        return $q->where('is_public', true);
    }
}
