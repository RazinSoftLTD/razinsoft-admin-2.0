<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A CodeCanyon author we watch — ours or a competitor. */
class EnvatoAuthor extends Model
{
    protected $guarded = [];

    protected $casts = ['is_own' => 'boolean', 'badges' => 'array', 'synced_at' => 'datetime'];

    public function products(): HasMany
    {
        return $this->hasMany(EnvatoProduct::class);
    }

    public function profileUrl(): string
    {
        return 'https://codecanyon.net/user/'.$this->username;
    }

    /** Estimated gross revenue — sales × current price. The API never exposes real earnings. */
    public function estimatedRevenue(): float
    {
        return $this->products->sum(fn ($p) => $p->number_of_sales * $p->price_cents) / 100;
    }
}
