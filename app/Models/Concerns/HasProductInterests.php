<?php

namespace App\Models\Concerns;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * "Interested in" — the product categories and sub-categories a lead, deal or client is after.
 * Shared by all three so the picker, the labels and the filter behave identically everywhere.
 */
trait HasProductInterests
{
    public function interests(): MorphToMany
    {
        return $this->morphToMany(ProductCategory::class, 'interestable', 'product_interests', 'interestable_id', 'product_category_id')
            ->withTimestamps();
    }

    /** Labels to render, parent first: ["Ready ecommerce", "Ready ecommerce › Multi Vendor"]. */
    public function interestLabels(): array
    {
        return $this->interests->map(fn (ProductCategory $c) => $c->fullName())->all();
    }

    /**
     * Filter by a category: matching the category itself also matches its sub-categories, so
     * picking "Ready ecommerce" finds everyone interested in any part of it.
     */
    public function scopeInterestedIn(Builder $query, $categoryId): Builder
    {
        $ids = ProductCategory::where('id', $categoryId)->orWhere('parent_id', $categoryId)->pluck('id');

        return $query->whereHas('interests', fn ($q) => $q->whereIn('product_categories.id', $ids));
    }
}
