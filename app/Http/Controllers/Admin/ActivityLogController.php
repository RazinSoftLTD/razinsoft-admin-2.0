<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

/** Settings → Activity Log: audit trail of every employee's actions. */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $q = ActivityLog::query()->with('user:id,name,photo')->latest('id');

        if ($employee = $request->query('employee')) {
            $q->where('user_id', $employee);
        }
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

        return view('admin.activity-logs.index', [
            'logs' => $q->paginate(30)->withQueryString(),
            'employees' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'methods' => ['GET' => 'Viewed', 'POST' => 'Created / submitted', 'PUT' => 'Updated', 'PATCH' => 'Updated', 'DELETE' => 'Deleted'],
        ]);
    }
}
