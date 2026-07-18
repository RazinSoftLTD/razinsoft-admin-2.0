<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

/** Activity → Employee: who's active, most-recent first; drill into one employee for the full trail. */
class ActivityLogController extends Controller
{
    private const METHODS = ['GET' => 'Viewed', 'POST' => 'Created / submitted', 'PUT' => 'Updated', 'PATCH' => 'Updated', 'DELETE' => 'Deleted'];

    /** List employees with their latest activity, most recently active on top. */
    public function index()
    {
        $rows = ActivityLog::query()
            ->selectRaw('user_id, MAX(id) as last_id, MAX(created_at) as last_at, COUNT(*) as total')
            ->groupBy('user_id')
            ->orderByDesc('last_at')
            ->get();

        $lastLogs = ActivityLog::with('user:id,name,photo')
            ->whereIn('id', $rows->pluck('last_id'))->get()->keyBy('id');

        $employees = $rows->map(fn ($r) => ['log' => $lastLogs[$r->last_id] ?? null, 'total' => (int) $r->total])
            ->filter(fn ($x) => $x['log'] && $x['log']->user)
            ->values();

        return view('admin.activity-logs.index', compact('employees'));
    }

    /** Full activity trail for one employee (with filters). */
    public function show(Request $request, User $employee)
    {
        $q = ActivityLog::query()->where('user_id', $employee->id)->latest('id');

        if ($method = $request->query('method')) {
            $q->where('method', $method);
        }
        match ($request->query('date_range')) {
            'today' => $q->whereDate('created_at', today()),
            'week' => $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $q->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            default => null,
        };
        if ($from = $request->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        return view('admin.activity-logs.show', [
            'employee' => $employee,
            'logs' => $q->paginate(40)->withQueryString(),
            'methods' => self::METHODS,
        ]);
    }
}
