<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One "category in city, country" lookup. */
class PlaceSearch extends Model
{
    protected $guarded = [];

    protected $casts = ['finished_at' => 'datetime'];

    public function leads(): HasMany
    {
        return $this->hasMany(PlaceLead::class, 'search_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
