@extends('admin.layouts.app')
@section('title', 'Tasks')

@php
    $me = auth()->user();
    $priorityBadge = ['low' => 'bg-gray-100 text-gray-500', 'medium' => 'bg-amber-50 text-amber-600', 'high' => 'bg-orange-50 text-orange-600', 'urgent' => 'bg-red-50 text-red-600'];
    $canEdit = $me->allows('tasks', 'edit');
    $canStatus = $me->allows('tasks', 'status');
    $canCreate = $me->allows('tasks', 'create');
    $canDelete = $me->allows('tasks', 'delete');
@endphp

@section('content')
    <div x-data="{ addOpen: {{ $errors->any() ? 'true' : 'false' }} }">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-[var(--color-heading)]">Tasks</h1>
                <p class="mt-1 text-sm text-[var(--color-muted)]">Workspace &rsaquo; Tasks</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @php $hasFilters = request()->hasAny(['search', 'project', 'assignee', 'priority', 'from', 'to']) || request()->filled('status'); @endphp
                @if ($canCreate)
                    <button type="button" @click="addOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Task
                    </button>
                @endif
                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-task-filters'))" title="Filters"
                        class="relative grid h-11 w-11 place-items-center rounded-lg border border-gray-200 text-[var(--color-primary)] transition hover:bg-indigo-50">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10M10 19h4"/></svg>
                    @if ($hasFilters)<span class="absolute -right-1 -top-1 h-3 w-3 rounded-full border-2 border-white bg-[var(--color-primary)]"></span>@endif
                </button>
            </div>
        </div>

        {{-- Stats — each card is a filter --}}
        @php
            $st = request('status', 'hide_completed');
            $cards = [
                ['My Tasks', $stats['mine'], 'text-emerald-700', ['mine' => 1, 'status' => 'hide_completed'], $mine && $st !== 'overdue'],
                ['Open Task', $stats['open'], 'text-blue-700', ['mine' => 0, 'status' => 'hide_completed'], ! $mine && $st === 'hide_completed'],
                ['Overdue', $stats['overdue'], 'text-red-600', ['mine' => 0, 'status' => 'overdue'], ! $mine && $st === 'overdue'],
                ['Total Tasks', $stats['total'], 'text-[var(--color-heading)]', ['mine' => 0, 'status' => 'all'], ! $mine && $st === 'all'],
            ];
        @endphp
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($cards as [$label, $value, $tone, $params, $active])
                <a href="{{ route('admin.tasks.index') }}?{{ http_build_query($params) }}"
                   class="rounded-xl border bg-white p-4 shadow-sm transition hover:shadow {{ $active ? 'border-[var(--color-primary)] ring-1 ring-[var(--color-primary)]' : 'border-gray-100 hover:border-gray-200' }}">
                    <p class="text-xs font-medium text-[var(--color-muted)]">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold {{ $tone }}">{{ $value }}</p>
                </a>
            @endforeach
        </div>

        {{-- ===== Filters drawer (right side) ===== --}}
        <div x-data="{ open: false }" x-cloak @open-task-filters.window="open = true" @keydown.escape.window="open = false">
            <div class="fixed inset-0 z-50" :class="open ? '' : 'pointer-events-none'">
                <div class="absolute inset-0 bg-black/30" style="transition:opacity .4s ease, backdrop-filter .4s ease"
                     :style="open ? 'opacity:1; backdrop-filter:blur(2px)' : 'opacity:0; backdrop-filter:blur(0)'" @click="open = false"></div>
                <div class="absolute right-0 top-0 flex h-full w-full max-w-sm flex-col bg-white shadow-2xl"
                     style="transition:transform .42s cubic-bezier(.32,.72,0,1)" :style="open ? 'transform:translateX(0)' : 'transform:translateX(100%)'">
                    <div class="flex items-start justify-between border-b border-gray-100 px-6 py-5">
                        <div>
                            <h2 class="text-lg font-bold text-[var(--color-heading)]">Filters</h2>
                            <p class="mt-0.5 text-sm text-[var(--color-muted)]">Refine your task list</p>
                        </div>
                        <button type="button" @click="open = false" class="grid h-9 w-9 place-items-center rounded-lg bg-gray-50 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>

                    <form method="GET" action="{{ route('admin.tasks.index') }}" class="flex flex-1 flex-col overflow-y-auto px-6 py-5">
                        <input type="hidden" name="mine" value="{{ $mine ? 1 : 0 }}">
                        @php
                            $pillOn = 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white shadow-sm';
                            $pillOff = 'border-gray-200 bg-white text-[var(--color-muted)] hover:bg-gray-50 hover:text-[var(--color-heading)]';
                            $pillBase = 'rounded-full border px-3 py-1.5 text-xs font-semibold transition';
                            $statusOpts = array_merge(['hide_completed' => 'Hide Completed', 'all' => 'All Status'], $statusFilter);
                        @endphp

                        <div class="space-y-5">
                            {{-- Search --}}
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-[var(--color-heading)]">Search</label>
                                <div class="relative">
                                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Task or project name…"
                                           class="h-11 w-full rounded-lg border-gray-200 pl-9 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                </div>
                            </div>

                            {{-- Status --}}
                            <div x-data="{ val: @js(request('status', 'hide_completed')) }">
                                <label class="mb-2 block text-sm font-semibold text-[var(--color-heading)]">Status</label>
                                <input type="hidden" name="status" :value="val">
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($statusOpts as $k => $v)
                                        <button type="button" @click="val = @js((string) $k)" :class="val === @js((string) $k) ? '{{ $pillOn }}' : '{{ $pillOff }}'" class="{{ $pillBase }}">{{ $v }}</button>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Priority --}}
                            <div x-data="{ val: @js(request('priority', '')) }">
                                <label class="mb-2 block text-sm font-semibold text-[var(--color-heading)]">Priority</label>
                                <input type="hidden" name="priority" :value="val">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="val = ''" :class="val === '' ? '{{ $pillOn }}' : '{{ $pillOff }}'" class="{{ $pillBase }}">Any</button>
                                    @foreach (\App\Models\ProjectTask::PRIORITIES as $k => $v)
                                        <button type="button" @click="val = @js((string) $k)" :class="val === @js((string) $k) ? '{{ $pillOn }}' : '{{ $pillOff }}'" class="{{ $pillBase }}">{{ $v }}</button>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Project --}}
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-[var(--color-heading)]">Project</label>
                                <x-admin.searchable-select name="project"
                                    :options="$projects->map(fn ($p) => ['id' => $p->id, 'label' => $p->name])"
                                    :selected="request('project')" placeholder="Search project…" clear-label="All projects" />
                            </div>

                            {{-- Assignee --}}
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-[var(--color-heading)]">Assignee</label>
                                <x-admin.searchable-select name="assignee"
                                    :options="$assignees->map(fn ($a) => ['id' => $a->id, 'label' => $a->name])"
                                    :selected="request('assignee')" placeholder="Search staff…" clear-label="Anyone" />
                            </div>

                            {{-- Due date range --}}
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-[var(--color-heading)]">Due date</label>
                                <div class="flex items-center gap-2">
                                    <input type="date" name="from" value="{{ request('from') }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    <span class="shrink-0 text-gray-300">to</span>
                                    <input type="date" name="to" value="{{ request('to') }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex gap-3 border-t border-gray-100 pt-5">
                            <a href="{{ route('admin.tasks.index') }}" class="flex-1 rounded-lg border border-gray-200 px-4 py-3 text-center text-sm font-bold text-[var(--color-heading)] hover:bg-gray-50">Clear Filters</a>
                            <button class="flex-1 rounded-lg bg-[var(--color-primary)] px-4 py-3 text-sm font-bold text-white hover:bg-[var(--color-primary-hover)]">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        @if ($tasks->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center">
                <p class="text-sm text-gray-400">No tasks found.</p>
                @if ($canEdit)<button type="button" @click="addOpen = true" class="mt-2 text-sm font-semibold text-[var(--color-primary)] hover:underline">Add your first task</button>@endif
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
                <table class="w-full text-sm" style="min-width:1000px">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/70 text-left text-[11px] uppercase tracking-wide text-gray-400">
                            <th class="px-4 py-3 font-semibold">Code</th>
                            <th class="px-4 py-3 font-semibold">Task</th>
                            <th class="px-4 py-3 font-semibold">Due Date</th>
                            <th class="px-4 py-3 font-semibold">Estimate</th>
                            <th class="px-4 py-3 font-semibold">Completed On</th>
                            <th class="px-4 py-3 font-semibold">Assigned To</th>
                            <th class="w-40 px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($tasks as $task)
                            <tr class="transition hover:bg-gray-50/60">
                                <td class="px-4 py-3.5 whitespace-nowrap text-xs font-semibold text-gray-400">{{ $task->code() }}</td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.tasks.show', $task) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $task->title }}</a>
                                        <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $priorityBadge[$task->priority] ?? 'bg-gray-100 text-gray-500' }}">{{ $task->priority }}</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-gray-400">
                                        <a href="{{ route('admin.projects.show', $task->project_id) }}" class="hover:text-[var(--color-primary)]">{{ $task->project?->name }}</a>
                                        @if ($task->milestone) · {{ $task->milestone->title }}@endif
                                        @if ($task->subtasks_count) · {{ $task->subtasks_count }} subtask{{ $task->subtasks_count === 1 ? '' : 's' }}@endif
                                    </p>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap {{ $task->isOverdue() ? 'font-semibold text-red-500' : 'text-[var(--color-muted)]' }}">{{ $task->due_date?->format('d M, Y') ?? '—' }}</td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-[var(--color-muted)]">{{ $task->estimateLabel() ?? '—' }}</td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-[var(--color-muted)]">{{ $task->completed_at?->format('d M, Y') ?? '—' }}</td>
                                <td class="px-4 py-3.5">
                                    @if ($task->assignee)
                                        <div class="flex items-center gap-2">
                                            @include('admin.projects._avatars', ['users' => [$task->assignee], 'max' => 1, 'size' => 6])
                                            <span class="text-xs text-[var(--color-heading)]">{{ $task->assignee->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-300">Unassigned</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    @php $opts = $task->project?->statusOptions() ?? []; $sc = $task->statusColor(); @endphp
                                    @if ($canStatus ?? $canEdit)
                                        <form method="POST" action="{{ route('admin.tasks.status', $task) }}" data-turbo="false" class="relative inline-flex">
                                            @csrf
                                            <span class="pointer-events-none absolute left-3 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full" style="background: {{ $sc }}"></span>
                                            <select name="status" onchange="this.form.submit()"
                                                    class="h-8 cursor-pointer appearance-none rounded-full border-0 pl-7 pr-7 text-xs font-semibold focus:ring-2"
                                                    style="background: {{ $sc }}1a; color: {{ $sc }}">
                                                @foreach ($opts as $k => $v)<option value="{{ $k }}" @selected($task->status === $k)>{{ $v }}</option>@endforeach
                                            </select>
                                            <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2">
                                                <svg class="h-3 w-3" style="color: {{ $sc }}" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                                            </span>
                                        </form>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" style="background: {{ $sc }}1a; color: {{ $sc }}">
                                            <span class="h-2 w-2 rounded-full" style="background: {{ $sc }}"></span>{{ $task->statusLabel() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="relative inline-block" x-data="{ open: false }" @click.outside="open = false">
                                        <button type="button" @click="open = !open" title="Actions"
                                                class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                        </button>
                                        <div x-show="open" x-cloak class="absolute right-0 z-30 mt-1.5 w-36 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 text-left shadow-lg">
                                            <a href="{{ route('admin.tasks.show', $task) }}" class="flex items-center gap-2 px-3.5 py-2 text-xs font-medium text-[var(--color-heading)] hover:bg-gray-50">
                                                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                                View
                                            </a>
                                            @if ($canEdit)
                                                <a href="{{ route('admin.tasks.show', $task) }}?edit=1" class="flex items-center gap-2 px-3.5 py-2 text-xs font-medium text-[var(--color-heading)] hover:bg-gray-50">
                                                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg>
                                                    Edit
                                                </a>
                                            @endif
                                            @if ($canDelete ?? $me->allows('tasks', 'delete'))
                                                <form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" data-turbo="false" onsubmit="return confirm('Delete this task?')">
                                                    @csrf @method('DELETE')
                                                    <button class="flex w-full items-center gap-2 px-3.5 py-2 text-left text-xs font-medium text-red-600 hover:bg-red-50">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5h6v2m2 0v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7"/></svg>
                                                        Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $tasks->links() }}</div>
        @endif

        {{-- Add Task modal — same shape as inside a project, plus a project picker --}}
        @if ($canEdit)
            <div x-show="addOpen" x-cloak @keydown.escape.window="addOpen = false"
                 x-data="globalTaskForm(@js($projectMeta), {{ $nextTaskId }}, {{ $me->id }}, @js(old('project_id')))">
                <div x-show="addOpen" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="addOpen = false"></div>
                <div x-show="addOpen" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 py-10" @click.self="addOpen = false">
                    <div class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1ZM8 6H6a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-2M12 11v6M9 14h6"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-bold text-[var(--color-heading)]">Add New Task</h3>
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500" x-text="taskCode" x-show="projectId"></span>
                                    </div>
                                    <p class="text-xs text-[var(--color-muted)]">Create a new task and add details</p>
                                </div>
                            </div>
                            <button type="button" @click="addOpen = false" class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                            </button>
                        </div>

                        @if ($errors->any())
                            <div class="mt-4 mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                <ul class="list-inside list-disc space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.tasks.store') }}" enctype="multipart/form-data" data-turbo="false" @submit="submitting = true">
                            @csrf
                            <div class="grid gap-0 lg:grid-cols-3">
                                {{-- Main --}}
                                <div class="space-y-5 p-6 lg:col-span-2">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Task Title <span class="text-red-500">*</span></label>
                                        <input type="text" name="title" required maxlength="120" x-model="title" value="{{ old('title') }}"
                                               placeholder="Enter a clear and concise task title"
                                               class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                        <p class="mt-1 text-right text-xs text-[var(--color-muted)]"><span x-text="title.length">0</span> / 120</p>
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Description</label>
                                        <x-admin.rich-editor name="description" placeholder="Describe the task in detail..." :min-height="150" />
                                        <p class="mt-1 text-xs text-[var(--color-muted)]">Bold, italics, lists and links are supported.</p>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Project <span class="text-red-500">*</span></label>
                                            <select name="project_id" required x-model="projectId" @change="onProject()"
                                                    class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                                <option value="">Select a project</option>
                                                @foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>@endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Task Board Column <span class="text-red-500">*</span></label>
                                            <select name="status" required x-model="status" :disabled="!projectId"
                                                    class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] is-disabled">
                                                <template x-if="!projectId"><option value="">Pick a project first</option></template>
                                                <template x-for="c in meta.columns" :key="c.key">
                                                    <option :value="c.key" x-text="c.name"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Priority</label>
                                            <select name="priority" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                                @foreach (\App\Models\ProjectTask::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(old('priority', 'medium') === $k)>{{ $v }}</option>@endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Labels</label>
                                            <div class="flex flex-wrap items-center gap-1.5 rounded-lg border border-gray-200 px-2 py-1.5 focus-within:border-[var(--color-primary)]" style="min-height:44px">
                                                <template x-for="(label, i) in labels" :key="i">
                                                    <span class="inline-flex items-center gap-1 rounded bg-[var(--color-primary-soft)] px-2 py-1 text-xs font-semibold text-[var(--color-primary)]">
                                                        <span x-text="label"></span>
                                                        <input type="hidden" name="labels[]" :value="label">
                                                        <button type="button" @click="labels.splice(i, 1)" class="opacity-60 hover:opacity-80">&times;</button>
                                                    </span>
                                                </template>
                                                <input type="text" x-model="labelDraft" @keydown.enter.prevent="addLabel()"
                                                       @keydown.backspace="if (!labelDraft && labels.length) labels.pop()"
                                                       placeholder="Type and press Enter"
                                                       class="min-w-0 flex-1 border-0 p-0 text-sm placeholder:text-gray-400 focus:ring-0" style="background:transparent">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-gray-200 p-4" x-data="{ over: false, names: [] }">
                                        <p class="flex items-center gap-2 text-sm font-semibold text-[var(--color-heading)]">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.5 12.8 20.7a5 5 0 0 1-7-7l8.2-8.3a3.3 3.3 0 1 1 4.7 4.7l-8.2 8.2a1.7 1.7 0 0 1-2.4-2.4l7.6-7.5"/></svg>
                                            Attachments <span class="font-normal text-[var(--color-muted)]">(Optional)</span>
                                        </p>
                                        <label class="mt-3 flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed py-8 text-center transition"
                                               :class="over ? 'border-[var(--color-primary)] bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'"
                                               @dragover.prevent="over = true" @dragleave="over = false"
                                               @drop.prevent="over = false; $refs.files.files = $event.dataTransfer.files; names = [...$refs.files.files].map(f => f.name)">
                                            <svg class="h-7 w-7 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                                            <span class="mt-2 text-sm text-[var(--color-muted)]">Drag &amp; drop files here or <span class="font-semibold text-[var(--color-primary)]">browse</span></span>
                                            <span class="mt-0.5 text-xs text-gray-400">Supports: JPG, PNG, PDF, DOC, ZIP (Max. 20MB)</span>
                                            <input type="file" name="attachments[]" multiple x-ref="files" class="sr-only" @change="names = [...$event.target.files].map(f => f.name)">
                                        </label>
                                        <ul x-show="names.length" x-cloak class="mt-2 space-y-1">
                                            <template x-for="(n, i) in names" :key="i">
                                                <li class="truncate rounded-lg bg-gray-50 px-3 py-1.5 text-xs text-[var(--color-heading)]" x-text="n"></li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>

                                {{-- Sidebar --}}
                                <div class="task-modal-side space-y-5 border-gray-100 bg-gray-50 p-6">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Assignee</label>
                                        <select name="assigned_to" x-model="assignee"
                                                class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                            <option value="">Unassigned</option>
                                            @foreach ($assignees as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                        </select>
                                        <button type="button" @click="assignee = '{{ $me->id }}'" class="mt-1.5 text-xs font-semibold text-[var(--color-primary)] hover:underline">Assign to me</button>
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Due Date</label>
                                        <input type="date" name="due_date" value="{{ old('due_date') }}" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Estimated Time <span class="font-normal text-[var(--color-muted)]">(Optional)</span></label>
                                        <input type="text" name="estimate" maxlength="40" placeholder="e.g. 4h, 2d, 30m"
                                               class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                        <p class="mt-1 text-xs text-[var(--color-muted)]">A day counts as 8h, a week as 5 days.</p>
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Start Date <span class="font-normal text-[var(--color-muted)]">(Optional)</span></label>
                                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Milestone <span class="font-normal text-[var(--color-muted)]">(Optional)</span></label>
                                        <select name="milestone_id" :disabled="!projectId"
                                                class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] is-disabled">
                                            <option value="">No milestone</option>
                                            <template x-for="m in meta.milestones" :key="m.id">
                                                <option :value="m.id" x-text="m.title"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Task Type</label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <label class="cursor-pointer rounded-xl border p-3 transition"
                                                   :class="type === 'task' ? 'border-[var(--color-primary)] bg-white ring-1 ring-[var(--color-primary)]' : 'border-gray-200 bg-white hover:bg-gray-50'">
                                                <span class="flex items-center gap-2">
                                                    <input type="radio" x-model="type" value="task" class="h-4 w-4 accent-[var(--color-primary)]">
                                                    <span class="text-sm font-semibold text-[var(--color-heading)]">Task</span>
                                                </span>
                                                <span class="mt-0.5 block pl-7 text-xs text-[var(--color-muted)]">A regular task</span>
                                            </label>
                                            <label class="cursor-pointer rounded-xl border p-3 transition"
                                                   :class="type === 'subtask' ? 'border-[var(--color-primary)] bg-white ring-1 ring-[var(--color-primary)]' : 'border-gray-200 bg-white hover:bg-gray-50'">
                                                <span class="flex items-center gap-2">
                                                    <input type="radio" x-model="type" value="subtask" class="h-4 w-4 accent-[var(--color-primary)]">
                                                    <span class="text-sm font-semibold text-[var(--color-heading)]">Subtask</span>
                                                </span>
                                                <span class="mt-0.5 block pl-7 text-xs text-[var(--color-muted)]">Break down a task</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div x-show="type === 'subtask'" x-cloak>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Parent Task <span class="text-red-500">*</span></label>
                                        <select name="parent_id" :required="type === 'subtask'" :disabled="type !== 'subtask' || !projectId"
                                                class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                            <option value="">Select parent task</option>
                                            <template x-for="t in meta.tasks" :key="t.id">
                                                <option :value="t.id" x-text="t.title"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div x-show="type === 'task'" x-cloak class="flex gap-2 rounded-xl bg-[var(--color-primary-soft)] p-3 text-xs text-[var(--color-heading)]">
                                        <svg class="h-4 w-4 shrink-0 text-[var(--color-primary)]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v5m0-8h.01"/></svg>
                                        You can break down larger tasks into subtasks after creating this task.
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-6 py-4">
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-[var(--color-heading)]">
                                    <input type="checkbox" name="create_another" value="1" class="h-4 w-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                    Create Another
                                </label>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="addOpen = false" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] transition hover:bg-gray-50">Cancel</button>
                                    <button :disabled="submitting || !projectId"
                                            class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] disabled:opacity-60">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>
                                        <span x-text="submitting ? 'Creating...' : 'Create Task'">Create Task</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <style>
                .task-modal-side { border-top-width: 1px; }
                @media (min-width: 1024px) { .task-modal-side { border-top-width: 0; border-left-width: 1px; } }
            </style>

            <script>
                function globalTaskForm(meta, nextId, meId, preselected) {
                    return {
                        all: meta,
                        projectId: preselected || '',
                        title: @js(old('title', '')),
                        labels: [], labelDraft: '',
                        assignee: '', type: 'task', status: '',
                        submitting: false,
                        get meta() { return this.all[this.projectId] || { code: '', columns: [], milestones: [], tasks: [] }; },
                        get taskCode() { return this.meta.code ? this.meta.code + '-' + nextId : ''; },
                        init() { if (this.projectId) this.onProject(); },
                        onProject() {
                            // Columns, milestones and parent tasks all belong to the chosen project.
                            this.status = this.meta.columns[0]?.key || '';
                        },
                        addLabel(value) {
                            const v = (value ?? this.labelDraft).trim().replace(/,$/, '');
                            if (v && !this.labels.includes(v) && this.labels.length < 12) this.labels.push(v);
                            this.labelDraft = '';
                        },
                    };
                }
            </script>
        @endif
    </div>
@endsection
