<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class GalleryGroup extends Model
{
    protected $guarded = [];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function images(): HasMany { return $this->hasMany(GalleryImage::class)->orderBy('sort_order')->orderBy('id'); }
}
