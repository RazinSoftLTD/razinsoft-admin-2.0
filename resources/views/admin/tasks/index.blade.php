@extends('admin.layouts.app')
@section('title', 'Tasks')

@php
    $me = auth()->user();
    $priorityBadge = ['low' => 'bg-gray-100 text-gray-500', 'medium' => 'bg-amber-50 text-amber-600', 'high' => 'bg-orange-50 text-orange-600', 'urgent' => 'bg-red-50 text-red-600'];
    $canEdit = $me->allows('projects', 'edit');
@endphp

@section('content')
    <div x-data="{ addOpen: {{ $errors->any() ? 'true' : 'false' }} }">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-[var(--color-heading)]">Tasks</h1>
                <p class="mt-1 text-sm text-[var(--color-muted)]">Workspace &rsaquo; Tasks</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ request()->fullUrlWithQuery(['mine' => request()->boolean('mine') ? null : 1]) }}"
                   class="inline-flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold {{ request()->boolean('mine') ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)] text-[var(--color-primary)]' : 'border-gray-200 text-[var(--color-muted)] hover:bg-gray-50' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path stroke-linecap="round" d="M4.5 19.5a7.5 7.5 0 0 1 15 0"/></svg>
                    My Tasks
                </a>
                @if ($canEdit)
                    <button type="button" @click="addOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Task
                    </button>
                @endif
            </div>
        </div>

        {{-- Stats --}}
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([['Total Tasks', $stats['total'], 'text-[var(--color-heading)]'], ['Open', $stats['open'], 'text-blue-700'], ['Overdue', $stats['overdue'], 'text-red-600'], ['My Open Tasks', $stats['mine'], 'text-emerald-700']] as [$label, $value, $tone])
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium text-[var(--color-muted)]">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        {{-- Filters --}}
        <form method="GET" class="mb-5 flex flex-wrap items-center gap-2 rounded-xl border border-gray-100 bg-white p-3 shadow-sm">
            @if (request()->boolean('mine'))<input type="hidden" name="mine" value="1">@endif
            <div class="flex items-center gap-1.5 text-sm">
                <span class="text-xs font-semibold text-[var(--color-muted)]">Due</span>
                <input type="date" name="from" value="{{ request('from') }}" class="h-10 rounded-lg border-gray-200 text-sm">
                <span class="text-gray-300">→</span>
                <input type="date" name="to" value="{{ request('to') }}" class="h-10 rounded-lg border-gray-200 text-sm">
            </div>
            <select name="status" class="h-10 rounded-lg border-gray-200 text-sm">
                <option value="hide_completed" @selected(request('status', 'hide_completed') === 'hide_completed')>Hide Completed</option>
                <option value="all" @selected(request('status') === 'all')>All Status</option>
                @foreach ($statusFilter as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select>
            <select name="project" class="h-10 max-w-48 rounded-lg border-gray-200 text-sm">
                <option value="">All Projects</option>
                @foreach ($projects as $p)<option value="{{ $p->id }}" @selected(request('project') == $p->id)>{{ $p->name }}</option>@endforeach
            </select>
            <select name="assignee" class="h-10 rounded-lg border-gray-200 text-sm">
                <option value="">Any Assignee</option>
                @foreach ($assignees as $a)<option value="{{ $a->id }}" @selected(request('assignee') == $a->id)>{{ $a->name }}</option>@endforeach
            </select>
            <select name="priority" class="h-10 rounded-lg border-gray-200 text-sm">
                <option value="">Any Priority</option>
                @foreach (\App\Models\ProjectTask::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(request('priority') === $k)>{{ $v }}</option>@endforeach
            </select>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Start typing to search…" class="h-10 w-52 rounded-lg border-gray-200 pl-9 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
            </div>
            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Filter</button>
            @if (request()->hasAny(['search', 'status', 'project', 'assignee', 'priority', 'from', 'to']))
                <a href="{{ route('admin.tasks.index') }}" class="text-xs font-semibold text-gray-400 hover:text-red-500">Clear</a>
            @endif
        </form>

        @if ($tasks->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center">
                <p class="text-sm text-gray-400">No tasks found.</p>
                @if ($canEdit)<button type="button" @click="addOpen = true" class="mt-2 text-sm font-semibold text-[var(--color-primary)] hover:underline">Add your first task</button>@endif
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
                <table class="w-full min-w-[1000px] text-sm">
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
                                    @php $opts = $task->project?->statusOptions() ?? []; @endphp
                                    @if ($canEdit)
                                        <form method="POST" action="{{ route('admin.tasks.status', $task) }}" data-turbo="false">
                                            @csrf
                                            <div class="relative inline-flex items-center">
                                                <span class="pointer-events-none absolute left-2.5 h-2 w-2 rounded-full" style="background: {{ $task->statusColor() }};"></span>
                                                <select name="status" onchange="this.form.submit()" class="h-8 rounded-lg border-gray-200 pl-6 pr-7 text-xs font-medium text-[var(--color-heading)]">
                                                    @foreach ($opts as $k => $v)<option value="{{ $k }}" @selected($task->status === $k)>{{ $v }}</option>@endforeach
                                                </select>
                                            </div>
                                        </form>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-heading)]"><span class="h-2 w-2 rounded-full" style="background: {{ $task->statusColor() }};"></span>{{ $task->statusLabel() }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <a href="{{ route('admin.tasks.show', $task) }}" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Open">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                        @if ($me->allows('projects', 'delete'))
                                            <form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" data-turbo="false" onsubmit="return confirm('Delete this task?')">
                                                @csrf @method('DELETE')
                                                <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500" title="Delete">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m3 0v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $tasks->links() }}</div>
        @endif

        {{-- Add Task modal (global: pick the project) --}}
        @if ($canEdit)
            <div x-show="addOpen" x-cloak @keydown.escape.window="addOpen = false">
                <div x-show="addOpen" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="addOpen = false"></div>
                <div x-show="addOpen" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-16" @click.self="addOpen = false">
                    <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                            <h3 class="text-base font-bold text-[var(--color-heading)]">Add Task</h3>
                            <button type="button" @click="addOpen = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                        </div>
                        @if ($errors->any())
                            <div class="mx-5 mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><ul class="list-inside list-disc space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                        @endif
                        <form method="POST" action="{{ route('admin.tasks.store') }}" data-turbo="false" class="space-y-4 px-5 py-4">
                            @csrf
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Project <span class="text-red-500">*</span></label>
                                    <x-admin.searchable-select name="project_id" :options="$projects->map(fn ($p) => ['id' => $p->id, 'label' => $p->name.' ('.$p->code.')'])" :selected="old('project_id')" placeholder="Search project…" :allow-clear="false" required />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Assigned To</label>
                                    <x-admin.searchable-select name="assigned_to" :options="$assignees->map(fn ($a) => ['id' => $a->id, 'label' => $a->name])" :selected="old('assigned_to')" placeholder="Search staff…" clear-label="Unassigned" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" value="{{ old('title') }}" required placeholder="What needs to be done?" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Description</label>
                                <textarea name="description" rows="3" placeholder="Optional details…" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">{{ old('description') }}</textarea>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div><label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Start Date</label><input type="date" name="start_date" value="{{ old('start_date') }}" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]"></div>
                                <div><label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Due Date</label><input type="date" name="due_date" value="{{ old('due_date') }}" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]"></div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Status</label>
                                    <select name="status" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                        @foreach ($statusFilter as $k => $v)<option value="{{ $k }}" @selected(old('status', 'todo') === $k)>{{ $v }}</option>@endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Priority</label>
                                    <select name="priority" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                        @foreach (\App\Models\ProjectTask::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(old('priority', 'medium') === $k)>{{ $v }}</option>@endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Estimate</label>
                                    <div class="flex items-center gap-1.5">
                                        <input type="number" name="estimated_hours" value="{{ old('estimated_hours') }}" min="0" placeholder="h" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                        <input type="number" name="estimated_extra_minutes" value="{{ old('estimated_extra_minutes') }}" min="0" max="59" placeholder="m" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" @click="addOpen = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                                <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save Task</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
