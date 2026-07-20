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

    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];

    /** Label colours offered in the milestone form. */
    public const COLORS = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#94a3b8'];

    /** key => svg path, for the icon picker. */
    public const ICONS = [
        'flag' => 'M5 21V4m0 0h11l-1.5 3.5L16 11H5',
        'target' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-5a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0-3a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z',
        'trophy' => 'M8 4h8v5a4 4 0 0 1-8 0V4ZM6 5H4v2a3 3 0 0 0 3 3M18 5h2v2a3 3 0 0 1-3 3M9 20h6M12 13v7',
        'star' => 'm12 3 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5-5.8-3-5.8 3 1.1-6.5L2.6 9.8l6.5-.9L12 3Z',
        'rocket' => 'M5 15l-2 6 6-2M9 15l6-6a6 6 0 0 0 4-6 6 6 0 0 0-6 4l-6 6 2 2ZM14 8h.01',
        'diamond' => 'm12 3 9 9-9 9-9-9 9-9Z',
        'calendar' => 'M4 6h16v14H4zM4 10h16M8 3v3M16 3v3',
    ];

    public function iconPath(): string
    {
        return self::ICONS[$this->icon] ?? self::ICONS['flag'];
    }

    public function labelColor(): string
    {
        return $this->color ?: self::COLORS[0];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'milestone_id');
    }
}
