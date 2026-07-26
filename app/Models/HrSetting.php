<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Single settings row for the HR/attendance module. */
class HrSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attendance_enabled' => 'boolean',
        'biometric_enabled' => 'boolean',
        'web_enabled' => 'boolean',
        'login_attendance_enabled' => 'boolean',
        'mobile_enabled' => 'boolean',
        'manual_enabled' => 'boolean',
        'overtime_enabled' => 'boolean',
    ];

    /** The one row, created with defaults on first use. */
    public static function current(): self
    {
        return static::first() ?? static::create([]);
    }

    /** Which of the methods the admin turned on, as method => label. */
    public function enabledMethods(): array
    {
        return array_filter([
            Attendance::METHOD_BIOMETRIC => $this->biometric_enabled ? 'Biometric' : null,
            Attendance::METHOD_WEB => $this->web_enabled ? 'Web' : null,
            Attendance::METHOD_WEB_LOGIN => $this->login_attendance_enabled ? 'Web Login' : null,
            Attendance::METHOD_MOBILE => $this->mobile_enabled ? 'Mobile' : null,
            Attendance::METHOD_MANUAL => $this->manual_enabled ? 'Manual' : null,
        ]);
    }

    public function allows(string $method): bool
    {
        return $this->attendance_enabled && array_key_exists($method, $this->enabledMethods());
    }

    /** Office start/end for a given day, as full datetimes. */
    public function officeWindow(\Carbon\CarbonInterface $day): array
    {
        return [
            $day->copy()->setTimeFromTimeString($this->office_start),
            $day->copy()->setTimeFromTimeString($this->office_end),
        ];
    }

    public function isWithinOfficeHours(\Carbon\CarbonInterface $at): bool
    {
        [$start, $end] = $this->officeWindow($at);

        return $at->betweenIncluded($start, $end);
    }
}
