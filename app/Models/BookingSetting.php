<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BookingSetting extends Model
{
    protected $fillable = [
        'start_time', 'end_time', 'slot_minutes', 'working_days',
        'advance_days', 'lead_hours', 'is_enabled', 'default_assignee_id',
    ];

    protected $casts = [
        'working_days' => 'array',
        'is_enabled' => 'boolean',
    ];

    /** The single settings row (created with sensible defaults on first use). */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
            'slot_minutes' => 120,
            'working_days' => [0, 1, 2, 3, 4], // Sun–Thu
            'advance_days' => 30,
            'lead_hours' => 2,
            'is_enabled' => true,
        ]);
    }

    public function defaultAssignee()
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    public function workingDays(): array
    {
        return $this->working_days ?: [0, 1, 2, 3, 4];
    }

    public function isWorkingDay(Carbon $date): bool
    {
        return in_array((int) $date->dayOfWeek, $this->workingDays(), true);
    }

    /** All slot windows in a day, e.g. [['10:00','12:00'], ['12:00','14:00'], …]. */
    public function slotWindows(): array
    {
        $start = Carbon::createFromFormat('H:i:s', $this->start_time);
        $end = Carbon::createFromFormat('H:i:s', $this->end_time);
        $step = max(15, (int) $this->slot_minutes);

        $windows = [];
        $cursor = $start->copy();
        while ($cursor->copy()->addMinutes($step)->lessThanOrEqualTo($end)) {
            $slotEnd = $cursor->copy()->addMinutes($step);
            $windows[] = [$cursor->format('H:i'), $slotEnd->format('H:i')];
            $cursor = $slotEnd;
        }

        return $windows;
    }

    /** Available slots for a given date, marking which are already taken / in the past. */
    public function slotsFor(Carbon $date): array
    {
        if (! $this->is_enabled || ! $this->isWorkingDay($date)) {
            return [];
        }

        $taken = Meeting::whereDate('date', $date->toDateString())
            ->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->pluck('start_time')
            ->map(fn ($t) => substr($t, 0, 5))
            ->all();

        $cutoff = now()->addHours((int) $this->lead_hours);

        return collect($this->slotWindows())->map(function ($w) use ($date, $taken, $cutoff) {
            [$s, $e] = $w;
            $slotStart = Carbon::parse($date->toDateString().' '.$s);
            $available = ! in_array($s, $taken, true) && $slotStart->greaterThanOrEqualTo($cutoff);

            return [
                'start' => $s,
                'end' => $e,
                'label' => Carbon::parse($s)->format('g:i A').' – '.Carbon::parse($e)->format('g:i A'),
                'available' => $available,
            ];
        })->all();
    }
}
