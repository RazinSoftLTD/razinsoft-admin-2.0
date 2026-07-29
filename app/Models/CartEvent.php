<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One "added to cart" event on the website — by a signed-in client or an anonymous visitor. */
class CartEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'client_id', 'product_id', 'product_slug', 'product_name', 'label',
        'unit_price', 'qty', 'country', 'ip', 'user_agent', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime', 'unit_price' => 'decimal:2'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
