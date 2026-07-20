<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Daily reading of a product's public numbers — this is how we get trends the API doesn't provide. */
class EnvatoSnapshot extends Model
{
    protected $guarded = [];

    protected $casts = ['captured_on' => 'date', 'rating' => 'decimal:2'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(EnvatoProduct::class, 'envato_product_id');
    }
}
