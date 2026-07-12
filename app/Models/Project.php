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
        'budget' => 'decimal:2',
        'start_date' => 'date',
        'expected_delivery' => 'date',
        'actual_delivery' => 'date',
        'progress' => 'integer',
    ];

    /** Project types drive the default checklist templates & deliverables. */
    public const TYPES = [
        'New Software Development', 'Existing Software Enhancement', 'Installation Service',
        'App Publish Service', 'Website Development', 'Mobile App Development',
        'Full Web + Mobile Solution', 'UI/UX Design', 'Maintenance', 'Bug Fixing',
        'API Integration', 'Server Migration', 'Consultation',
    ];

    /** Full delivery lifecycle. */
    public const STATUSES = [
        'draft' => 'Draft',
        'requirement_collection' => 'Requirement Collection',
        'requirements_pending' => 'Requirements Pending',
        'planning' => 'Planning',
        'development' => 'Development',
        'internal_testing' => 'Internal Testing',
        'client_review' => 'Client Review',
        'bug_fixing' => 'Bug Fixing',
        'deployment' => 'Deployment',
        'delivered' => 'Delivered',
        'maintenance' => 'Maintenance',
        'completed' => 'Completed',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
    ];

    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];

    /** Statuses that count as finished (for stats). */
    public const CLOSED_STATUSES = ['completed', 'cancelled'];

    public const DOCUMENT_TYPES = [
        'Requirement', 'Proposal', 'Quotation', 'Invoice', 'Agreement', 'NDA', 'Design',
        'APK', 'IPA', 'Source Code', 'Meeting Notes', 'Credentials', 'Others',
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (empty($project->code)) {
                $project->code = 'PRJ-'.now()->format('y').str_pad((string) (self::withTrashed()->max('id') + 1), 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_person_id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function workstreams(): HasMany
    {
        return $this->hasMany(ProjectWorkstream::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Only top-level tasks (subtasks hang off their parent). */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->whereNull('parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function allTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ProjectChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class)->latest('id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ProjectChangeRequest::class)->latest('id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ProjectActivityLog::class)->latest('id');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /** Progress computed from completed tasks; falls back to the manual field when there are no tasks. */
    public function getComputedProgressAttribute(): int
    {
        $total = $this->allTasks()->count();
        if ($total === 0) {
            return (int) $this->progress;
        }
        $done = $this->allTasks()->where('status', 'completed')->count();

        return (int) round($done / $total * 100);
    }

    public function log(string $action, string $description, ?int $userId = null): void
    {
        $this->activityLogs()->create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'description' => $description,
        ]);
    }
}
