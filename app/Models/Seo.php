<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
class Seo extends Model
{
    protected $guarded = [];
    protected $casts = ['price_valid_until' => 'date'];
    public function seoable(): MorphTo { return $this->morphTo(); }
}
