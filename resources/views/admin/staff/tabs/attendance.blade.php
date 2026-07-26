@php
    $chip = ['present' => 'bg-emerald-50 text-emerald-700', 'late' => 'bg-amber-50 text-amber-700', 'half_day' => 'bg-sky-50 text-sky-700', 'absent' => 'bg-red-50 text-red-600'];
@endphp

@include('admin.staff.tabs._stats', ['stats' => [
    ['Days present', $attendanceStats['present'], 'text-emerald-600 bg-emerald-50', 'm5 13 4 4L19 7'],
    ['Late days', $attendanceStats['late'], 'text-amber-600 bg-amber-50', 'M12 8v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
    ['Total worked', \App\Models\Attendance::minutesLabel($attendanceStats['minutes']), 'text-indigo-600 bg-indigo-50', 'M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
]])

<section class="rounded-xl border border-gray-100 bg-white shadow-sm">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
        <h2 class="text-sm font-bold text-[var(--color-heading)]">Attendance history</h2>
        {{-- The Attendance screen carries the date/method filters; this tab is the recent view. --}}
        @if (auth()->user()->hasPermission('attendance.view'))
            <a href="{{ route('admin.attendance.history', ['user' => $staff->id]) }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">Open with filters</a>
        @endif
    </header>
    <div class="overflow-x-auto">
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
                        <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $a->work_date?->format('d M Y') }}</td>
                        <td class="px-5 py-3">
                            <span class="font-medium text-[var(--color-heading)]">{{ $a->check_in_at?->format('g:i A') ?? '—' }}</span>
                            @include('admin.attendance._method-badge', ['method' => $a->check_in_method])
                        </td>
                        <td class="px-5 py-3">
                            <span class="font-medium text-[var(--color-heading)]">{{ $a->check_out_at?->format('g:i A') ?? '—' }}</span>
                            @include('admin.attendance._method-badge', ['method' => $a->check_out_method])
                        </td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $a->workedLabel() }}</td>
                        <td class="px-5 py-3 {{ $a->late_minutes ? 'font-semibold text-red-600' : 'text-gray-300' }}">{{ $a->late_minutes ? \App\Models\Attendance::minutesLabel($a->late_minutes) : '—' }}</td>
                        <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $chip[$a->status] ?? 'bg-gray-100 text-gray-500' }}">{{ \App\Models\Attendance::STATUSES[$a->status] ?? $a->status }}</span></td>
                    </tr>
                @empty
                    @include('admin.staff.tabs._empty', [
                        'cols' => 6,
                        'icon' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z',
                        'title' => 'No attendance recorded yet',
                        'hint' => 'Punches from the biometric device or web check-in show up here.',
                    ])
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($attendance->hasPages())
        <div class="border-t border-gray-100 px-5 py-3">{{ $attendance->links() }}</div>
    @endif
</section>
