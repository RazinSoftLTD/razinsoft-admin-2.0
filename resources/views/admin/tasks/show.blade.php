@extends('admin.layouts.app')
@section('title', $task->title)

@php
    $me = auth()->user();
    $canEdit = $me->allows('projects', 'edit');
    $statusDot = ['backlog' => 'bg-slate-400', 'todo' => 'bg-sky-500', 'in_progress' => 'bg-blue-500', 'review' => 'bg-purple-500', 'completed' => 'bg-emerald-500', 'cancelled' => 'bg-gray-400'];
    $priorityBadge = ['low' => 'bg-gray-100 text-gray-500', 'medium' => 'bg-amber-50 text-amber-600', 'high' => 'bg-orange-50 text-orange-600', 'urgent' => 'bg-red-50 text-red-600'];
@endphp

@section('content')
    <div x-data="{ editOpen: {{ $errors->any() ? 'true' : 'false' }} }">
        <a href="{{ route('admin.projects.show', $task->project_id) }}?tab=tasks" class="mb-4 inline-flex items-center gap-1.5 text-sm text-[var(--color-muted)] hover:text-[var(--color-heading)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> {{ $task->project?->name }}
        </a>

        {{-- Header --}}
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $task->title }}</h1>
                    <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-bold text-gray-500">{{ $task->code() }}</span>
                    <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $priorityBadge[$task->priority] ?? 'bg-gray-100 text-gray-500' }}">{{ $task->priority }}</span>
                    @if ($task->isOverdue())<span class="rounded-md bg-red-50 px-2 py-0.5 text-xs font-bold text-red-600">Overdue</span>@endif
                </div>
                @if ($task->parent)<p class="mt-1 text-sm text-[var(--color-muted)]">Subtask of <a href="{{ route('admin.tasks.show', $task->parent) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $task->parent->title }}</a></p>@endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($canEdit)
                    <form method="POST" action="{{ route('admin.tasks.status', $task) }}" data-turbo="false">
                        @csrf
                        <div class="relative inline-flex items-center">
                            <span class="pointer-events-none absolute left-2.5 h-2 w-2 rounded-full" style="background: {{ $task->statusColor() }};"></span>
                            <select name="status" onchange="this.form.submit()" class="h-10 rounded-lg border-gray-200 pl-6 pr-8 text-sm font-medium text-[var(--color-heading)]">
                                @foreach ($statusOptions as $k => $v)<option value="{{ $k }}" @selected($task->status === $k)>{{ $v }}</option>@endforeach
                            </select>
                        </div>
                    </form>
                    <button type="button" @click="editOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg> Edit
                    </button>
                @endif
                @if ($me->allows('projects', 'delete'))
                    <form method="POST" action="{{ route('admin.tasks.destroy', ['task' => $task, 'redirect' => 'project']) }}" data-turbo="false" onsubmit="return confirm('Delete this task?')">
                        @csrf @method('DELETE')
                        <button class="inline-flex items-center gap-2 rounded-lg border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Delete</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            {{-- Main column --}}
            <div class="space-y-5 lg:col-span-2">
                {{-- Description --}}
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-bold text-[var(--color-heading)]">Description</h3>
                    <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-[var(--color-muted)]">{{ $task->description ?: 'No description.' }}</p>
                </div>

                {{-- Subtasks --}}
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-[var(--color-heading)]">Subtasks <span class="ml-1 text-xs font-normal text-gray-400">{{ $task->subtasks->where('status', 'completed')->count() }}/{{ $task->subtasks->count() }} done</span></h3>
                    </div>
                    <ul class="mt-3 space-y-2">
                        @forelse ($task->subtasks as $sub)
                            <li class="flex items-center gap-3 rounded-lg border border-gray-50 px-3 py-2.5">
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('admin.tasks.status', $sub) }}" data-turbo="false">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $sub->status === 'completed' ? 'todo' : 'completed' }}">
                                        <button class="grid h-5 w-5 place-items-center rounded border {{ $sub->status === 'completed' ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-gray-300 hover:border-[var(--color-primary)]' }}">
                                            @if ($sub->status === 'completed')<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>@endif
                                        </button>
                                    </form>
                                @endif
                                <span class="flex-1 text-sm {{ $sub->status === 'completed' ? 'text-gray-400 line-through' : 'text-[var(--color-heading)]' }}">{{ $sub->title }}</span>
                                @if ($sub->assignee)@include('admin.projects._avatars', ['users' => [$sub->assignee], 'max' => 1, 'size' => 5])@endif
                                @if ($me->allows('projects', 'delete'))
                                    <form method="POST" action="{{ route('admin.tasks.destroy', $sub) }}" data-turbo="false" onsubmit="return confirm('Delete subtask?')">
                                        @csrf @method('DELETE')
                                        <button class="text-gray-300 hover:text-red-500"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                                    </form>
                                @endif
                            </li>
                        @empty
                            <li class="text-sm text-gray-300">No subtasks.</li>
                        @endforelse
                    </ul>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('admin.tasks.store') }}" data-turbo="false" class="mt-3 flex items-center gap-2">
                            @csrf
                            <input type="hidden" name="project_id" value="{{ $task->project_id }}">
                            <input type="hidden" name="parent_id" value="{{ $task->id }}">
                            <input type="hidden" name="status" value="todo">
                            <input type="hidden" name="priority" value="medium">
                            <input type="text" name="title" required placeholder="Add a subtask…" class="h-10 flex-1 rounded-lg border-gray-200 text-sm">
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                        </form>
                    @endif
                </div>

                {{-- Comments --}}
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-bold text-[var(--color-heading)]">Comments <span class="ml-1 text-xs font-normal text-gray-400">{{ $task->comments->count() }}</span></h3>
                    <ul class="mt-4 space-y-4">
                        @forelse ($task->comments as $comment)
                            <li class="flex gap-3">
                                @include('admin.projects._avatars', ['users' => [$comment->user], 'max' => 1, 'size' => 8])
                                <div class="min-w-0 flex-1 rounded-xl bg-gray-50/80 px-4 py-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-bold text-[var(--color-heading)]">{{ $comment->user?->name }}</p>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                                            @if ($comment->user_id === $me->id || $me->isAdmin())
                                                <form method="POST" action="{{ route('admin.tasks.comments.destroy', [$task, $comment]) }}" data-turbo="false" onsubmit="return confirm('Delete comment?')">
                                                    @csrf @method('DELETE')
                                                    <button class="text-gray-300 hover:text-red-500"><svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                    <p class="mt-1 whitespace-pre-line text-sm text-[var(--color-muted)]">{{ $comment->body }}</p>
                                </div>
                            </li>
                        @empty
                            <li class="text-sm text-gray-300">No comments yet — start the discussion.</li>
                        @endforelse
                    </ul>
                    <form method="POST" action="{{ route('admin.tasks.comments.store', $task) }}" data-turbo="false" class="mt-4 flex items-start gap-2">
                        @csrf
                        <textarea name="body" rows="2" required placeholder="Write a comment…" class="flex-1 rounded-lg border-gray-200 text-sm"></textarea>
                        <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Send</button>
                    </form>
                </div>
            </div>

            {{-- Meta sidebar --}}
            <aside class="h-fit space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-bold text-[var(--color-heading)]">Details</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-400">Project</dt><dd class="text-right"><a href="{{ route('admin.projects.show', $task->project_id) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $task->project?->name }}</a></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-400">Milestone</dt><dd class="font-medium text-[var(--color-heading)]">{{ $task->milestone?->title ?? '—' }}</dd></div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-gray-400">Assigned To</dt>
                        <dd>
                            @if ($task->assignee)
                                <span class="flex items-center gap-2">
                                    @include('admin.projects._avatars', ['users' => [$task->assignee], 'max' => 1, 'size' => 6])
                                    <span class="font-medium text-[var(--color-heading)]">{{ $task->assignee->name }}</span>
                                </span>
                            @else
                                <span class="text-gray-300">Unassigned</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-400">Start Date</dt><dd class="font-medium text-[var(--color-heading)]">{{ $task->start_date?->format('d M, Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-400">Due Date</dt><dd class="font-medium {{ $task->isOverdue() ? 'text-red-500' : 'text-[var(--color-heading)]' }}">{{ $task->due_date?->format('d M, Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-400">Estimate</dt><dd class="font-medium text-[var(--color-heading)]">{{ $task->estimateLabel() ?? '—' }}</dd></div>
                    @if ($task->completed_at)<div class="flex justify-between gap-2"><dt class="text-gray-400">Completed On</dt><dd class="font-medium text-emerald-600">{{ $task->completed_at->format('d M, Y') }}</dd></div>@endif
                    <div class="flex justify-between gap-2 border-t border-gray-50 pt-3"><dt class="text-gray-400">Created</dt><dd class="text-xs text-gray-400">{{ $task->created_at->format('d M, Y h:i A') }}</dd></div>
                </dl>
            </aside>
        </div>

        {{-- Edit modal --}}
        @if ($canEdit)
            <div x-show="editOpen" x-cloak @keydown.escape.window="editOpen = false">
                <div x-show="editOpen" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="editOpen = false"></div>
                <div x-show="editOpen" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-16" @click.self="editOpen = false">
                    <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                            <h3 class="text-base font-bold text-[var(--color-heading)]">Edit Task</h3>
                            <button type="button" @click="editOpen = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                        </div>
                        @if ($errors->any())
                            <div class="mx-5 mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><ul class="list-inside list-disc space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                        @endif
                        <form method="POST" action="{{ route('admin.tasks.update', $task) }}" data-turbo="false" class="space-y-4 px-5 py-4">
                            @csrf @method('PUT')
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" value="{{ old('title', $task->title) }}" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Description</label>
                                <textarea name="description" rows="3" class="w-full rounded-lg border-gray-200 text-sm">{{ old('description', $task->description) }}</textarea>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Milestone</label>
                                    <select name="milestone_id" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                        <option value="">No milestone</option>
                                        @foreach ($milestones as $m)<option value="{{ $m->id }}" @selected(old('milestone_id', $task->milestone_id) == $m->id)>{{ $m->title }}</option>@endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Assigned To</label>
                                    <select name="assigned_to" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                        <option value="">Unassigned</option>
                                        @foreach ($staff as $s)<option value="{{ $s->id }}" @selected(old('assigned_to', $task->assigned_to) == $s->id)>{{ $s->name }}</option>@endforeach
                                    </select>
                                </div>
                                <div><label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Start Date</label><input type="date" name="start_date" value="{{ old('start_date', $task->start_date?->toDateString()) }}" class="h-11 w-full rounded-lg border-gray-200 text-sm"></div>
                                <div><label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Due Date</label><input type="date" name="due_date" value="{{ old('due_date', $task->due_date?->toDateString()) }}" class="h-11 w-full rounded-lg border-gray-200 text-sm"></div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Status</label>
                                    <select name="status" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                        @foreach ($statusOptions as $k => $v)<option value="{{ $k }}" @selected(old('status', $task->status) === $k)>{{ $v }}</option>@endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Priority</label>
                                    <select name="priority" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                        @foreach (\App\Models\ProjectTask::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(old('priority', $task->priority) === $k)>{{ $v }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Estimate</label>
                                    <div class="flex items-center gap-2">
                                        <input type="number" name="estimated_hours" value="{{ old('estimated_hours', $task->estimated_minutes ? intdiv($task->estimated_minutes, 60) : '') }}" min="0" placeholder="Hours" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                        <input type="number" name="estimated_extra_minutes" value="{{ old('estimated_extra_minutes', $task->estimated_minutes ? $task->estimated_minutes % 60 : '') }}" min="0" max="59" placeholder="Minutes" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 pt-1">
                                <button type="button" @click="editOpen = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                                <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
