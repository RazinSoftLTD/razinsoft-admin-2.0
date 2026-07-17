<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'deadline' => 'date',
        'budget' => 'decimal:2',
        'auto_progress' => 'boolean',
        'progress' => 'integer',
        'hours_allocated' => 'integer',
    ];

    /** Desk-style lifecycle. "Overdue" is derived from the deadline, not stored. */
    public const STATUSES = [
        'todo' => 'Todo',
        'in_progress' => 'In Progress',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];

    public const CATEGORIES = [
        'Website Development', 'Mobile App Development', 'Full Web + Mobile Solution',
        'UI/UX Design', 'Installation & Setup', 'App Publishing', 'Customization',
        'Maintenance & Support', 'API Integration', 'Server / DevOps', 'Marketing', 'Other',
    ];

    public const CLOSED_STATUSES = ['completed', 'cancelled'];

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (empty($project->code)) {
                $project->code = 'PRJ-'.str_pad((string) ((self::withTrashed()->max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT);
            }
        });

        // Copy the global default board columns onto every new project.
        static::created(function (Project $project) {
            foreach (ProjectColumn::defaults() as $i => $col) {
                $project->columns()->create([
                    'key' => $col->key, 'name' => $col->name, 'color' => $col->color,
                    'position' => $col->position ?? $i, 'is_done' => $col->is_done, 'is_excluded' => $col->is_excluded,
                ]);
            }
        });
    }

    // ---------------------------------------------------------------- relations

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('end_date')->orderBy('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class)->latest();
    }

    /** Top-level tasks only (subtasks hang off their parent). */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->whereNull('parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function allTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivityLog::class)->latest();
    }

    public function columns(): HasMany
    {
        return $this->hasMany(ProjectColumn::class)->orderBy('position')->orderBy('id');
    }

    /** [key => name] map of this project's board columns (falls back to defaults). */
    public function statusOptions(): array
    {
        $cols = $this->relationLoaded('columns') ? $this->columns : $this->columns()->get();
        if ($cols->isEmpty()) {
            $cols = ProjectColumn::defaults();
        }

        return $cols->pluck('name', 'key')->all();
    }

    /** Column keys that count as "done". */
    public function doneKeys(): array
    {
        $cols = $this->relationLoaded('columns') ? $this->columns : $this->columns()->get();

        return $cols->where('is_done', true)->pluck('key')->all() ?: ['completed'];
    }

    /** Column keys excluded from progress (e.g. Cancelled). */
    public function excludedKeys(): array
    {
        $cols = $this->relationLoaded('columns') ? $this->columns : $this->columns()->get();

        return $cols->where('is_excluded', true)->pluck('key')->all() ?: ['cancelled'];
    }

    // ---------------------------------------------------------------- scopes

    /** Permission scope: all, or only the projects I manage / created / belong to. */
    public function scopeVisibleTo($q, User $user)
    {
        $scope = $user->permissionScope('projects', 'view');
        if ($scope === 'none') {
            return $q->whereRaw('1 = 0');
        }
        if ($scope !== 'all') {
            $q->where(function ($w) use ($user, $scope) {
                if (in_array($scope, ['owned', 'both'], true)) {
                    $w->orWhere('project_manager_id', $user->id);
                }
                if (in_array($scope, ['added', 'both'], true)) {
                    $w->orWhere('created_by', $user->id);
                }
                $w->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id));
            });
        }

        return $q;
    }

    // ---------------------------------------------------------------- helpers

    /** % complete — from tasks when auto, else the manual slider value. */
    public function progressPercent(): int
    {
        if (! $this->auto_progress) {
            return min(100, max(0, (int) $this->progress));
        }
        $excluded = $this->excludedKeys();
        $total = $this->allTasks()->whereNull('parent_id')->whereNotIn('status', $excluded)->count();
        if ($total === 0) {
            return 0;
        }
        $done = $this->allTasks()->whereNull('parent_id')->whereIn('status', $this->doneKeys())->count();

        return (int) round($done / $total * 100);
    }

    public function isOverdue(): bool
    {
        return $this->deadline && $this->deadline->isPast() && ! in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function log(string $action, string $description, ?int $userId = null): void
    {
        $this->activities()->create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'description' => mb_substr($description, 0, 500),
        ]);
    }
}
