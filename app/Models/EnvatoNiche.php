<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Groups comparable products across authors, e.g. "eCommerce". */
class EnvatoNiche extends Model
{
    protected $guarded = [];

    public function products(): HasMany
    {
        return $this->hasMany(EnvatoProduct::class);
    }
}
