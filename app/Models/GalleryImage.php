<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class GalleryImage extends Model
{
    protected $guarded = [];
    public function group(): BelongsTo { return $this->belongsTo(GalleryGroup::class, 'gallery_group_id'); }
}
