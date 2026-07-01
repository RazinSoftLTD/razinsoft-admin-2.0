<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductSuitableFor extends Model
{
    protected $table = 'product_suitable_for';
    protected $guarded = [];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
