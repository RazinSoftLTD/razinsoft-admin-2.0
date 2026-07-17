@php $ms = $project->milestones; @endphp

<div x-data="{ addOpen: false, editId: null }">
    @if ($canEdit)
        <div class="mb-4 flex justify-end">
            <button type="button" @click="addOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Milestone
            </button>
        </div>
    @endif

    @if ($ms->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-400">No milestones yet.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full min-w-[750px] text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/70 text-left text-[11px] uppercase tracking-wide text-gray-400">
                        <th class="px-4 py-3 font-semibold">Milestone</th>
                        <th class="px-4 py-3 font-semibold">Start Date</th>
                        <th class="px-4 py-3 font-semibold">End Date</th>
                        <th class="px-4 py-3 font-semibold">Cost</th>
                        <th class="px-4 py-3 font-semibold">Tasks</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($ms as $m)
                        <tr class="transition hover:bg-gray-50/60">
                            <td class="px-4 py-3.5">
                                <p class="font-semibold text-[var(--color-heading)]">{{ $m->title }}</p>
                                @if ($m->summary)<p class="mt-0.5 max-w-md truncate text-xs text-gray-400">{{ $m->summary }}</p>@endif
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-[var(--color-muted)]">{{ $m->start_date?->format('d M, Y') ?? '—' }}</td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-[var(--color-muted)]">{{ $m->end_date?->format('d M, Y') ?? '—' }}</td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-[var(--color-muted)]">{{ $m->cost ? $project->currency.' '.number_format((float) $m->cost, 2) : '—' }}</td>
                            <td class="px-4 py-3.5 text-[var(--color-muted)]">{{ $m->tasks()->count() }}</td>
                            <td class="px-4 py-3.5">
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('admin.projects.milestones.update', [$project, $m]) }}" data-turbo="false">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="{{ $m->status === 'complete' ? 'incomplete' : 'complete' }}">
                                        <button class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $m->status === 'complete' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $m->status === 'complete' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                            {{ \App\Models\ProjectMilestone::STATUSES[$m->status] }}
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $m->status === 'complete' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ \App\Models\ProjectMilestone::STATUSES[$m->status] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                @if ($canEdit)
                                    <div class="inline-flex items-center gap-1">
                                        <button type="button" @click="editId = editId === {{ $m->id }} ? null : {{ $m->id }}" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Edit">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg>
                                        </button>
                                        <form method="POST" action="{{ route('admin.projects.milestones.destroy', [$project, $m]) }}" data-turbo="false" onsubmit="return confirm('Delete this milestone? Its tasks stay, unlinked.')">
                                            @csrf @method('DELETE')
                                            <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500" title="Delete">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m3 0v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                        {{-- Inline edit row --}}
                        @if ($canEdit)
                            <tr x-show="editId === {{ $m->id }}" x-cloak>
                                <td colspan="7" class="bg-gray-50/60 px-4 py-4">
                                    <form method="POST" action="{{ route('admin.projects.milestones.update', [$project, $m]) }}" data-turbo="false" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                                        @csrf @method('PUT')
                                        <input type="text" name="title" value="{{ $m->title }}" required placeholder="Milestone title" class="h-10 rounded-lg border-gray-200 text-sm lg:col-span-2">
                                        <input type="date" name="start_date" value="{{ $m->start_date?->toDateString() }}" class="h-10 rounded-lg border-gray-200 text-sm">
                                        <input type="date" name="end_date" value="{{ $m->end_date?->toDateString() }}" class="h-10 rounded-lg border-gray-200 text-sm">
                                        <input type="number" name="cost" step="0.01" min="0" value="{{ $m->cost }}" placeholder="Cost" class="h-10 rounded-lg border-gray-200 text-sm">
                                        <select name="status" class="h-10 rounded-lg border-gray-200 text-sm">
                                            @foreach (\App\Models\ProjectMilestone::STATUSES as $k => $v)<option value="{{ $k }}" @selected($m->status === $k)>{{ $v }}</option>@endforeach
                                        </select>
                                        <textarea name="summary" rows="2" placeholder="Summary…" class="rounded-lg border-gray-200 text-sm sm:col-span-2 lg:col-span-5">{{ $m->summary }}</textarea>
                                        <div class="flex items-start"><button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button></div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Add milestone modal --}}
    @if ($canEdit)
        <div x-show="addOpen" x-cloak @keydown.escape.window="addOpen = false">
            <div x-show="addOpen" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="addOpen = false"></div>
            <div x-show="addOpen" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-20" @click.self="addOpen = false">
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="text-base font-bold text-[var(--color-heading)]">Add Milestone</h3>
                        <button type="button" @click="addOpen = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                    </div>
                    <form method="POST" action="{{ route('admin.projects.milestones.store', $project) }}" data-turbo="false" class="space-y-4 px-5 py-4">
                        @csrf
                        <input type="hidden" name="status" value="incomplete">
                        <input type="text" name="title" required placeholder="Milestone title" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        <textarea name="summary" rows="2" placeholder="Summary (optional)" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <input type="date" name="start_date" class="h-11 rounded-lg border-gray-200 text-sm">
                            <input type="date" name="end_date" class="h-11 rounded-lg border-gray-200 text-sm">
                            <input type="number" name="cost" step="0.01" min="0" placeholder="Cost" class="h-11 rounded-lg border-gray-200 text-sm">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="addOpen = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add Milestone</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
