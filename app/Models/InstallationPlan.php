<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InstallationPlan extends Model
{
    protected $guarded = [];

    protected $casts = ['price' => 'decimal:2', 'sale_price' => 'decimal:2', 'is_popular' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Features included in this plan (checkmarks in the comparison table). */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(InstallationFeature::class, 'installation_plan_feature', 'plan_id', 'feature_id');
    }

    public function includes(int $featureId): bool
    {
        return $this->features->contains('id', $featureId);
    }
}
