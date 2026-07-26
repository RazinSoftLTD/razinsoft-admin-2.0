<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\HrSetting;
use App\Models\User;
use App\Support\AttendanceRecorder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Attendance — the day's board, each employee's history, the web check-in/out buttons,
 * manual HR entries, biometric devices and the HR settings that switch methods on and off.
 */
class AttendanceController extends Controller
{
    /** Today's board plus my own check-in/out card. */
    public function index(Request $request)
    {
        $this->can($request, 'view');
        $settings = HrSetting::current();

        $date = $request->date('date') ?: today();
        $scopeAll = $request->user()->seesAll('attendance');

        $rows = Attendance::with('user:id,name,designation_id', 'user.designation:id,name', 'markedBy:id,name')
            ->whereDate('work_date', $date)
            ->when(! $scopeAll, fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('method'), fn ($q) => $q->where('check_in_method', $request->query('method')))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$request->query('search').'%')))
            ->get()
            ->sortBy(fn ($a) => $a->user?->name);

        // Everyone who should have been in today, so absences are visible too.
        $staff = $scopeAll
            ? User::assignable()->orderBy('name')->get(['id', 'name', 'designation_id'])
            : User::whereKey($request->user()->id)->get(['id', 'name', 'designation_id']);

        return view('admin.attendance.index', [
            'settings' => $settings,
            'date' => $date,
            'rows' => $rows,
            'staff' => $staff,
            'mine' => Attendance::where('user_id', $request->user()->id)->whereDate('work_date', today())->first(),
            'scopeAll' => $scopeAll,
            'stats' => [
                'present' => $rows->whereIn('status', ['present', 'late'])->count(),
                'late' => $rows->where('status', 'late')->count(),
                'half_day' => $rows->where('status', 'half_day')->count(),
                'absent' => max(0, $staff->count() - $rows->count()),
            ],
        ]);
    }

    /** Longer history with filters, across employees or just mine. */
    public function history(Request $request)
    {
        $this->can($request, 'view');
        $scopeAll = $request->user()->seesAll('attendance');

        $from = $request->query('from') ?: today()->copy()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: today()->toDateString();

        $rows = Attendance::with('user:id,name', 'markedBy:id,name')
            ->whereBetween('work_date', [$from, $to])
            ->when(! $scopeAll, fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->query('user')))
            ->when($request->filled('method'), fn ($q) => $q->where('check_in_method', $request->query('method')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('work_date')->orderBy('user_id')
            ->paginate(30)->withQueryString();

        return view('admin.attendance.history', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'scopeAll' => $scopeAll,
            'staff' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'totals' => [
                'days' => $rows->total(),
                'worked' => Attendance::whereBetween('work_date', [$from, $to])
                    ->when(! $scopeAll, fn ($q) => $q->where('user_id', $request->user()->id))
                    ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->query('user')))
                    ->sum('worked_minutes'),
            ],
        ]);
    }

    // ===================================================================== web check in/out

    public function checkIn(Request $request)
    {
        return $this->webPunch($request, 'in');
    }

    public function checkOut(Request $request)
    {
        return $this->webPunch($request, 'out');
    }

    private function webPunch(Request $request, string $direction)
    {
        $settings = HrSetting::current();

        if (! $settings->allows(Attendance::METHOD_WEB)) {
            return back()->with('error', 'Web check-in is turned off in HR Settings.');
        }

        $outcome = AttendanceRecorder::punch(
            $request->user(),
            Attendance::METHOD_WEB,
            $direction,
            now(),
            AttendanceRecorder::webMeta($request),
        );

        // "Attendance already recorded today." is the expected message when another method won.
        return back()->with($outcome['result'] === AttendanceRecorder::ALREADY ? 'error' : 'status', $outcome['message']);
    }

    // ===================================================================== manual entry (HR/admin)

    public function manualStore(Request $request)
    {
        $this->can($request, 'create');
        $settings = HrSetting::current();
        abort_unless($settings->allows(Attendance::METHOD_MANUAL), 403, 'Manual attendance is turned off.');

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'work_date' => ['required', 'date'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'status' => ['nullable', Rule::in(array_keys(Attendance::STATUSES))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $date = \Illuminate\Support\Carbon::parse($data['work_date'])->startOfDay();
        $attendance = Attendance::firstOrNew(['user_id' => $data['user_id'], 'work_date' => $date->toDateString()]);

        if (! empty($data['check_in'])) {
            $attendance->check_in_at = $date->copy()->setTimeFromTimeString($data['check_in']);
            $attendance->check_in_method = Attendance::METHOD_MANUAL;
        }
        if (! empty($data['check_out'])) {
            $attendance->check_out_at = $date->copy()->setTimeFromTimeString($data['check_out']);
            $attendance->check_out_method = Attendance::METHOD_MANUAL;
        }
        $attendance->notes = $data['notes'] ?? $attendance->notes;
        $attendance->marked_by = $request->user()->id;
        $attendance->recalculate($settings);
        if (! empty($data['status'])) {
            $attendance->status = $data['status'];              // HR can override the derived status
        }
        $attendance->save();

        AttendanceLog::create([
            'user_id' => $attendance->user_id,
            'attendance_id' => $attendance->id,
            'method' => Attendance::METHOD_MANUAL,
            'direction' => ! empty($data['check_out']) ? 'out' : 'in',
            'punched_at' => now(),
            'ip_address' => $request->ip(),
            'raw' => 'Manual entry by '.$request->user()->name,
        ]);

        return back()->with('status', 'Attendance saved.');
    }

    public function destroy(Request $request, Attendance $attendance)
    {
        $this->can($request, 'delete');
        $attendance->delete();

        return back()->with('status', 'Attendance record removed.');
    }

    // ===================================================================== biometric devices

    public function devices(Request $request)
    {
        $this->can($request, 'settings');

        return view('admin.attendance.devices', [
            'devices' => AttendanceDevice::orderBy('name')->get(),
            'settings' => HrSetting::current(),
            'unmatched' => AttendanceLog::whereNull('user_id')->whereNotNull('biometric_id')
                ->select('biometric_id')->distinct()->limit(20)->pluck('biometric_id'),
            'recentLogs' => AttendanceLog::with('user:id,name', 'device:id,name')
                ->where('method', Attendance::METHOD_BIOMETRIC)->latest('punched_at')->limit(25)->get(),
            'staff' => User::assignable()->orderBy('name')->get(['id', 'name', 'biometric_id']),
        ]);
    }

    public function deviceStore(Request $request)
    {
        $this->can($request, 'settings');
        AttendanceDevice::create($this->validatedDevice($request));

        return back()->with('status', 'Device added. Use its API token on the sync bridge.');
    }

    public function deviceUpdate(Request $request, AttendanceDevice $device)
    {
        $this->can($request, 'settings');
        $device->update($this->validatedDevice($request));

        return back()->with('status', 'Device updated.');
    }

    public function deviceDestroy(Request $request, AttendanceDevice $device)
    {
        $this->can($request, 'settings');
        $device->delete();

        return back()->with('status', 'Device removed.');
    }

    private function validatedDevice(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'brand' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    /** Map an employee to the id their fingerprint is enrolled under. */
    public function assignBiometricId(Request $request)
    {
        $this->can($request, 'settings');
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'biometric_id' => ['nullable', 'string', 'max:40'],
        ]);

        User::whereKey($data['user_id'])->update(['biometric_id' => $data['biometric_id'] ?: null]);

        return back()->with('status', 'Biometric ID saved.');
    }

    /** Paste or upload the device's log export (CSV / JSON) and turn it into attendance. */
    public function deviceImport(Request $request, AttendanceDevice $device)
    {
        $this->can($request, 'settings');
        $request->validate([
            'payload' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:5120'],
        ]);

        $text = $request->hasFile('file')
            ? file_get_contents($request->file('file')->getRealPath())
            : (string) $request->input('payload');

        $rows = self::parseDeviceLogs($text);
        if (! $rows) {
            return back()->with('error', 'Could not read any punches — expected CSV "biometric_id,datetime[,in|out]" or a JSON array.');
        }

        $summary = AttendanceRecorder::ingestDeviceLogs($device, $rows);
        $msg = "Imported {$summary['matched']} punch(es).";
        if ($summary['skipped']) {
            $msg .= " {$summary['skipped']} skipped — unmatched IDs: ".implode(', ', array_slice($summary['unknown_ids'], 0, 8)).'.';
        }

        return back()->with($summary['matched'] ? 'status' : 'error', $msg);
    }

    /**
     * Accepts either a JSON array of objects or CSV lines. Kept lenient on purpose:
     * ZKTeco exports vary, and an on-site bridge posting JSON should just work.
     *
     * @return array<int, array{biometric_id: string, punched_at: string, direction: ?string, raw: string}>
     */
    public static function parseDeviceLogs(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $rows = [];

        if (str_starts_with($text, '[') || str_starts_with($text, '{')) {
            $json = json_decode($text, true);
            foreach (($json['logs'] ?? $json ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $id = $item['biometric_id'] ?? $item['user_id'] ?? $item['pin'] ?? $item['id'] ?? null;
                $at = $item['punched_at'] ?? $item['timestamp'] ?? $item['time'] ?? $item['datetime'] ?? null;
                if ($id === null || $at === null) {
                    continue;
                }
                $rows[] = [
                    'biometric_id' => (string) $id,
                    'punched_at' => (string) $at,
                    'direction' => self::normaliseDirection($item['direction'] ?? $item['state'] ?? $item['status'] ?? null),
                    'raw' => json_encode($item),
                ];
            }

            return $rows;
        }

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'biometric') === 0) {     // skip a header row
                continue;
            }
            $parts = array_map('trim', preg_split('/[,;\t]/', $line));
            if (count($parts) < 2) {
                continue;
            }
            $rows[] = [
                'biometric_id' => $parts[0],
                'punched_at' => $parts[1],
                'direction' => self::normaliseDirection($parts[2] ?? null),
                'raw' => $line,
            ];
        }

        return $rows;
    }

    private static function normaliseDirection(mixed $value): ?string
    {
        $v = strtolower(trim((string) $value));

        return match (true) {
            in_array($v, ['in', 'i', '0', 'checkin', 'check-in', 'check_in'], true) => 'in',
            in_array($v, ['out', 'o', '1', 'checkout', 'check-out', 'check_out'], true) => 'out',
            default => null,                     // let the recorder infer it
        };
    }

    // ===================================================================== HR settings

    public function settings(Request $request)
    {
        $this->can($request, 'settings');

        return view('admin.attendance.settings', ['settings' => HrSetting::current()]);
    }

    public function settingsUpdate(Request $request)
    {
        $this->can($request, 'settings');

        $data = $request->validate([
            'office_start' => ['required', 'date_format:H:i'],
            'office_end' => ['required', 'date_format:H:i', 'after:office_start'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'min_work_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'half_day_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'overtime_after_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ]);

        foreach (['attendance_enabled', 'biometric_enabled', 'web_enabled', 'login_attendance_enabled', 'mobile_enabled', 'manual_enabled', 'overtime_enabled'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        HrSetting::current()->update($data);

        return back()->with('status', 'Attendance settings saved.');
    }

    private function can(Request $request, string $action): void
    {
        abort_unless($request->user()->hasPermission("attendance.{$action}"), 403);
    }
}
