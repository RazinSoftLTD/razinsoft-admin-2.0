@php
    $ms = $project->milestones;
    $doneKeys = $project->doneKeys();
    $priorityStyle = [
        'low' => ['text-emerald-600', 'Low'],
        'medium' => ['text-amber-500', 'Medium'],
        'high' => ['text-red-500', 'High'],
        'urgent' => ['text-red-600', 'Urgent'],
    ];
@endphp

<div x-data="{ editId: null }">

    @if ($ms->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-400">No milestones yet.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full text-sm" style="min-width:980px">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-400">
                        <th class="px-5 py-3 font-semibold">Milestone</th>
                        <th class="px-5 py-3 font-semibold">Project</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Progress</th>
                        <th class="px-5 py-3 font-semibold">Due Date</th>
                        <th class="px-5 py-3 font-semibold">Priority</th>
                        <th class="px-5 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($ms as $m)
                        @php
                            $mTasks = $m->tasks()->get(['id', 'status']);
                            $total = $mTasks->count();
                            $done = $mTasks->whereIn('status', $doneKeys)->count();
                            $pct = $m->status === 'complete' ? 100 : ($total ? (int) round($done / $total * 100) : 0);
                            // Completed / In Progress / Pending is derived from the work, not stored.
                            [$stLabel, $stTone, $stDot] = $m->status === 'complete'
                                ? ['Completed', 'bg-emerald-50 text-emerald-600', 'bg-emerald-500']
                                : ($pct > 0 ? ['In Progress', 'bg-blue-50 text-blue-600', 'bg-blue-500']
                                            : ['Pending', 'bg-amber-50 text-amber-600', 'bg-amber-500']);
                            $bar = $m->status === 'complete' ? 'bg-emerald-500' : ($pct > 0 ? 'bg-[var(--color-primary)]' : 'bg-gray-200');
                            $days = $m->end_date ? (int) now()->startOfDay()->diffInDays($m->end_date, false) : null;
                            [$pTone, $pLabel] = $priorityStyle[$m->priority ?? 'medium'] ?? $priorityStyle['medium'];
                        @endphp
                        <tr class="transition hover:bg-gray-50">
                            {{-- Milestone --}}
                            <td class="px-5 py-3.5">
                                <div class="flex items-start gap-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg"
                                          style="background: {{ $m->labelColor() }}1a; color: {{ $m->labelColor() }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $m->iconPath() }}"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-[var(--color-heading)]">{{ $m->title }}</p>
                                        @if ($m->summary)
                                            <p class="mt-0.5 max-w-xs truncate text-xs text-gray-400">{{ Str::limit(strip_tags($m->summary), 60) }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Project --}}
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center gap-2 text-[var(--color-heading)]">
                                    @if ($project->avatarUrl())
                                        <img src="{{ $project->avatarUrl() }}" class="h-5 w-5 rounded object-cover" alt="">
                                    @else
                                        <span class="grid h-5 w-5 place-items-center rounded bg-[var(--color-primary-soft)] text-[9px] font-bold text-[var(--color-primary)]">{{ $project->initials() }}</span>
                                    @endif
                                    <span class="truncate">{{ $project->name }}</span>
                                </span>
                            </td>

                            {{-- Status (click to flip complete/incomplete) --}}
                            <td class="px-5 py-3.5">
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('admin.projects.milestones.update', [$project, $m]) }}" data-turbo="false">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="{{ $m->status === 'complete' ? 'incomplete' : 'complete' }}">
                                        <button title="{{ $m->status === 'complete' ? 'Mark as incomplete' : 'Mark as complete' }}"
                                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition hover:opacity-80 {{ $stTone }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $stDot }}"></span>{{ $stLabel }}
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $stTone }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $stDot }}"></span>{{ $stLabel }}
                                    </span>
                                @endif
                            </td>

                            {{-- Progress --}}
                            <td class="px-5 py-3.5" style="min-width:140px">
                                <p class="mb-1 text-right text-xs font-semibold text-[var(--color-heading)]">{{ $pct }}%</p>
                                <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-1.5 rounded-full {{ $bar }}" style="width: {{ $pct }}%"></div>
                                </div>
                                @if ($total)
                                    <p class="mt-1 text-[11px] text-gray-400">{{ $done }}/{{ $total }} tasks</p>
                                @endif
                            </td>

                            {{-- Due date --}}
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if ($m->end_date)
                                    <p class="inline-flex items-center gap-1.5 text-[var(--color-heading)]">
                                        <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M3 9h18M8 3v4M16 3v4"/></svg>
                                        {{ $m->end_date->format('d M, Y') }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] font-medium
                                        {{ $m->status === 'complete' ? 'text-emerald-600' : ($days < 0 ? 'text-red-600' : ($days <= 7 ? 'text-amber-500' : 'text-gray-400')) }}">
                                        @if ($m->status === 'complete')
                                            Completed
                                        @elseif ($days < 0)
                                            {{ abs($days) }} days overdue
                                        @elseif ($days === 0)
                                            Due today
                                        @else
                                            {{ $days }} days left
                                        @endif
                                    </p>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>

                            {{-- Priority --}}
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center gap-1.5 text-[var(--color-heading)]">
                                    <svg class="h-4 w-4 {{ $pTone }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 21V4m0 0h11l-1.5 3.5L16 11H5"/></svg>
                                    {{ $pLabel }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="px-5 py-3.5 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <a href="{{ route('admin.projects.show', $project) }}?tab=tasks&milestone={{ $m->id }}" title="View tasks"
                                       class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-primary)]">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    @if ($canEdit)
                                        <button type="button" @click="editId = editId === {{ $m->id }} ? null : {{ $m->id }}" title="Edit"
                                                class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-primary)]">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg>
                                        </button>
                                        <form method="POST" action="{{ route('admin.projects.milestones.destroy', [$project, $m]) }}" data-turbo="false" onsubmit="return confirm('Delete “{{ $m->title }}”? Its tasks stay, unlinked.')">
                                            @csrf @method('DELETE')
                                            <button title="Delete" class="grid h-8 w-8 place-items-center rounded-lg text-red-400 transition hover:bg-red-50 hover:text-red-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m3 0v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Inline edit row --}}
                        @if ($canEdit)
                            <tr x-show="editId === {{ $m->id }}" x-cloak>
                                <td colspan="7" class="bg-gray-50 px-5 py-4">
                                    <form method="POST" action="{{ route('admin.projects.milestones.update', [$project, $m]) }}" data-turbo="false" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                                        @csrf @method('PUT')
                                        <input type="text" name="title" value="{{ $m->title }}" required placeholder="Milestone title" class="h-10 rounded-lg border-gray-200 text-sm lg:col-span-2">
                                        <input type="date" name="start_date" value="{{ $m->start_date?->toDateString() }}" class="h-10 rounded-lg border-gray-200 text-sm">
                                        <input type="date" name="end_date" value="{{ $m->end_date?->toDateString() }}" class="h-10 rounded-lg border-gray-200 text-sm">
                                        <select name="priority" class="h-10 rounded-lg border-gray-200 text-sm">
                                            @foreach (\App\Models\ProjectMilestone::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(($m->priority ?? 'medium') === $k)>{{ $v }}</option>@endforeach
                                        </select>
                                        <select name="status" class="h-10 rounded-lg border-gray-200 text-sm">
                                            @foreach (\App\Models\ProjectMilestone::STATUSES as $k => $v)<option value="{{ $k }}" @selected($m->status === $k)>{{ $v }}</option>@endforeach
                                        </select>
                                        <textarea name="summary" rows="2" placeholder="Summary…" class="rounded-lg border-gray-200 text-sm sm:col-span-2 lg:col-span-5">{{ strip_tags($m->summary ?? '') }}</textarea>
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
        <div x-show="addOpen" x-cloak @keydown.escape.window="addOpen = false"
             x-data="milestoneForm()">
            <div x-show="addOpen" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="addOpen = false"></div>
            <div x-show="addOpen" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 py-10" @click.self="addOpen = false">
                <div class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                    {{-- Header --}}
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 21V4m0 0h11l-1.5 3.5L16 11H5"/></svg>
                            </span>
                            <div>
                                <h3 class="text-lg font-bold text-[var(--color-heading)]">Add New Milestone</h3>
                                <p class="text-xs text-[var(--color-muted)]">Create a new milestone and set target date</p>
                            </div>
                        </div>
                        <button type="button" @click="addOpen = false" class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('admin.projects.milestones.store', $project) }}" data-turbo="false" @submit="submitting = true">
                        @csrf
                        <div class="space-y-5 p-6">
                            {{-- Title + project --}}
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Milestone Title <span class="text-red-500">*</span></label>
                                    <input type="text" name="title" required maxlength="255" placeholder="Enter milestone title"
                                           class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                    <p class="mt-1 text-xs text-[var(--color-muted)]">A short, clear title for this milestone</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Project <span class="text-red-500">*</span></label>
                                    <div class="flex h-11 items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm font-medium text-[var(--color-heading)]">
                                        @if ($project->avatarUrl())
                                            <img src="{{ $project->avatarUrl() }}" class="h-6 w-6 shrink-0 rounded object-cover" alt="">
                                        @else
                                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded bg-[var(--color-primary-soft)] text-[10px] font-bold text-[var(--color-primary)]">{{ $project->initials() }}</span>
                                        @endif
                                        <span class="truncate">{{ $project->name }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-[var(--color-muted)]">Milestones belong to this project</p>
                                </div>
                            </div>

                            {{-- Description --}}
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Description</label>
                                <x-admin.rich-editor name="summary" placeholder="Write a description for this milestone..." :min-height="120" />
                                <p class="mt-1 text-xs text-[var(--color-muted)]">Describe the goal and scope of this milestone</p>
                            </div>

                            {{-- Dates + priority --}}
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Start Date <span class="font-normal text-[var(--color-muted)]">(Optional)</span></label>
                                    <input type="date" name="start_date" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                    <p class="mt-1 text-xs text-[var(--color-muted)]">When this milestone starts</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Due Date</label>
                                    <input type="date" name="end_date" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                    <p class="mt-1 text-xs text-[var(--color-muted)]">Target completion date</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Priority</label>
                                    <select name="priority" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                        @foreach (\App\Models\ProjectMilestone::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($k === 'medium')>{{ $v }}</option>@endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-[var(--color-muted)]">Set priority level</p>
                                </div>
                            </div>

                            {{-- Status + tasks --}}
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Status</label>
                                    <select name="status" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                        @foreach (\App\Models\ProjectMilestone::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-[var(--color-muted)]">Current status of this milestone</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Associated Tasks <span class="font-normal text-[var(--color-muted)]">(Optional)</span></label>
                                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                        <button type="button" @click="open = !open"
                                                class="flex h-11 w-full items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 text-left text-sm text-[var(--color-heading)]">
                                            <span x-text="tasks.length ? tasks.length + ' task(s) selected' : 'Select tasks'" class="truncate text-[var(--color-muted)]">Select tasks</span>
                                            <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                                        </button>
                                        <div x-show="open" x-cloak class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-100 bg-white py-1 shadow-lg">
                                            @forelse ($tasks as $t)
                                                <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-xs text-[var(--color-heading)] hover:bg-gray-50">
                                                    <input type="checkbox" name="task_ids[]" value="{{ $t->id }}" @change="sync($event)"
                                                           class="h-4 w-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                                    <span class="truncate">{{ $t->title }}</span>
                                                </label>
                                            @empty
                                                <p class="px-3 py-3 text-center text-xs text-gray-400">No tasks in this project yet.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-[var(--color-muted)]">Link related tasks to this milestone</p>
                                </div>
                            </div>

                            {{-- Colour + icon --}}
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Color <span class="font-normal text-[var(--color-muted)]">(Label)</span></label>
                                    <input type="hidden" name="color" :value="color">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach (\App\Models\ProjectMilestone::COLORS as $c)
                                            <button type="button" @click="color = @js($c)"
                                                    class="grid h-8 w-8 place-items-center rounded-full text-white transition"
                                                    :class="color === @js($c) ? 'ring-2' : ''"
                                                    style="background: {{ $c }}">
                                                <svg x-show="color === @js($c)" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                            </button>
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-xs text-[var(--color-muted)]">Choose a color to identify this milestone</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Milestone Icon</label>
                                    <input type="hidden" name="icon" :value="icon">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach (\App\Models\ProjectMilestone::ICONS as $key => $path)
                                            <button type="button" @click="icon = @js($key)"
                                                    class="grid h-10 w-10 place-items-center rounded-lg border transition"
                                                    :class="icon === @js($key) ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)] text-[var(--color-primary)]' : 'border-gray-200 text-gray-400 hover:bg-gray-50'">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                                            </button>
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-xs text-[var(--color-muted)]">Choose an icon for this milestone</p>
                                </div>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
                            <button type="button" @click="addOpen = false" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] transition hover:bg-gray-50">Cancel</button>
                            <button :disabled="submitting"
                                    class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] disabled:opacity-60">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 21V4m0 0h11l-1.5 3.5L16 11H5"/></svg>
                                <span x-text="submitting ? 'Creating...' : 'Create Milestone'">Create Milestone</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function milestoneForm() {
                return {
                    color: @js(\App\Models\ProjectMilestone::COLORS[0]),
                    icon: 'flag',
                    tasks: [],
                    submitting: false,
                    sync(e) {
                        const v = e.target.value;
                        this.tasks = e.target.checked ? [...this.tasks, v] : this.tasks.filter(t => t !== v);
                    },
                };
            }
        </script>
    @endif
</div>
