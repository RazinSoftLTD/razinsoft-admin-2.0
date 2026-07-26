{{-- Same source as Activity > Employee, scoped to this person. --}}
<section class="rounded-xl border border-gray-100 bg-white shadow-sm">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
        <h2 class="text-sm font-bold text-[var(--color-heading)]">Panel activity</h2>
        <div class="flex items-center gap-3">
            <span class="text-xs text-gray-400">{{ $logs->total() }} event(s)</span>
            @if (auth()->user()->hasPermission('activity.employee'))
                <a href="{{ route('admin.activity-logs.show', $staff) }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">Full log</a>
            @endif
        </div>
    </header>
    <div class="overflow-x-auto">
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
                @include('admin.staff.tabs._empty', ['cols' => 4, 'icon' => 'M3 12h4l3 8 4-16 3 8h4', 'title' => 'No activity recorded', 'hint' => 'Every page this employee opens in the panel is logged here.'])
            @endforelse
        </tbody>
    </table>
    </div>
    @if ($logs->hasPages())
        <div class="border-t border-gray-100 px-5 py-3">{{ $logs->links() }}</div>
    @endif
</section>
