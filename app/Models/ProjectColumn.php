<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectColumn extends Model
{
    protected $guarded = [];

    protected $casts = ['is_done' => 'boolean', 'is_excluded' => 'boolean'];

    /** The out-of-the-box columns, reused when a project is created. */
    public const DEFAULTS = [
        ['key' => 'backlog', 'name' => 'Backlog', 'color' => '#94a3b8', 'is_done' => false, 'is_excluded' => false],
        ['key' => 'todo', 'name' => 'To Do', 'color' => '#0ea5e9', 'is_done' => false, 'is_excluded' => false],
        ['key' => 'in_progress', 'name' => 'In Progress', 'color' => '#3b82f6', 'is_done' => false, 'is_excluded' => false],
        ['key' => 'review', 'name' => 'Review', 'color' => '#a855f7', 'is_done' => false, 'is_excluded' => false],
        ['key' => 'completed', 'name' => 'Done', 'color' => '#10b981', 'is_done' => true, 'is_excluded' => false],
        ['key' => 'cancelled', 'name' => 'Cancelled', 'color' => '#9ca3af', 'is_done' => false, 'is_excluded' => true],
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Global default columns (project_id null) as a template collection. */
    public static function defaults()
    {
        $rows = static::whereNull('project_id')->orderBy('position')->get();

        return $rows->isNotEmpty() ? $rows : collect(self::DEFAULTS)->map(fn ($c, $i) => new self($c + ['position' => $i]));
    }
}
