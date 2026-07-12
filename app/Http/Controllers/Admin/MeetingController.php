<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingSetting;
use App\Models\Meeting;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    /** Meeting list — employees see only meetings assigned to them (unless they can view all). */
    public function index(Request $request)
    {
        $me = $request->user();
        $seeAll = $me->seesAll('meetings');

        $meetings = Meeting::with('assignee', 'client')
            ->when(! $seeAll, fn ($q) => $q->where('assigned_to', $me->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('scope') && $request->scope === 'upcoming', fn ($q) => $q->where('date', '>=', today()))
            ->orderBy('date', $request->get('sort', 'asc'))
            ->orderBy('start_time')
            ->paginate(20)
            ->withQueryString();

        $employees = User::assignable()->orderBy('name')->get(['id', 'name']);
        $canAssign = $me->allows('meetings', 'assign');
        $canEdit = $me->allows('meetings', 'edit');
        $canDelete = $me->allows('meetings', 'delete');

        $scoped = fn () => Meeting::when(! $seeAll, fn ($q) => $q->where('assigned_to', $me->id));
        $stats = [
            'new' => $scoped()->unread()->count(),
            'pending' => $scoped()->where('status', 'pending')->count(),
            'today' => $scoped()->whereDate('date', today())->count(),
        ];

        return view('admin.meetings.index', compact('meetings', 'stats', 'seeAll', 'employees', 'canAssign', 'canEdit', 'canDelete'));
    }

    /** New/unread bookings for the current user — powers the sidebar "Book Meeting" number. */
    public static function unreadCount(User $me): int
    {
        return Meeting::unread()
            ->when(! $me->seesAll('meetings'), fn ($q) => $q->where('assigned_to', $me->id))
            ->count();
    }

    /** Inline updates from the list (status / assignee / follow-up date). */
    public function quickUpdate(Request $request, Meeting $meeting)
    {
        $me = $request->user();
        abort_unless($me->seesAll('meetings') || $meeting->assigned_to === $me->id, 403);

        $data = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', Meeting::STATUSES)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'follow_up_date' => ['nullable', 'date'],
        ]);

        if ($request->has('status') && $data['status']) {
            $meeting->status = $data['status'];
        }
        if ($request->has('assigned_to') && $me->allows('meetings', 'assign')) {
            $meeting->assigned_to = $data['assigned_to'] ?: null;
        }
        if ($request->has('follow_up_date')) {
            $meeting->follow_up_date = $data['follow_up_date'] ?: null;
        }
        $meeting->save();

        return back()->with('status', 'Meeting updated.');
    }

    /** Reschedule page — pick a new date + slot. */
    public function edit(Request $request, Meeting $meeting)
    {
        $me = $request->user();
        abort_unless($me->seesAll('meetings') || $meeting->assigned_to === $me->id, 403);

        $windows = BookingSetting::current()->slotWindows();

        return view('admin.meetings.edit', compact('meeting', 'windows'));
    }

    /** Save a new date/time for the meeting (admin reschedule). */
    public function reschedule(Request $request, Meeting $meeting)
    {
        $me = $request->user();
        abort_unless($me->seesAll('meetings') || $meeting->assigned_to === $me->id, 403);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'start' => ['required', 'date_format:H:i'],
        ]);

        $window = collect(BookingSetting::current()->slotWindows())->firstWhere(0, $data['start']);
        if (! $window) {
            return back()->withErrors(['start' => 'Pick a valid time slot.'])->withInput();
        }

        // Don't collide with another meeting in the same slot.
        $clash = Meeting::whereDate('date', $data['date'])->where('start_time', $data['start'].':00')
            ->where('id', '!=', $meeting->id)->whereIn('status', ['pending', 'confirmed', 'completed'])->exists();
        if ($clash) {
            return back()->withErrors(['start' => 'That slot is already booked on this date.'])->withInput();
        }

        $meeting->update([
            'date' => $data['date'],
            'start_time' => $window[0],
            'end_time' => $window[1],
        ]);

        return redirect()->route('admin.meetings.show', $meeting)->with('status', 'Meeting rescheduled.');
    }

    public function show(Request $request, Meeting $meeting)
    {
        $me = $request->user();
        abort_unless($me->seesAll('meetings') || $meeting->assigned_to === $me->id, 403);

        // Opening the details marks this booking as read → clears the sidebar "new" number.
        if ($meeting->seen_at === null) {
            $meeting->forceFill(['seen_at' => now()])->save();
        }

        $meeting->load('assignee', 'client');
        $employees = User::assignable()->orderBy('name')->get(['id', 'name']);

        return view('admin.meetings.show', compact('meeting', 'employees'));
    }

    /** Assign an employee, change status, add a link / notes. */
    public function update(Request $request, Meeting $meeting)
    {
        $me = $request->user();
        abort_unless($me->seesAll('meetings') || $meeting->assigned_to === $me->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Meeting::STATUSES)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'follow_up_date' => ['nullable', 'date'],
            'meeting_link' => ['nullable', 'url', 'max:500'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Only users who can assign may change the owner.
        if (! $me->allows('meetings', 'assign')) {
            unset($data['assigned_to']);
        }

        $meeting->update($data);

        return back()->with('status', 'Meeting updated.');
    }

    public function destroy(Meeting $meeting)
    {
        $meeting->delete();

        return redirect()->route('admin.meetings.index')->with('status', 'Meeting deleted.');
    }

    // ---- Settings ----

    public function settings()
    {
        $settings = BookingSetting::current();
        $employees = User::assignable()->orderBy('name')->get(['id', 'name']);

        return view('admin.meetings.settings', compact('settings', 'employees'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'working_days' => ['nullable', 'array'],
            'working_days.*' => ['integer', 'between:0,6'],
            'advance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'lead_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'is_enabled' => ['nullable', 'boolean'],
            'default_assignee_id' => ['nullable', 'exists:users,id'],
        ]);

        BookingSetting::current()->update([
            'start_time' => $data['start_time'].':00',
            'end_time' => $data['end_time'].':00',
            'slot_minutes' => $data['slot_minutes'],
            'working_days' => array_values($data['working_days'] ?? []),
            'advance_days' => $data['advance_days'],
            'lead_hours' => $data['lead_hours'],
            'is_enabled' => $request->boolean('is_enabled'),
            'default_assignee_id' => $data['default_assignee_id'] ?? null,
        ]);

        return back()->with('status', 'Booking settings saved.');
    }
}
