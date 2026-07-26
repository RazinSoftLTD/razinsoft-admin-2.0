<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One employee's attendance for one day. Whichever method arrives first fills the check-in;
 * later methods only fill what is still blank, which is how duplicate attendance is avoided.
 */
class Attendance extends Model
{
    public const METHOD_BIOMETRIC = 'biometric';
    public const METHOD_WEB = 'web';
    public const METHOD_WEB_LOGIN = 'web_login';
    public const METHOD_MOBILE = 'mobile';
    public const METHOD_MANUAL = 'manual';

    public const METHODS = [
        self::METHOD_BIOMETRIC => 'Biometric',
        self::METHOD_WEB => 'Web',
        self::METHOD_WEB_LOGIN => 'Web Login',
        self::METHOD_MOBILE => 'Mobile',
        self::METHOD_MANUAL => 'Manual',
    ];

    public const STATUSES = [
        'present' => 'Present',
        'late' => 'Late',
        'half_day' => 'Half Day',
        'absent' => 'Absent',
    ];

    protected $guarded = [];

    protected $casts = [
        'work_date' => 'date:Y-m-d',   // stored as a plain date so lookups match in MySQL and SQLite alike
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function isCheckedIn(): bool
    {
        return $this->check_in_at !== null;
    }

    public function isCheckedOut(): bool
    {
        return $this->check_out_at !== null;
    }

    public function methodLabel(?string $method): string
    {
        return self::METHODS[$method] ?? '—';
    }

    /** Worked time, lateness and overtime, derived from the two stamps and the HR settings. */
    public function recalculate(?HrSetting $settings = null): void
    {
        $settings ??= HrSetting::current();

        // Carbon 3 returns a float here — these columns are whole minutes.
        $worked = ($this->check_in_at && $this->check_out_at && $this->check_out_at->gt($this->check_in_at))
            ? (int) floor($this->check_in_at->diffInMinutes($this->check_out_at))
            : 0;

        [$start] = $settings->officeWindow($this->work_date);
        $graceEnd = $start->copy()->addMinutes($settings->grace_minutes);
        $late = ($this->check_in_at && $this->check_in_at->gt($graceEnd))
            ? (int) floor($graceEnd->diffInMinutes($this->check_in_at))
            : 0;

        $overtime = ($settings->overtime_enabled && $worked > $settings->overtime_after_minutes)
            ? (int) ($worked - $settings->overtime_after_minutes)
            : 0;

        // Status only firms up once the day is closed out; an open check-in stays present/late.
        $status = match (true) {
            ! $this->check_in_at => 'absent',
            $this->check_out_at && $worked < $settings->half_day_minutes => 'half_day',
            $late > 0 => 'late',
            default => 'present',
        };

        $this->forceFill([
            'worked_minutes' => $worked,
            'late_minutes' => $late,
            'overtime_minutes' => $overtime,
            'status' => $status,
        ]);
    }

    /** "8h 12m" */
    public function workedLabel(): string
    {
        return self::minutesLabel($this->worked_minutes);
    }

    public static function minutesLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return '—';
        }

        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }
}
