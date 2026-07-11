<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $seeAll = $me->isAdmin() || $me->hasPermission('leave.view_all');

        $q = Leave::with('user.designation', 'reviewer')->latest();
        if (! $seeAll) {
            $q->where('user_id', $me->id); // employees see only their own
        }
        if (array_key_exists($request->query('status'), Leave::STATUSES)) {
            $q->where('status', $request->query('status'));
        }

        $base = $seeAll ? Leave::query() : Leave::where('user_id', $me->id);

        return view('admin.leaves.index', [
            'leaves' => $q->paginate(15)->withQueryString(),
            'status' => $request->query('status', ''),
            'seeAll' => $seeAll,
            'canApprove' => $me->isAdmin() || $me->hasPermission('leave.approve'),
            'counts' => [
                'all' => (clone $base)->count(),
                'pending' => (clone $base)->where('status', 'pending')->count(),
                'approved' => (clone $base)->where('status', 'approved')->count(),
                'rejected' => (clone $base)->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.leaves.form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'leave_type' => ['required', Rule::in(array_keys(Leave::TYPES))],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['user_id'] = $request->user()->id;
        $data['status'] = 'pending';
        Leave::create($data);

        return redirect()->route('admin.leaves.index')->with('status', 'Leave request submitted.');
    }

    /** Approve or reject — requires leave.approve. */
    public function updateStatus(Request $request, Leave $leave)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->hasPermission('leave.approve'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);
        $leave->update([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'Leave '.$data['status'].'.');
    }

    public function destroy(Request $request, Leave $leave)
    {
        $me = $request->user();
        // Own pending request, or an approver/admin.
        abort_unless(($leave->user_id === $me->id && $leave->status === 'pending') || $me->isAdmin() || $me->hasPermission('leave.delete'), 403);
        $leave->delete();

        return back()->with('status', 'Leave request removed.');
    }
}
