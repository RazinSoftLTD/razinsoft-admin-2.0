@php $chip = ['completed' => 'bg-emerald-50 text-emerald-700', 'in_progress' => 'bg-sky-50 text-sky-700', 'review' => 'bg-amber-50 text-amber-700', 'todo' => 'bg-gray-100 text-gray-600']; @endphp

<section class="rounded-xl border border-gray-100 bg-white shadow-sm">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
        <h2 class="text-sm font-bold text-[var(--color-heading)]">Assigned tasks</h2>
        <span class="text-xs text-gray-400">{{ $tasks->total() }} task(s)</span>
    </header>
    <div class="overflow-x-auto">
    <table class="w-full text-left text-sm" style="min-width:720px">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
            <tr>
                <th class="px-5 py-3 font-semibold">Task</th>
                <th class="px-5 py-3 font-semibold">Project</th>
                <th class="px-5 py-3 font-semibold">Due</th>
                <th class="px-5 py-3 font-semibold">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($tasks as $t)
                @php $overdue = $t->due_date && \Carbon\Carbon::parse($t->due_date)->isPast() && $t->status !== 'completed'; @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ $t->title }}</td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $t->project?->name ?? '—' }}</td>
                    <td class="px-5 py-3 {{ $overdue ? 'font-semibold text-red-600' : 'text-[var(--color-muted)]' }}">
                        {{ $t->due_date ? \Carbon\Carbon::parse($t->due_date)->format('d M Y') : '—' }}{{ $overdue ? ' · overdue' : '' }}
                    </td>
                    <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $chip[$t->status] ?? 'bg-gray-100 text-gray-500' }}">{{ ucfirst(str_replace('_', ' ', $t->status)) }}</span></td>
                </tr>
            @empty
                @include('admin.staff.tabs._empty', ['cols' => 4, 'icon' => 'M9 5h10M9 12h10M9 19h10M5 5h.01M5 12h.01M5 19h.01', 'title' => 'No tasks assigned', 'hint' => 'Tasks assigned to this employee in Workspace appear here.'])
            @endforelse
        </tbody>
    </table>
    </div>
    @if ($tasks->hasPages())
        <div class="border-t border-gray-100 px-5 py-3">{{ $tasks->links() }}</div>
    @endif
</section>
