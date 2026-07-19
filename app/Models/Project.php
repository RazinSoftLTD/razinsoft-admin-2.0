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
        'needs_requirements' => 'boolean',
        'prd_sections' => 'array',
    ];

    /**
     * Catalogue of PRD (requirement) sections a project can collect.
     * key => [label, hint, required?, icon path, icon tint]
     * Settings decides which are switched on; the PRD tab renders only those.
     */
    public const PRD_SECTIONS = [
        'play_store' => ['Play Store Account Information', 'Provide your Google Play Console account details.', true, 'm5 3 14 9-14 9V3Z', 'bg-emerald-50 text-emerald-600'],
        'app_store' => ['App Store Account Information', 'Provide your Apple App Store Connect account details.', true, 'M9 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2ZM11 18h2', 'bg-gray-100 text-gray-700'],
        'server' => ['Server Requirements', 'Provide your server and hosting information.', true, 'M4 5h16v5H4zM4 14h16v5H4zM7 7.5h.01M7 16.5h.01', 'bg-slate-100 text-slate-600'],
        'brand' => ['Brand Assets & Design', 'Upload logo, colors, icons and brand related assets.', true, 'M12 3a9 9 0 1 0 0 18h1a2 2 0 0 0 0-4h-.5a1.5 1.5 0 0 1 0-3H15a5 5 0 0 0 5-5c0-3.5-3.6-6-8-6ZM7.5 11h.01M10 7.5h.01M14 7h.01', 'bg-violet-50 text-violet-600'],
        'firebase' => ['Firebase Configuration', 'Provide your Firebase project configuration and keys.', false, 'M12 22a7 7 0 0 0 7-7c0-4-3-6-4-9-1 2-2 3-3 3s-1-2-1-4c-2 2-6 5-6 10a7 7 0 0 0 7 7Z', 'bg-amber-50 text-amber-600'],
        'domain' => ['Domain & Website Information', 'Provide domain, website and related access details.', false, 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z', 'bg-blue-50 text-blue-600'],
        'email' => ['System Email Configuration', 'Provide SMTP and email configuration for system.', false, 'M3 6h18v12H3zM3 7l9 6 9-6', 'bg-sky-50 text-sky-600'],
        'api_keys' => ['Third-party API Keys', 'Provide API keys for third-party services.', false, 'M15 7a4 4 0 1 1-3.9 5H8v3H5v-3H3v-3h8.1A4 4 0 0 1 15 7Z', 'bg-yellow-50 text-yellow-600'],
        'files' => ['Project Files & Documents', 'Upload design files, documents and other project files.', false, 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z', 'bg-orange-50 text-orange-600'],
        'notes' => ['Additional Notes', 'Any additional information or special instructions.', false, 'M8 3h8a2 2 0 0 1 2 2v14l-6-3-6 3V5a2 2 0 0 1 2-2Z', 'bg-indigo-50 text-indigo-600'],
    ];

    /** Section keys switched on for this project (only ones that still exist in the catalogue). */
    public function prdSectionKeys(): array
    {
        return array_values(array_intersect(
            array_keys(self::PRD_SECTIONS),
            (array) ($this->prd_sections ?? [])
        ));
    }

    /** Public URL clients use to fill in the PRD (null until the link is shared). */
    public function prdShareUrl(): ?string
    {
        return $this->prd_share_token ? route('prd.public', $this->prd_share_token) : null;
    }

    /** Everything submitted against this project's PRD sections. */
    public function prdItems(): HasMany
    {
        return $this->hasMany(ProjectPrdItem::class)->latest();
    }

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

    /** Column keys that mean "waiting for review/approval". */
    public function reviewKeys(): array
    {
        $cols = $this->relationLoaded('columns') ? $this->columns : $this->columns()->get();

        return $cols->where('is_review', true)->pluck('key')->all();
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
