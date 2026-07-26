<div class="mb-4 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
    <p class="text-xs uppercase tracking-wide text-gray-400">Total logged</p>
    <p class="mt-1 text-xl font-extrabold text-[var(--color-heading)]">{{ \App\Models\Attendance::minutesLabel($timeTotal) }}</p>
</div>

<div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
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
                <tr><td colspan="5" class="px-5 py-12 text-center text-gray-300">No time logged.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $timeLogs->links() }}</div>
