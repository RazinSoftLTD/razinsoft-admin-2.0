<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
        'takeaways' => 'array',
        'is_featured' => 'boolean',
        'published_at' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'article_product');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public static function cacheKey(string $slug): string
    {
        return "api:article:{$slug}";
    }

    public static function forgetCache(?string $slug): void
    {
        if ($slug) {
            \Illuminate\Support\Facades\Cache::forget(static::cacheKey($slug));
        }
    }

    protected static function booted(): void
    {
        static::saved(fn (self $a) => static::forgetCache($a->getOriginal('slug') ?: $a->slug));
        static::saved(fn (self $a) => static::forgetCache($a->slug));
        static::deleted(fn (self $a) => static::forgetCache($a->slug));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
