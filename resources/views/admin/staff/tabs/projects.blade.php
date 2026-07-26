{{-- Projects this employee manages or is a member of. --}}
@php
    $chip = [
        'todo' => 'bg-gray-100 text-gray-600',
        'in_progress' => 'bg-sky-50 text-sky-700',
        'on_hold' => 'bg-amber-50 text-amber-700',
        'completed' => 'bg-emerald-50 text-emerald-700',
        'cancelled' => 'bg-red-50 text-red-600',
    ];
@endphp

<section class="rounded-xl border border-gray-100 bg-white shadow-sm">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
        <h2 class="text-sm font-bold text-[var(--color-heading)]">Projects</h2>
        <span class="text-xs text-gray-400">{{ $projects->total() }} project(s)</span>
    </header>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm" style="min-width:760px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Project</th>
                    <th class="px-5 py-3 font-semibold">Client</th>
                    <th class="px-5 py-3 font-semibold">Role</th>
                    <th class="px-5 py-3 font-semibold">Deadline</th>
                    <th class="px-5 py-3 font-semibold">Progress</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($projects as $p)
                    @php $pct = $p->progressPercent(); @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.projects.show', $p) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $p->name }}</a>
                            <p class="text-xs text-gray-400">{{ $p->all_tasks_count }} task(s)</p>
                        </td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->client?->name ?? '—' }}</td>
                        <td class="px-5 py-3">
                            @if ($p->project_manager_id === $staff->id)
                                <span class="rounded-full bg-[var(--color-primary-soft)] px-2 py-0.5 text-[11px] font-semibold text-[var(--color-primary)]">Manager</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">Member</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 {{ $p->isOverdue() ? 'font-semibold text-red-600' : 'text-[var(--color-muted)]' }}">
                            {{ $p->deadline?->format('d M Y') ?? '—' }}{{ $p->isOverdue() ? ' · overdue' : '' }}
                        </td>
                        <td class="px-5 py-3">
                            <span class="flex items-center gap-2">
                                <span class="block h-1.5 w-24 rounded-full bg-gray-100">
                                    <span class="block h-1.5 rounded-full bg-[var(--color-primary)]" style="width:{{ $pct }}%"></span>
                                </span>
                                <span class="text-xs font-semibold text-[var(--color-muted)]">{{ $pct }}%</span>
                            </span>
                        </td>
                        <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $chip[$p->status] ?? 'bg-gray-100 text-gray-500' }}">{{ \App\Models\Project::STATUSES[$p->status] ?? $p->status }}</span></td>
                    </tr>
                @empty
                    @include('admin.staff.tabs._empty', [
                        'cols' => 6,
                        'icon' => 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z',
                        'title' => 'No projects yet',
                        'hint' => 'Projects this employee manages or belongs to will be listed here.',
                    ])
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($projects->hasPages())
        <div class="border-t border-gray-100 px-5 py-3">{{ $projects->links() }}</div>
    @endif
</section>
