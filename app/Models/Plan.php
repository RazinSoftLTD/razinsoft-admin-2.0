<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Plan extends Model
{
    protected $guarded = [];
    protected $casts = ['perks' => 'array', 'is_popular' => 'boolean', 'price' => 'decimal:2'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
