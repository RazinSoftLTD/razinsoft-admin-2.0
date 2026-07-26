{{-- Same source as Activity > Employee, scoped to this person. --}}
<div class="mb-3 flex items-center justify-between">
    <p class="text-xs text-[var(--color-muted)]">Everything this employee did in the panel.</p>
    @if (auth()->user()->hasPermission('activity.employee'))
        <a href="{{ route('admin.activity-logs.show', $staff) }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">Open full activity log</a>
    @endif
</div>

<div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
    <table class="w-full text-left text-sm" style="min-width:760px">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
            <tr>
                <th class="px-5 py-3 font-semibold">When</th>
                <th class="px-5 py-3 font-semibold">Action</th>
                <th class="px-5 py-3 font-semibold">Method</th>
                <th class="px-5 py-3 font-semibold">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($logs as $log)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $log->created_at?->format('d M Y, g:i A') }}</td>
                    <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ $log->route_name ?: ($log->url ?: "—") }}</td>
                    <td class="px-5 py-3"><span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-semibold text-gray-600">{{ $log->method ?? '—' }}</span></td>
                    <td class="px-5 py-3 font-mono text-xs text-[var(--color-muted)]">{{ $log->ip ?? "—" }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-5 py-12 text-center text-gray-300">No activity recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
