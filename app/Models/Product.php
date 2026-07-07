<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_featured' => 'boolean',
        'for_home' => 'boolean',
        'price' => 'decimal:2',
        'ext_price' => 'decimal:2',
        'rating' => 'decimal:1',
    ];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    /** Cache key + invalidation for the public product-detail API payload. */
    public static function cacheKey(string $slug): string
    {
        return "api:product:{$slug}";
    }

    public static function forgetCache(?string $slug): void
    {
        if ($slug) {
            \Illuminate\Support\Facades\Cache::forget(static::cacheKey($slug));
        }
    }

    protected static function booted(): void
    {
        // Any direct product edit (general/media/status) drops its cached detail payload.
        static::saved(fn (self $p) => static::forgetCache($p->getOriginal('slug') ?: $p->slug));
        static::saved(fn (self $p) => static::forgetCache($p->slug));
        static::deleted(fn (self $p) => static::forgetCache($p->slug));

        // A publish/unpublish (or deletion) flips this product's visibility on any blog post
        // that features it — flush those cached article payloads so they re-query immediately.
        static::saved(fn (self $p) => $p->wasChanged('status') ? static::forgetLinkedArticleCaches($p) : null);
        static::deleted(fn (self $p) => static::forgetLinkedArticleCaches($p));
    }

    /** Clear the cached payload of every blog post that features this product. */
    protected static function forgetLinkedArticleCaches(self $p): void
    {
        \Illuminate\Support\Facades\DB::table('article_product')
            ->join('articles', 'articles.id', '=', 'article_product.article_id')
            ->where('article_product.product_id', $p->id)
            ->pluck('articles.slug')
            ->each(fn ($slug) => Article::forgetCache($slug));
    }

    public function galleryGroups(): HasMany
    {
        return $this->hasMany(GalleryGroup::class)->orderBy('sort_order');
    }

    public function galleryImages(): HasManyThrough
    {
        return $this->hasManyThrough(GalleryImage::class, GalleryGroup::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class)->orderBy('sort_order');
    }

    /** The first plan (by sort order) — used for the "from" price on product cards. */
    public function firstPlan(): HasOne
    {
        return $this->hasOne(Plan::class)->orderBy('sort_order');
    }

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class)->orderBy('sort_order');
    }

    public function tech(): HasMany
    {
        return $this->hasMany(ProductTech::class)->orderBy('sort_order');
    }

    public function suitableFor(): HasMany
    {
        return $this->hasMany(ProductSuitableFor::class)->orderBy('sort_order');
    }

    public function docs(): HasMany
    {
        return $this->hasMany(ProductDoc::class)->orderBy('sort_order');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(ProductFaq::class)->orderBy('sort_order');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class)->latest();
    }

    public function latestFile(): HasOne
    {
        return $this->hasOne(ProductFile::class)->where('is_latest', true);
    }

    public function demos(): HasMany
    {
        return $this->hasMany(ProductDemo::class)->orderBy('sort_order')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Recompute the denormalised rating + reviews_count from the approved reviews. */
    public function syncReviewAggregates(): void
    {
        $agg = $this->reviews()->where('is_approved', true)
            ->selectRaw('COUNT(*) as c, COALESCE(AVG(rating), 0) as a')
            ->first();

        $this->forceFill([
            'reviews_count' => (int) $agg->c,
            'rating' => $agg->c ? round((float) $agg->a, 1) : 0,
        ])->save();
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class)->latest();
    }

    public function seo(): MorphOne
    {
        return $this->morphOne(Seo::class, 'seoable');
    }
}
