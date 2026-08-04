<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One renewed term. Kept as its own row so the history survives the contract's dates moving on. */
class MaintenanceRenewal extends Model
{
    protected $guarded = [];

    protected $casts = [
        'previous_ends_on' => 'date',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function maintenanceProject(): BelongsTo
    {
        return $this->belongsTo(MaintenanceProject::class);
    }

    public function renewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renewed_by');
    }
}
