@php $chip = ['present' => 'bg-emerald-50 text-emerald-700', 'late' => 'bg-amber-50 text-amber-700', 'half_day' => 'bg-sky-50 text-sky-700', 'absent' => 'bg-red-50 text-red-600']; @endphp

<div class="mb-4 grid gap-4 sm:grid-cols-3">
    @foreach ([['Days present', $attendanceStats['present']], ['Late days', $attendanceStats['late']], ['Total worked', \App\Models\Attendance::minutesLabel($attendanceStats['minutes'])]] as [$l, $v])
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-400">{{ $l }}</p>
            <p class="mt-1 text-xl font-extrabold text-[var(--color-heading)]">{{ $v }}</p>
        </div>
    @endforeach
</div>

<div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
    <table class="w-full text-left text-sm" style="min-width:760px">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
            <tr>
                <th class="px-5 py-3 font-semibold">Date</th>
                <th class="px-5 py-3 font-semibold">Check In</th>
                <th class="px-5 py-3 font-semibold">Check Out</th>
                <th class="px-5 py-3 font-semibold">Worked</th>
                <th class="px-5 py-3 font-semibold">Late</th>
                <th class="px-5 py-3 font-semibold">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($attendance as $a)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ $a->work_date?->format('d M Y') }}</td>
                    <td class="px-5 py-3">{{ $a->check_in_at?->format('g:i A') ?? '—' }} @include('admin.attendance._method-badge', ['method' => $a->check_in_method])</td>
                    <td class="px-5 py-3">{{ $a->check_out_at?->format('g:i A') ?? '—' }} @include('admin.attendance._method-badge', ['method' => $a->check_out_method])</td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $a->workedLabel() }}</td>
                    <td class="px-5 py-3">{{ $a->late_minutes ? \App\Models\Attendance::minutesLabel($a->late_minutes) : '—' }}</td>
                    <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $chip[$a->status] ?? 'bg-gray-100 text-gray-500' }}">{{ \App\Models\Attendance::STATUSES[$a->status] ?? $a->status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-12 text-center text-gray-300">No attendance recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $attendance->links() }}</div>
