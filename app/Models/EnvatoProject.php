<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A named comparison: several authors' products lined up against each other. */
class EnvatoProject extends Model
{
    protected $guarded = [];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(EnvatoProduct::class, 'envato_project_products')
            ->withTimestamps();
    }

    /** Our own entry in this race, if there is one. */
    public function ownProduct(): BelongsTo
    {
        return $this->belongsTo(EnvatoProduct::class, 'own_product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
