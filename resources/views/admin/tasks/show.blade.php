@extends('admin.layouts.app')
@section('title', $task->title)

@php
    $me = auth()->user();
    $canEdit = $me->allows('tasks', 'edit');
    $canStatus = $me->allows('tasks', 'status');
    $canComment = $me->allows('tasks', 'comments');
    $canAttach = $me->allows('tasks', 'attachments');
    $canTime = $me->allows('tasks', 'time');
    $statusDot = ['backlog' => 'bg-slate-400', 'todo' => 'bg-sky-500', 'in_progress' => 'bg-blue-500', 'review' => 'bg-purple-500', 'completed' => 'bg-emerald-500', 'cancelled' => 'bg-gray-400'];
    $priorityBadge = ['low' => 'bg-gray-100 text-gray-500', 'medium' => 'bg-amber-50 text-amber-600', 'high' => 'bg-orange-50 text-orange-600', 'urgent' => 'bg-red-50 text-red-600'];
@endphp

@section('content')
    <div x-data="{ editOpen: {{ ($errors->any() || request()->boolean('edit')) ? 'true' : 'false' }}, tab: 'comments' }">

        @if ($errors->has('timer'))
            <div class="mb-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v5m0 3h.01M12 3 2 20h20L12 3Z"/></svg>
                <span>{{ $errors->first('timer') }}</span>
            </div>
        @endif

        {{-- ===== Breadcrumb + actions ===== --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <nav class="flex flex-wrap items-center gap-2 text-sm text-[var(--color-muted)]">
                <a href="{{ route('admin.projects.show', $task->project_id) }}?tab=board" class="inline-flex items-center gap-1.5 font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Kanban
                </a>
                <span class="text-gray-300">|</span>
                <a href="{{ route('admin.projects.show', $task->project_id) }}?tab=board" class="hover:text-[var(--color-heading)]">{{ $task->project?->name }}</a>
                <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                <span>{{ $statusOptions[$task->status] ?? $task->status }}</span>
                <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                <span class="text-[var(--color-heading)]">Task Details</span>
            </nav>

            @if ($canEdit || $canStatus)
                <div class="flex flex-wrap items-center gap-2">
                    @if ($canEdit)
                    <button type="button" @click="editOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] transition hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg> Edit Task
                    </button>
                    @endif
                    @php $doneKey = collect($task->project?->doneKeys() ?? [])->first(); @endphp
                    @if ($canStatus && $doneKey && $task->status !== $doneKey)
                        <form method="POST" action="{{ route('admin.tasks.status', $task) }}" data-turbo="false">
                            @csrf
                            <input type="hidden" name="status" value="{{ $doneKey }}">
                            <button class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm-3-9 2 2 4-4"/></svg> Mark as Complete
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        <div class="grid items-start gap-4 lg:grid-cols-3">
            {{-- ===================== Left ===================== --}}
            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <span class="rounded-md px-2 py-1 text-[11px] font-bold uppercase" style="background: {{ $task->statusColor() }}1a; color: {{ $task->statusColor() }}">{{ $statusOptions[$task->status] ?? $task->status }}</span>
                        @if ($canStatus)
                            <form method="POST" action="{{ route('admin.tasks.status', $task) }}" data-turbo="false" class="relative">
                                @csrf
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2">
                                    <svg class="h-4 w-4" style="color: {{ $task->statusColor() }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4l2.5 1.5"/></svg>
                                </span>
                                <select name="status" onchange="this.form.submit()"
                                        class="h-9 cursor-pointer appearance-none rounded-full border-0 pl-9 pr-8 text-sm font-semibold"
                                        style="background: {{ $task->statusColor() }}1a; color: {{ $task->statusColor() }}">
                                    @foreach ($statusOptions as $k => $v)<option value="{{ $k }}" @selected($task->status === $k)>{{ $v }}</option>@endforeach
                                </select>
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                                    <svg class="h-3.5 w-3.5" style="color: {{ $task->statusColor() }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                                </span>
                            </form>
                        @endif
                    </div>

                    <h1 class="mt-3 text-2xl font-bold text-[var(--color-heading)]">{{ $task->title }}</h1>

                    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-[var(--color-muted)]">
                        <span class="font-semibold">{{ $task->code() }}</span>
                        @if ($task->due_date)
                            <span class="inline-flex items-center gap-1 {{ $task->isOverdue() ? 'font-semibold text-red-600' : '' }}">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M3 9h18M8 3v4M16 3v4"/></svg>
                                {{ $task->due_date->format('d M, Y') }}
                            </span>
                        @endif
                        <span class="rounded bg-gray-100 px-2 py-0.5">Created by {{ $task->creator?->name ?? 'Someone' }}</span>
                        @if ($task->parent)
                            <span>Subtask of <a href="{{ route('admin.tasks.show', $task->parent) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $task->parent->title }}</a></span>
                        @endif
                    </div>

                    @if (filled($task->description))
                        <div class="rich-surface mt-4 text-sm leading-relaxed text-[var(--color-muted)]">{!! $task->description !!}</div>
                    @else
                        <p class="mt-4 text-sm text-[var(--color-muted)]">No description.</p>
                    @endif

                    {{-- Labels --}}
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-semibold {{ $priorityBadge[$task->priority] ?? 'bg-gray-100 text-gray-500' }}">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 21V4m0 0h11l-1.5 3.5L16 11H5"/></svg>
                            {{ ucfirst($task->priority) }}
                        </span>
                        @foreach ((array) $task->labels as $label)
                            <span class="rounded bg-[var(--color-primary-soft)] px-2 py-1 text-xs font-semibold text-[var(--color-primary)]">{{ $label }}</span>
                        @endforeach
                        @if ($canEdit)
                            <button type="button" @click="editOpen = true" title="Edit labels"
                                    class="grid h-7 w-7 place-items-center rounded border border-dashed border-gray-200 text-gray-400 transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Attachments --}}
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-bold text-[var(--color-heading)]">Attachments ({{ $task->files->count() }})</h3>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($task->files as $file)
                            @php
                                $ext = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
                                $tint = match (true) {
                                    str_starts_with((string) $file->mime, 'image/') => ['bg-blue-50 text-blue-500', 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5'],
                                    $ext === 'pdf' => ['bg-red-50 text-red-500', 'M7 3h7l5 5v13H7zM14 3v5h5'],
                                    in_array($ext, ['fig', 'sketch', 'xd'], true) => ['bg-purple-50 text-purple-500', 'M12 3a3 3 0 0 1 0 6 3 3 0 0 0 0 6 3 3 0 1 1-3-3H9a3 3 0 1 1 0-6 3 3 0 0 1 3-3Z'],
                                    in_array($ext, ['zip', 'rar'], true) => ['bg-amber-50 text-amber-500', 'M6 3h12v18H6zM12 3v4M12 9v2'],
                                    default => ['bg-gray-100 text-gray-500', 'M7 3h7l5 5v13H7zM14 3v5h5'],
                                };
                            @endphp
                            <div class="rounded-xl border border-gray-100 p-4 transition hover:border-gray-200">
                                <span class="grid h-10 w-10 place-items-center rounded-lg {{ $tint[0] }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $tint[1] }}"/></svg>
                                </span>
                                <p class="mt-3 truncate text-sm font-semibold text-[var(--color-heading)]" title="{{ $file->name }}">{{ $file->name }}</p>
                                <div class="mt-1 flex items-center justify-between gap-2">
                                    <span class="text-xs text-[var(--color-muted)]">{{ $file->sizeLabel() }}</span>
                                    <span class="flex items-center gap-0.5">
                                        <a href="{{ route('admin.tasks.files.download', [$task, $file]) }}" title="Download"
                                           class="grid h-7 w-7 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0 4-4m-4 4-4-4M5 19h14"/></svg>
                                        </a>
                                        @if ($canAttach)
                                            <form method="POST" action="{{ route('admin.tasks.files.destroy', [$task, $file]) }}" onsubmit="return confirm('Remove this attachment?')">
                                                @csrf @method('DELETE')
                                                <button title="Remove" class="grid h-7 w-7 place-items-center rounded-lg text-gray-300 transition hover:bg-red-50 hover:text-red-500">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @endforeach

                        @if ($canAttach)
                            <form method="POST" action="{{ route('admin.tasks.files.store', $task) }}" enctype="multipart/form-data" x-data="{ over: false }">
                                @csrf
                                <label class="flex h-full cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed p-3 text-center transition" style="min-height:96px"
                                       :class="over ? 'border-[var(--color-primary)] bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'"
                                       @dragover.prevent="over = true" @dragleave="over = false"
                                       @drop.prevent="over = false; $refs.f.files = $event.dataTransfer.files; $refs.f.form.submit()">
                                    <svg class="h-6 w-6 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                                    <span class="mt-1 text-xs font-semibold text-[var(--color-primary)]">Upload File</span>
                                    <input type="file" name="files[]" multiple x-ref="f" class="sr-only" @change="$refs.f.form.submit()">
                                </label>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Tabs: Activity / Subtasks / Comments --}}
                <div class="rounded-2xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex gap-6 border-b border-gray-100 px-6">
                        @foreach ([['comments', 'Comments', $task->comments->count()], ['subtasks', 'Subtasks', $task->subtasks->count()], ['activity', 'Activity', $task->activities->count()]] as [$key, $label, $count])
                            <button type="button" @click="tab = '{{ $key }}'"
                                    class="-mb-px border-b-2 py-3 text-sm font-semibold transition"
                                    :class="tab === '{{ $key }}' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]'">
                                {{ $label }} @if ($count)<span class="text-xs">({{ $count }})</span>@endif
                            </button>
                        @endforeach
                    </div>

                    {{-- Subtasks --}}
                    <div x-show="tab === 'subtasks'" x-cloak class="p-6">
                        @if ($task->subtasks->isEmpty())
                            <p class="py-8 text-center text-sm text-gray-400">No subtasks yet.</p>
                        @else
                            <ul class="divide-y divide-gray-50">
                                @foreach ($task->subtasks as $sub)
                                    <li class="flex items-center gap-3 py-2.5">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $sub->statusColor() }}"></span>
                                        <a href="{{ route('admin.tasks.show', $sub) }}" class="min-w-0 flex-1 truncate text-sm font-medium text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $sub->title }}</a>
                                        <span class="shrink-0 text-xs text-[var(--color-muted)]">{{ $statusOptions[$sub->status] ?? $sub->status }}</span>
                                        @include('admin.projects._avatars', ['users' => $sub->assignee ? [$sub->assignee] : [], 'max' => 1, 'size' => 6])
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Comments --}}
                    <div x-show="tab === 'comments'" class="p-6">
                        @if ($canComment)
                        <form method="POST" action="{{ route('admin.tasks.comments.store', $task) }}" data-turbo="false" class="mb-4">
                            @csrf
                            <textarea name="body" rows="3" required placeholder="Write a comment…"
                                      class="w-full rounded-xl border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"></textarea>
                            <button class="mt-2 rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Comment</button>
                        </form>
                        @endif
                        @if ($task->comments->isEmpty())
                            <p class="py-6 text-center text-sm text-gray-400">No comments yet.</p>
                        @else
                            <ul class="space-y-4">
                                @foreach ($task->comments as $comment)
                                    <li class="flex items-start gap-3">
                                        @if ($comment->user?->photo_url)
                                            <img src="{{ $comment->user->photo_url }}" class="h-8 w-8 shrink-0 rounded-full object-cover" alt="">
                                        @else
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gray-100 text-xs font-bold text-gray-500">{{ strtoupper(substr($comment->user?->name ?? '?', 0, 1)) }}</span>
                                        @endif
                                        <div class="min-w-0 flex-1 rounded-xl bg-gray-50 px-3 py-2">
                                            <p class="text-sm font-semibold text-[var(--color-heading)]">{{ $comment->user?->name ?? 'Someone' }}
                                                <span class="ml-1 text-xs font-normal text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                                            </p>
                                            <p class="mt-0.5 whitespace-pre-line text-sm text-[var(--color-muted)]">{{ $comment->body }}</p>
                                        </div>
                                        @if ($canComment && ($canEdit || $comment->user_id === $me->id))
                                            <form method="POST" action="{{ route('admin.tasks.comments.destroy', [$task, $comment]) }}" onsubmit="return confirm('Delete this comment?')">
                                                @csrf @method('DELETE')
                                                <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-300 hover:bg-red-50 hover:text-red-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    {{-- Activity --}}
                    <div x-show="tab === 'activity'" x-cloak class="p-6">
                        @if ($task->activities->isEmpty())
                            <p class="py-8 text-center text-sm text-gray-400">No activity yet.</p>
                        @else
                            <ul class="relative space-y-5">
                                <span class="pointer-events-none absolute w-px bg-gray-100" style="left:16px;top:16px;bottom:16px"></span>
                                @foreach ($task->activities as $a)
                                    @php
                                        $d = strtolower($a->description ?? '');
                                        // Each kind of event gets its own colour + glyph, like the design.
                                        [$tone, $glyph] = match (true) {
                                            str_contains($d, 'created') => ['bg-[var(--color-primary-soft)] text-[var(--color-primary)]', 'M12 8v8m-4-4h8M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z'],
                                            str_contains($d, 'moved') => ['bg-[var(--color-primary-soft)] text-[var(--color-primary)]', 'M20 12a8 8 0 1 1-2.3-5.7M20 4v4h-4'],
                                            str_contains($d, 'priority') => ['bg-red-50 text-red-500', 'M5 21V4m0 0h11l-1.5 3.5L16 11H5'],
                                            str_contains($d, 'timer') && str_contains($d, 'started') => ['bg-emerald-50 text-emerald-600', 'm7 4 12 8-12 8V4Z'],
                                            str_contains($d, 'resumed') => ['bg-emerald-50 text-emerald-600', 'm7 4 12 8-12 8V4Z'],
                                            str_contains($d, 'paused') => ['bg-amber-50 text-amber-500', 'M9 5v14M15 5v14'],
                                            str_contains($d, 'logged') => ['bg-emerald-50 text-emerald-600', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM12 7v5l3 2'],
                                            str_contains($d, 'file') || str_contains($d, 'attach') => ['bg-amber-50 text-amber-500', 'M21 12.5 12.8 20.7a5 5 0 0 1-7-7l8.2-8.3a3.3 3.3 0 1 1 4.7 4.7l-8.2 8.2a1.7 1.7 0 0 1-2.4-2.4l7.6-7.5'],
                                            str_contains($d, 'assignee') => ['bg-violet-50 text-violet-500', 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
                                            default => ['bg-gray-100 text-gray-400', 'M12 8v5l3 2M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z'],
                                        };
                                        $isTimer = str_contains($d, 'timer') || str_contains($d, 'logged');
                                    @endphp
                                    <li class="relative flex items-start gap-3">
                                        <span class="z-10 grid h-8 w-8 shrink-0 place-items-center rounded-full ring-4 ring-white {{ $tone }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $glyph }}"/></svg>
                                        </span>
                                        @if ($a->user?->photo_url)
                                            <img src="{{ $a->user->photo_url }}" class="h-9 w-9 shrink-0 rounded-full object-cover" alt="">
                                        @else
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gray-100 text-xs font-bold text-gray-500">{{ strtoupper(substr($a->user?->name ?? '?', 0, 1)) }}</span>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm text-[var(--color-muted)]">
                                                <span class="font-semibold text-[var(--color-heading)]">{{ $a->user?->name ?? 'System' }}</span>
                                                {{ $a->description }}
                                                @if ($isTimer)
                                                    <span class="ml-1 rounded bg-[var(--color-primary-soft)] px-1.5 py-0.5 text-[11px] font-semibold text-[var(--color-primary)]">Time Tracking</span>
                                                @endif
                                            </p>
                                            <p class="mt-0.5 text-xs text-gray-400">{{ $a->created_at->format('d M, Y') }} at {{ $a->created_at->format('h:i A') }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                </div>
            </div>

            {{-- ===================== Sidebar ===================== --}}
            <div class="space-y-4">
                @if ($task->project?->time_tracking && $canTime)
                    @php
                        $est = (int) $task->estimated_minutes;
                        $pct = $est ? min(100, (int) round($loggedMinutes / $est * 100)) : null;
                        $remaining = $est ? max(0, $est - $loggedMinutes) : null;
                        $hm = fn ($m) => sprintf('%02dh %02dm', intdiv((int) $m, 60), (int) $m % 60);
                    @endphp
                    <div class="rounded-2xl border border-gray-100 bg-white px-5 py-4 shadow-sm" x-data="{ noteOpen: false }">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-base font-bold text-[var(--color-heading)]">Time Tracking</h3>
                            <a href="{{ route('admin.projects.show', $task->project_id) }}?tab=time" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">View Logs</a>
                        </div>

                        {{-- Timer + the four totals, kept compact --}}
                        <div class="mt-3 flex flex-wrap items-start gap-x-6 gap-y-3">
                            <div class="min-w-0" style="flex:1 1 130px">
                                @if ($timer)
                                    <p class="flex items-center gap-1.5 text-[11px] font-semibold {{ $timer->isRunning() ? 'text-red-500' : 'text-amber-500' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $timer->isRunning() ? 'bg-red-500' : 'bg-amber-500' }}"></span>
                                        {{ $timer->isRunning() ? 'Timer Running' : 'Paused' }}
                                    </p>
                                    <p class="text-xl font-bold text-[var(--color-primary)]"
                                       x-data="ticker(@js($timer->elapsedSeconds()), {{ $timer->isRunning() ? 'true' : 'false' }})" x-text="clock" x-init="run()">{{ $timer->clock() }}</p>
                                    @if ($timer->started_at)
                                        <p class="text-[11px] text-[var(--color-muted)]">Started {{ $timer->started_at->format('h:i A') }}</p>
                                    @endif
                                @else
                                    <p class="text-[11px] font-semibold text-[var(--color-muted)]">No timer running</p>
                                    <p class="text-xl font-bold text-gray-300">00:00:00</p>
                                @endif
                            </div>

                            <div class="grid gap-x-5 gap-y-2" style="flex:2 1 200px; grid-template-columns: repeat(2, minmax(0, 1fr))">
                                @foreach ([
                                    ['Today', $hm($todayMinutes), ''],
                                    ['Total', $hm($loggedMinutes), ''],
                                    ['Estimated', $est ? $hm($est) : '—', ''],
                                    ['Remaining', $remaining === null ? '—' : $hm($remaining), $remaining === 0 ? 'text-red-600' : ''],
                                ] as [$label, $value, $tone])
                                    <div>
                                        <p class="text-[11px] text-[var(--color-muted)]">{{ $label }}</p>
                                        <p class="text-sm font-bold {{ $tone ?: 'text-[var(--color-heading)]' }}">{{ $value }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @if ($pct !== null)
                            <div class="mt-3 flex items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-1.5 rounded-full {{ $pct >= 100 ? 'bg-red-500' : 'bg-[var(--color-primary)]' }}" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="shrink-0 text-[11px] font-semibold text-[var(--color-muted)]">{{ $pct }}%</span>
                            </div>
                        @endif

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            @if ($timer && $timer->isRunning())
                                <form method="POST" action="{{ route('admin.tasks.timer.pause', $task) }}" data-turbo="false">
                                    @csrf
                                    <button class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-[var(--color-primary)] py-2 text-xs font-semibold text-[var(--color-primary)] transition hover:bg-indigo-50">
                                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><rect x="7" y="5" width="3.5" height="14" rx="1"/><rect x="13.5" y="5" width="3.5" height="14" rx="1"/></svg> Pause
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.tasks.timer.start', $task) }}" data-turbo="false">
                                    @csrf
                                    <button class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-gray-200 py-2 text-xs font-semibold text-[var(--color-heading)] transition hover:bg-gray-50">
                                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="m7 4 12 8-12 8V4Z"/></svg> {{ $timer ? 'Resume' : 'Start' }}
                                    </button>
                                </form>
                            @endif

                            {{-- Stop, with an optional note --}}
                            <form method="POST" action="{{ route('admin.tasks.timer.stop', $task) }}" data-turbo="false">
                                @csrf
                                <input type="hidden" name="note" :value="noteOpen ? $refs.note?.value : ''">
                                <button @disabled(! $timer)
                                        class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-red-200 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:opacity-60">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><rect x="5" y="5" width="14" height="14" rx="2"/></svg> Stop
                                </button>
                            </form>
                        </div>

                        @if ($timer)
                            <button type="button" @click="noteOpen = !noteOpen"
                                    class="mt-2 text-[11px] font-semibold text-[var(--color-primary)] hover:underline"
                                    x-text="noteOpen ? 'Hide note' : 'Add a note (optional)'">Add a note (optional)</button>
                            <input x-show="noteOpen" x-cloak x-ref="note" type="text" maxlength="255"
                                   placeholder="What did you work on?"
                                   class="mt-1.5 h-9 w-full rounded-lg border-gray-200 text-xs placeholder:text-gray-400 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">

                            <form method="POST" action="{{ route('admin.tasks.timer.cancel', $task) }}" data-turbo="false" onsubmit="return confirm('Discard this timer without logging the time?')">
                                @csrf
                                <button class="mt-2 w-full text-[11px] text-gray-400 hover:text-red-500">Discard without logging</button>
                            </form>
                        @endif
                    </div>
                @endif

                <div class="rounded-2xl border border-gray-100 bg-white px-6 py-5 shadow-sm">
                    <h3 class="text-base font-bold text-[var(--color-heading)]">Task Information</h3>
                    @php
                        $ic = [
                            'project' => 'M4 5h16v14H4zM4 10h16',
                            'column' => 'M4 5h6v14H4zM14 5h6v14h-6z',
                            'user' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
                            'flag' => 'M5 21V4m0 0h11l-1.5 3.5L16 11H5',
                            'tag' => 'M3 12V5h7l9 9-7 7-9-9ZM7 8h.01',
                            'calendar' => 'M4 6h16v14H4zM4 10h16M8 3v3M16 3v3',
                            'clock' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM12 7v5l3 2',
                            'tree' => 'M12 4v6m0 0H7v4m5-4h5v4M5 14h4v4H5zm10 0h4v4h-4z',
                            'check' => 'm9 12 2 2 4-4M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z',
                        ];
                    @endphp
                    <dl class="mt-2 divide-y divide-gray-50 text-sm">
                        @php
                            $rows = [
                                ['project', 'Project', $task->project?->name],
                                ['column', 'Task Board Column', null],
                                ['user', 'Assignee', null],
                                ['flag', 'Priority', null],
                                ['tag', 'Labels', null],
                                ['calendar', 'Due Date', null],
                                ['clock', 'Estimated Time', $task->estimateLabel() ?? '—'],
                                ['clock', 'Logged Time', \App\Models\ProjectTimeLog::humanMinutes($loggedMinutes)],
                                ['calendar', 'Start Date', $task->start_date?->format('d M, Y') ?? '—'],
                                ['flag', 'Milestone', $task->milestone?->title ?? '—'],
                                ['tree', 'Parent Task', $task->parent?->title ?? '—'],
                                ['check', 'Task Type', $task->parent_id ? 'Subtask' : 'Task'],
                                ['calendar', 'Created At', $task->created_at->format('d M, Y h:i A')],
                                ['clock', 'Last Updated', $task->updated_at->format('d M, Y h:i A')],
                            ];
                        @endphp
                        @foreach ($rows as [$icon, $label, $value])
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="flex shrink-0 items-center gap-2.5 text-[var(--color-muted)]">
                                    <svg class="h-4 w-4 shrink-0 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ic[$icon] }}"/></svg>
                                    {{ $label }}
                                </dt>
                                <dd class="min-w-0 text-right font-medium text-[var(--color-heading)]">
                                    @switch($label)
                                        @case('Task Board Column')
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="h-2 w-2 rounded-full" style="background: {{ $task->statusColor() }}"></span>
                                                {{ $statusOptions[$task->status] ?? $task->status }}
                                            </span>
                                            @break
                                        @case('Assignee')
                                            @if ($task->assignee)
                                                <span class="inline-flex items-center gap-1.5">
                                                    @if ($task->assignee->photo_url)
                                                        <img src="{{ $task->assignee->photo_url }}" class="h-5 w-5 rounded-full object-cover" alt="">
                                                    @else
                                                        <span class="grid h-5 w-5 place-items-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-500">{{ strtoupper(substr($task->assignee->name, 0, 1)) }}</span>
                                                    @endif
                                                    <span class="truncate">{{ $task->assignee->name }}</span>
                                                    @if ($task->assignee->job_title)
                                                        <span class="rounded bg-[var(--color-primary-soft)] px-1.5 py-0.5 text-[11px] font-semibold text-[var(--color-primary)]">{{ $task->assignee->job_title }}</span>
                                                    @endif
                                                </span>
                                            @else
                                                <span class="text-[var(--color-muted)]">Unassigned</span>
                                            @endif
                                            @break
                                        @case('Priority')
                                            <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-semibold {{ $priorityBadge[$task->priority] ?? 'bg-gray-100 text-gray-500' }}">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 21V4m0 0h11l-1.5 3.5L16 11H5"/></svg>
                                                {{ ucfirst($task->priority) }}
                                            </span>
                                            @break
                                        @case('Labels')
                                            @forelse ((array) $task->labels as $l)
                                                <span class="ml-2 inline-block rounded px-2 py-0.5 text-[11px] font-semibold bg-[var(--color-primary-soft)] text-[var(--color-primary)]">{{ $l }}</span>
                                            @empty
                                                <span class="text-[var(--color-muted)]">—</span>
                                            @endforelse
                                            @break
                                        @case('Due Date')
                                            {{ $task->due_date?->format('d M, Y') ?? '—' }}
                                            @if ($task->isOverdue())
                                                <span class="ml-2 rounded px-2 py-0.5 text-[11px] font-bold bg-red-50 text-red-600">Overdue</span>
                                            @endif
                                            @break
                                        @default
                                            <span class="truncate">{{ $value }}</span>
                                    @endswitch
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

            </div>
        </div>

        <script>
            function ticker(startSeconds, running) {
                return {
                    clock: '00:00:00',
                    run() {
                        const base = startSeconds;
                        const from = Date.now();
                        const paint = () => {
                            const s = running ? base + Math.floor((Date.now() - from) / 1000) : base;
                            const p = n => String(n).padStart(2, '0');
                            this.clock = p(Math.floor(s / 3600)) + ':' + p(Math.floor(s / 60) % 60) + ':' + p(s % 60);
                        };
                        paint();
                        if (running) setInterval(paint, 1000);
                    },
                };
            }
        </script>

        {{-- Edit modal — same shape as the Add New Task modal --}}
        @if ($canEdit)
            <div x-show="editOpen" x-cloak @keydown.escape.window="editOpen = false"
                 x-data="editTaskForm(@js((array) $task->labels), {{ $me->id }}, @js($task->title))">
                <div x-show="editOpen" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="editOpen = false"></div>
                <div x-show="editOpen" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 py-10" @click.self="editOpen = false">
                    <div class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                        {{-- Header --}}
                        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-bold text-[var(--color-heading)]">Edit Task</h3>
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ $task->code() }}</span>
                                    </div>
                                    <p class="text-xs text-[var(--color-muted)]">Update the details of this task</p>
                                </div>
                            </div>
                            <button type="button" @click="editOpen = false" class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                            </button>
                        </div>

                        @if ($errors->any() && ! $errors->has('timer'))
                            <div class="mt-4 mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                <ul class="list-inside list-disc space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.tasks.update', $task) }}" data-turbo="false" @submit="submitting = true">
                            @csrf @method('PUT')

                            <div class="grid gap-0 lg:grid-cols-3">
                                {{-- Main --}}
                                <div class="space-y-5 p-6 lg:col-span-2">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Task Title <span class="text-red-500">*</span></label>
                                        <input type="text" name="title" required maxlength="120" x-model="title"
                                               class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                        <p class="mt-1 text-right text-xs text-[var(--color-muted)]"><span x-text="title.length"></span> / 120</p>
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Description</label>
                                        <x-admin.rich-editor name="description" :value="$task->description" placeholder="Describe the task in detail..." :min-height="150" />
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Project</label>
                                            <div class="flex h-11 items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm font-medium text-[var(--color-heading)]">
                                                <span class="truncate">{{ $task->project?->name }}</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Task Board Column <span class="text-red-500">*</span></label>
                                            <select name="status" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                                @foreach ($statusOptions as $k => $v)<option value="{{ $k }}" @selected(old('status', $task->status) === $k)>{{ $v }}</option>@endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Priority</label>
                                            <select name="priority" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                                @foreach (\App\Models\ProjectTask::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(old('priority', $task->priority) === $k)>{{ $v }}</option>@endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Labels</label>
                                            <div class="flex flex-wrap items-center gap-1.5 rounded-lg border border-gray-200 px-2 py-1.5 focus-within:border-[var(--color-primary)]" style="min-height:44px">
                                                <template x-for="(label, i) in labels" :key="i">
                                                    <span class="inline-flex items-center gap-1 rounded bg-[var(--color-primary-soft)] px-2 py-1 text-xs font-semibold text-[var(--color-primary)]">
                                                        <span x-text="label"></span>
                                                        <button type="button" @click="labels.splice(i, 1)" class="opacity-60 hover:opacity-80">&times;</button>
                                                    </span>
                                                </template>
                                                <input type="text" x-model="labelDraft" @keydown.enter.prevent="addLabel()"
                                                       @keydown.backspace="if (!labelDraft && labels.length) labels.pop()"
                                                       placeholder="Type and press Enter"
                                                       class="min-w-0 flex-1 border-0 p-0 text-sm placeholder:text-gray-400 focus:ring-0" style="background:transparent">
                                            </div>
                                            {{-- the server reads this comma-separated value --}}
                                            <input type="hidden" name="labels_csv" :value="labels.join(', ')">
                                        </div>
                                    </div>
                                </div>

                                {{-- Sidebar --}}
                                <div class="task-modal-side space-y-5 border-gray-100 bg-gray-50 p-6">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Assignee</label>
                                        <select name="assigned_to" x-model="assignee"
                                                class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                            <option value="">Unassigned</option>
                                            @foreach ($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                        </select>
                                        <button type="button" @click="assignee = '{{ $me->id }}'" class="mt-1.5 text-xs font-semibold text-[var(--color-primary)] hover:underline">Assign to me</button>
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Due Date</label>
                                        <input type="date" name="due_date" value="{{ old('due_date', $task->due_date?->toDateString()) }}"
                                               class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Estimated Time <span class="font-normal text-[var(--color-muted)]">(Optional)</span></label>
                                        <input type="text" name="estimate" maxlength="40" placeholder="e.g. 4h, 2d, 30m"
                                               value="{{ old('estimate', $task->estimateLabel()) }}"
                                               class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                        <p class="mt-1 text-xs text-[var(--color-muted)]">A day counts as 8h, a week as 5 days.</p>
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Start Date <span class="font-normal text-[var(--color-muted)]">(Optional)</span></label>
                                        <input type="date" name="start_date" value="{{ old('start_date', $task->start_date?->toDateString()) }}"
                                               class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Milestone <span class="font-normal text-[var(--color-muted)]">(Optional)</span></label>
                                        <select name="milestone_id" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                            <option value="">No milestone</option>
                                            @foreach ($milestones as $m)<option value="{{ $m->id }}" @selected(old('milestone_id', $task->milestone_id) == $m->id)>{{ $m->title }}</option>@endforeach
                                        </select>
                                    </div>

                                    @if ($task->parent)
                                        <div class="flex gap-2 rounded-xl bg-white p-3 text-xs text-[var(--color-heading)]">
                                            <svg class="h-4 w-4 shrink-0 text-[var(--color-primary)]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v5m0-8h.01"/></svg>
                                            Subtask of <span class="font-semibold">{{ $task->parent->title }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Footer --}}
                            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
                                <button type="button" @click="editOpen = false" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] transition hover:bg-gray-50">Cancel</button>
                                <button :disabled="submitting"
                                        class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] disabled:opacity-60">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                    <span x-text="submitting ? 'Saving...' : 'Save Changes'">Save Changes</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <style>
                /* Sidebar divider: above on mobile, beside on desktop. */
                .task-modal-side { border-top-width: 1px; }
                @media (min-width: 1024px) { .task-modal-side { border-top-width: 0; border-left-width: 1px; } }
            </style>

            <script>
                function editTaskForm(labels, meId, title) {
                    return {
                        title: title || '',
                        labels: labels || [],
                        labelDraft: '',
                        assignee: @js((string) ($task->assigned_to ?? '')),
                        submitting: false,
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
