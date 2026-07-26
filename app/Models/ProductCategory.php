<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The shared Product Category / Sub-category list used by Leads, Deals and Clients.
 * parent_id = null → a category; otherwise a sub-category of that parent.
 */
class ProductCategory extends Model
{
    protected $fillable = ['parent_id', 'name', 'sort_order'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /** Top-level categories with their sub-categories, in display order. */
    public static function tree()
    {
        return static::whereNull('parent_id')->with('children')->orderBy('sort_order')->orderBy('name')->get();
    }

    /** ['Category name', …] for a dropdown. */
    public static function categoryNames(): array
    {
        return static::whereNull('parent_id')->orderBy('sort_order')->orderBy('name')->pluck('name')->all();
    }

    /** "Ready ecommerce" for a category, "Ready ecommerce › Multi Vendor" for a sub-category. */
    public function fullName(): string
    {
        return $this->parent_id && $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }

    /**
     * Everything pickable in the "Interested in" list, in tree order: each category followed by
     * its own sub-categories. Returns [['id' =>, 'label' =>, 'is_sub' =>], …].
     */
    public static function pickerOptions(): array
    {
        $out = [];
        foreach (static::tree() as $cat) {
            $out[] = ['id' => $cat->id, 'label' => $cat->name, 'is_sub' => false];
            foreach ($cat->children as $sub) {
                $out[] = ['id' => $sub->id, 'label' => $sub->name, 'is_sub' => true];
            }
        }

        return $out;
    }

    /** ['Category' => ['Sub A', 'Sub B'], …] — drives the dependent sub-category dropdown. */
    public static function subMap(): array
    {
        return static::tree()->mapWithKeys(fn ($c) => [$c->name => $c->children->pluck('name')->all()])->all();
    }
}
