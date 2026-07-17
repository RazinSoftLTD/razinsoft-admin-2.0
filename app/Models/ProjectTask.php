<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTask extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['start_date' => 'date', 'due_date' => 'date', 'completed_at' => 'datetime'];

    /** Board columns, in order (desk-style). */
    public const STATUSES = [
        'backlog' => 'Backlog',
        'todo' => 'Todo',
        'in_progress' => 'In Progress',
        'review' => 'Review',
        'completed' => 'Done',
        'cancelled' => 'Cancelled',
    ];

    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectTaskComment::class, 'task_id')->oldest();
    }

    // ---------------------------------------------------------------- helpers

    public function code(): string
    {
        return ($this->project?->code ?: 'TASK').'-'.$this->id;
    }

    public function isOverdue(): bool
    {
        return $this->due_date && ! in_array($this->status, ['completed', 'cancelled'], true) && $this->due_date->isPast();
    }

    /** Human label + colour for this task's status, resolved from the project's columns. */
    public function statusLabel(): string
    {
        return $this->project?->statusOptions()[$this->status] ?? (self::STATUSES[$this->status] ?? ucfirst($this->status));
    }

    public function statusColor(): string
    {
        $col = $this->project?->columns->firstWhere('key', $this->status);

        return $col->color ?? '#94a3b8';
    }

    /** "3h 30m" style label for the estimate. */
    public function estimateLabel(): ?string
    {
        if (! $this->estimated_minutes) {
            return null;
        }
        $h = intdiv($this->estimated_minutes, 60);
        $m = $this->estimated_minutes % 60;

        return trim(($h ? "{$h}h " : '').($m ? "{$m}m" : '')) ?: null;
    }

    /** Keep completed_at in sync whenever the status changes. Pass the project's
     *  done-column keys so custom "done" columns also stamp the completion date. */
    public function applyStatus(string $status, ?array $doneKeys = null): void
    {
        $doneKeys ??= ['completed'];
        $this->status = $status;
        $this->completed_at = in_array($status, $doneKeys, true) ? ($this->completed_at ?? now()) : null;
    }
}
