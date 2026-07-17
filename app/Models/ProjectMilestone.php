<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectMilestone extends Model
{
    protected $guarded = [];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'cost' => 'decimal:2'];

    public const STATUSES = ['incomplete' => 'Incomplete', 'complete' => 'Complete'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'milestone_id');
    }
}
