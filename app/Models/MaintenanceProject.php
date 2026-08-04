<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A maintenance contract: who we look after, until when, and what gets done on a schedule.
 *
 * Kept apart from Project because the two answer different questions. A project ends; maintenance
 * is the arrangement that runs afterwards and has to be renewed. The link to a project is optional,
 * since some contracts cover work this panel never tracked.
 */
class MaintenanceProject extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'fee' => 'decimal:2',
    ];

    /** How long before it runs out we start asking for a renewal. */
    public const RENEWAL_WINDOW_DAYS = 30;

    public const STATUSES = ['active' => 'Active', 'paused' => 'Paused', 'ended' => 'Ended'];

    public const CYCLES = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'one_off' => 'One-off'];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->code ??= self::nextCode();
        });
    }

    public static function nextCode(): string
    {
        $n = static::withTrashed()->max('id') + 1;

        return 'MNT-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(MaintenanceTask::class)->orderBy('position')->orderBy('id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(MaintenanceRenewal::class)->latest('ends_on');
    }

    // ---- Where it stands ---------------------------------------------------------------------

    public function daysLeft(): int
    {
        return Carbon::today()->diffInDays($this->ends_on, false);
    }

    public function isExpired(): bool
    {
        return $this->ends_on->lt(Carbon::today());
    }

    /** Expired, or close enough that someone should be asking the client about the next term. */
    public function needsRenewal(): bool
    {
        return $this->status !== 'ended' && $this->daysLeft() <= self::RENEWAL_WINDOW_DAYS;
    }

    public function healthLabel(): string
    {
        if ($this->status === 'ended') {
            return 'Ended';
        }
        if ($this->status === 'paused') {
            return 'Paused';
        }
        if ($this->isExpired()) {
            return 'Expired';
        }
        if ($this->needsRenewal()) {
            return 'Expiring soon';
        }

        return 'Active';
    }

    /**
     * What is owed right now, per task.
     *
     * Worked out from the schedule rather than read from a table of pre-generated rows: nothing has
     * to be created in advance, so the figures are right the moment a plan is edited — and they do
     * not depend on a scheduled command, which this server does not run.
     *
     * @return Collection<int, array{task: MaintenanceTask, due_on: Carbon, days_late: int}>
     */
    public function dueTasks(?Carbon $on = null): Collection
    {
        $on = $on ?? Carbon::today();

        if ($this->status !== 'active') {
            return collect();
        }

        return $this->tasks
            ->where('is_active', true)
            ->map(function (MaintenanceTask $t) use ($on) {
                $due = $t->currentDueDate($on);
                if (! $due) {
                    return null;
                }
                // Loaded once by the caller; a task with a run for this date is done.
                $done = $t->relationLoaded('runs')
                    ? $t->runs->firstWhere(fn ($r) => $r->due_on->isSameDay($due) && $r->completed_at)
                    : $t->runs()->whereDate('due_on', $due)->whereNotNull('completed_at')->first();

                return $done ? null : [
                    'task' => $t,
                    'due_on' => $due,
                    'days_late' => (int) $due->diffInDays($on),
                ];
            })
            ->filter()
            ->sortBy('due_on')
            ->values();
    }

    public function scopeNeedingRenewal(Builder $q): Builder
    {
        return $q->where('status', '!=', 'ended')
            ->whereDate('ends_on', '<=', Carbon::today()->addDays(self::RENEWAL_WINDOW_DAYS));
    }

    /**
     * How many contracts want attention — the sidebar badge.
     *
     * Renewals plus anything overdue. Counted with the tasks eager-loaded, because working out
     * what is due is per-task arithmetic and doing it lazily would be a query per task per contract.
     */
    public static function attentionCount(): int
    {
        $rows = static::with(['tasks.runs'])->where('status', '!=', 'ended')->get();

        return $rows->filter(fn (self $m) => $m->needsRenewal() || $m->dueTasks()->isNotEmpty())->count();
    }
}
