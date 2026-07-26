<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\HrSetting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Every attendance method funnels through here, so the "one record per employee per day"
 * rule and the working-hours maths live in exactly one place. Punches are always logged,
 * even when they don't change the day's record — that's the audit trail.
 */
class AttendanceRecorder
{
    /** Outcome of a punch: what happened, and the record it applied to. */
    public const DID_CHECK_IN = 'checked_in';
    public const DID_CHECK_OUT = 'checked_out';
    public const ALREADY = 'already';

    /**
     * Record a punch. `$direction` may be 'in', 'out' or null to let the state decide
     * (blank check-in → in, otherwise → out), which is how biometric readers behave.
     *
     * @return array{result: string, attendance: Attendance, message: string}
     */
    public static function punch(
        User $user,
        string $method,
        ?string $direction = null,
        ?CarbonInterface $at = null,
        array $meta = [],
        ?AttendanceDevice $device = null,
    ): array {
        $settings = HrSetting::current();
        $at = $at ? Carbon::parse($at) : now();
        $date = $at->copy()->startOfDay();

        $attendance = Attendance::firstOrNew(['user_id' => $user->id, 'work_date' => $date->toDateString()]);

        // Decide the side of the punch when the caller didn't say.
        $direction ??= $attendance->check_in_at ? 'out' : 'in';

        $result = self::ALREADY;
        $message = 'Attendance already recorded today.';

        if ($direction === 'in') {
            if (! $attendance->check_in_at) {
                $attendance->check_in_at = $at;
                $attendance->check_in_method = $method;
                $result = self::DID_CHECK_IN;
                $message = 'Checked in at '.$at->format('g:i A').'.';
            }
        } else {
            if (! $attendance->check_in_at) {
                // A check-out with nothing to close: treat the punch as the check-in instead.
                $attendance->check_in_at = $at;
                $attendance->check_in_method = $method;
                $result = self::DID_CHECK_IN;
                $message = 'Checked in at '.$at->format('g:i A').'.';
            } elseif (! $attendance->check_out_at) {
                $attendance->check_out_at = $at;
                $attendance->check_out_method = $method;
                $result = self::DID_CHECK_OUT;
                $message = 'Checked out at '.$at->format('g:i A').'.';
            } else {
                // Later punches only extend the day, they never open a second record.
                if ($at->gt($attendance->check_out_at)) {
                    $attendance->check_out_at = $at;
                    $attendance->check_out_method = $method;
                    $result = self::DID_CHECK_OUT;
                    $message = 'Check-out updated to '.$at->format('g:i A').'.';
                } else {
                    $message = 'Attendance already recorded today.';
                }
            }
        }

        $attendance->recalculate($settings);
        $attendance->save();

        AttendanceLog::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'device_id' => $device?->id,
            'method' => $method,
            'direction' => $direction,
            'punched_at' => $at,
            'biometric_id' => $meta['biometric_id'] ?? null,
            'ip_address' => $meta['ip_address'] ?? null,
            'device_type' => $meta['device_type'] ?? null,
            'browser' => $meta['browser'] ?? null,
            'user_agent' => $meta['user_agent'] ?? null,
            'raw' => $meta['raw'] ?? null,
        ]);

        return ['result' => $result, 'attendance' => $attendance, 'message' => $message];
    }

    /**
     * The first successful login of the day becomes the check-in, when HR enabled it and the
     * login lands inside office hours. Never creates a check-out.
     */
    public static function fromLogin(User $user, Request $request): ?array
    {
        $settings = HrSetting::current();

        if (! $settings->allows(Attendance::METHOD_WEB_LOGIN) || ! $user->isPanelUser()) {
            return null;
        }
        if (! $settings->isWithinOfficeHours(now())) {
            return null;                       // outside office hours it does not create attendance
        }

        $existing = Attendance::where('user_id', $user->id)->whereDate('work_date', today())->first();
        if ($existing && $existing->check_in_at) {
            return null;                       // biometric or web already got there first
        }

        return self::punch($user, Attendance::METHOD_WEB_LOGIN, 'in', now(), self::webMeta($request));
    }

    /** IP / browser / device details recorded with a web punch. */
    public static function webMeta(Request $request): array
    {
        $agent = (string) $request->userAgent();

        return [
            'ip_address' => $request->ip(),
            'user_agent' => $agent,
            'browser' => self::browserFrom($agent),
            'device_type' => self::deviceTypeFrom($agent),
        ];
    }

    private static function browserFrom(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Unknown',
        };
    }

    private static function deviceTypeFrom(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet/i', $agent) => 'tablet',
            (bool) preg_match('/Mobile|Android|iPhone/i', $agent) => 'mobile',
            default => 'desktop',
        };
    }

    /**
     * Ingest raw punches from a biometric device. Each row needs a biometric id and a
     * timestamp; direction is optional and inferred when absent. Returns a small summary.
     *
     * @param  array<int, array{biometric_id: string, punched_at: string, direction?: string, raw?: string}>  $rows
     */
    public static function ingestDeviceLogs(AttendanceDevice $device, array $rows): array
    {
        $matched = $skipped = 0;
        $unknown = [];

        // One lookup for every biometric id in the batch.
        $ids = collect($rows)->pluck('biometric_id')->filter()->map(fn ($v) => trim((string) $v))->unique();
        $users = User::whereIn('biometric_id', $ids)->get()->keyBy(fn ($u) => trim((string) $u->biometric_id));

        foreach ($rows as $row) {
            $bid = trim((string) ($row['biometric_id'] ?? ''));
            $user = $users[$bid] ?? null;

            if (! $user) {
                $skipped++;
                if ($bid !== '' && ! in_array($bid, $unknown, true)) {
                    $unknown[] = $bid;
                }
                // Still logged, so nothing from the device is silently lost.
                AttendanceLog::create([
                    'device_id' => $device->id,
                    'method' => Attendance::METHOD_BIOMETRIC,
                    'direction' => $row['direction'] ?? null,
                    'punched_at' => Carbon::parse($row['punched_at']),
                    'biometric_id' => $bid ?: null,
                    'raw' => $row['raw'] ?? json_encode($row),
                ]);

                continue;
            }

            self::punch(
                $user,
                Attendance::METHOD_BIOMETRIC,
                $row['direction'] ?? null,
                Carbon::parse($row['punched_at']),
                ['biometric_id' => $bid, 'raw' => $row['raw'] ?? json_encode($row)],
                $device,
            );
            $matched++;
        }

        $device->forceFill(['last_sync_at' => now()])->save();

        return ['matched' => $matched, 'skipped' => $skipped, 'unknown_ids' => $unknown];
    }
}
