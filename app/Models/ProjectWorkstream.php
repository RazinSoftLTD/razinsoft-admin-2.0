<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectWorkstream extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['progress' => 'integer'];

    /** A workstream is a deliverable track inside a project. */
    public const TYPES = [
        'Website', 'Admin Panel', 'Android App', 'iOS App', 'Backend API',
        'Vendor Panel', 'Rider App', 'Google Play Publish', 'Apple Store Publish', 'Other',
    ];

    public const STATUSES = [
        'not_started' => 'Not Started',
        'planning' => 'Planning',
        'development' => 'Development',
        'testing' => 'Testing',
        'review' => 'Review',
        'completed' => 'Completed',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'workstream_id')->whereNull('parent_id');
    }

    public function getComputedProgressAttribute(): int
    {
        $total = ProjectTask::where('workstream_id', $this->id)->count();
        if ($total === 0) {
            return (int) $this->progress;
        }
        $done = ProjectTask::where('workstream_id', $this->id)->where('status', 'completed')->count();

        return (int) round($done / $total * 100);
    }
}
