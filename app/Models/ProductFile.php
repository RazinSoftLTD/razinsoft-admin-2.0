<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductFile extends Model
{
    protected $guarded = [];
    protected $casts = ['is_latest' => 'boolean'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
