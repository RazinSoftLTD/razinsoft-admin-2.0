<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One repeating job in a maintenance plan — "back up the database daily", "check the logs weekly".
 *
 * The row is the rule, not the occurrence. Occurrences are worked out from the dates on demand, so
 * a year of daily backups is one row and nothing has to be generated ahead of time. That also means
 * the panel is correct without a scheduler, which this server does not run.
 */
class MaintenanceTask extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'weekday' => 'integer',
        'day_of_month' => 'integer',
    ];

    public const FREQUENCIES = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];

    public function maintenanceProject(): BelongsTo
    {
        return $this->belongsTo(MaintenanceProject::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(MaintenanceTaskRun::class);
    }

    /** How often, in words, for the list. */
    public function scheduleLabel(): string
    {
        return match ($this->frequency) {
            'weekly' => 'Every '.Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($this->weekday ?? 1)->format('l'),
            'monthly' => 'Day '.($this->day_of_month ?? 1).' of each month',
            default => 'Every day',
        };
    }

    /**
     * The occurrence due on or before $on — the one currently owed.
     *
     * Clamped to the contract's own dates: an occurrence before the contract started, or after it
     * ended, is not owed to anyone. Returns null when the task has no occurrence yet.
     */
    public function currentDueDate(?Carbon $on = null): ?Carbon
    {
        $on = ($on ?? Carbon::today())->copy()->startOfDay();
        $contract = $this->maintenanceProject;
        $start = $contract->starts_on->copy()->startOfDay();
        $end = $contract->ends_on->copy()->startOfDay();

        if ($on->lt($start)) {
            return null;
        }
        // Past the end of the contract nothing new falls due, but the last occurrence inside it
        // still counts as owed until someone ticks it off.
        $on = $on->min($end);

        $due = match ($this->frequency) {
            'weekly' => $this->lastWeekdayOnOrBefore($on),
            'monthly' => $this->lastMonthDayOnOrBefore($on),
            default => $on,
        };

        return $due && $due->gte($start) ? $due : null;
    }

    private function lastWeekdayOnOrBefore(Carbon $on): Carbon
    {
        $target = $this->weekday ?? 1;
        $diff = ($on->dayOfWeek - $target + 7) % 7;

        return $on->copy()->subDays($diff);
    }

    private function lastMonthDayOnOrBefore(Carbon $on): Carbon
    {
        $day = $this->day_of_month ?? 1;
        // A task set for the 31st still has to fall due in February, so it lands on the last day
        // of any month that is too short rather than being skipped.
        $thisMonth = $on->copy()->startOfMonth()->addDays(min($day, $on->daysInMonth) - 1);

        if ($thisMonth->lte($on)) {
            return $thisMonth;
        }

        $prev = $on->copy()->subMonthNoOverflow()->startOfMonth();

        return $prev->addDays(min($day, $prev->daysInMonth) - 1);
    }
}
