@include('admin.staff.tabs._stats', ['stats' => [
    ['Total logged', \App\Models\Attendance::minutesLabel($timeTotal), 'text-indigo-600 bg-indigo-50', 'M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
]])

<section class="rounded-xl border border-gray-100 bg-white shadow-sm">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
        <h2 class="text-sm font-bold text-[var(--color-heading)]">Time logs</h2>
        <span class="text-xs text-gray-400">{{ $timeLogs->total() }} entr(y/ies)</span>
    </header>
    <div class="overflow-x-auto">
    <table class="w-full text-left text-sm" style="min-width:760px">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
            <tr>
                <th class="px-5 py-3 font-semibold">Date</th>
                <th class="px-5 py-3 font-semibold">Project</th>
                <th class="px-5 py-3 font-semibold">Task</th>
                <th class="px-5 py-3 font-semibold">Note</th>
                <th class="px-5 py-3 text-right font-semibold">Time</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($timeLogs as $log)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ \Carbon\Carbon::parse($log->spent_on)->format('d M Y') }}</td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $log->project?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $log->task?->title ?? '—' }}</td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ \Illuminate\Support\Str::limit($log->note, 50) ?: '—' }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-[var(--color-heading)]">{{ \App\Models\Attendance::minutesLabel((int) $log->minutes) }}</td>
                </tr>
            @empty
                @include('admin.staff.tabs._empty', ['cols' => 5, 'icon' => 'M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', 'title' => 'No time logged', 'hint' => 'Hours logged against tasks and projects show up here.'])
            @endforelse
        </tbody>
    </table>
    </div>
    @if ($timeLogs->hasPages())
        <div class="border-t border-gray-100 px-5 py-3">{{ $timeLogs->links() }}</div>
    @endif
</section>
