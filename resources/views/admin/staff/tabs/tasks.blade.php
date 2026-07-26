@php $chip = ['completed' => 'bg-emerald-50 text-emerald-700', 'in_progress' => 'bg-sky-50 text-sky-700', 'review' => 'bg-amber-50 text-amber-700', 'todo' => 'bg-gray-100 text-gray-600']; @endphp

<div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
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
                <tr><td colspan="4" class="px-5 py-12 text-center text-gray-300">No tasks assigned.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $tasks->links() }}</div>
