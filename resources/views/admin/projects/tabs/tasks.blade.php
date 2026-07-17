@php
    $statusOptions = $project->statusOptions();
    $colColor = $project->columns->pluck('color', 'key')->all();
    $priorityBadge = ['low' => 'bg-gray-100 text-gray-500', 'medium' => 'bg-amber-50 text-amber-600', 'high' => 'bg-orange-50 text-orange-600', 'urgent' => 'bg-red-50 text-red-600'];
@endphp

<div x-data="{ addOpen: false }">
    @if ($canEdit)
        <div class="mb-4 flex justify-end">
            <button type="button" @click="addOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Task
            </button>
        </div>
    @endif

    @if ($tasks->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-400">No tasks yet.</p>
            @if ($canEdit)<button type="button" @click="addOpen = true" class="mt-2 text-sm font-semibold text-[var(--color-primary)] hover:underline">Add the first task</button>@endif
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full min-w-[850px] text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/70 text-left text-[11px] uppercase tracking-wide text-gray-400">
                        <th class="px-4 py-3 font-semibold">Code</th>
                        <th class="px-4 py-3 font-semibold">Task</th>
                        <th class="px-4 py-3 font-semibold">Milestone</th>
                        <th class="px-4 py-3 font-semibold">Due Date</th>
                        <th class="px-4 py-3 font-semibold">Estimate</th>
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
                                @if ($task->subtasks->count())
                                    <p class="mt-0.5 text-[11px] text-gray-400">{{ $task->subtasks->where('status', 'completed')->count() }}/{{ $task->subtasks->count() }} subtasks</p>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-xs text-[var(--color-muted)]">{{ $task->milestone?->title ?? '—' }}</td>
                            <td class="px-4 py-3.5 whitespace-nowrap {{ $task->isOverdue() ? 'font-semibold text-red-500' : 'text-[var(--color-muted)]' }}">{{ $task->due_date?->format('d M, Y') ?? '—' }}</td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-[var(--color-muted)]">{{ $task->estimateLabel() ?? '—' }}</td>
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
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('admin.tasks.status', $task) }}" data-turbo="false">
                                        @csrf
                                        <div class="relative inline-flex items-center">
                                            <span class="pointer-events-none absolute left-2.5 h-2 w-2 rounded-full" style="background: {{ $colColor[$task->status] ?? '#94a3b8' }};"></span>
                                            <select name="status" onchange="this.form.submit()" class="h-8 rounded-lg border-gray-200 pl-6 pr-7 text-xs font-medium text-[var(--color-heading)]">
                                                @foreach ($statusOptions as $k => $v)<option value="{{ $k }}" @selected($task->status === $k)>{{ $v }}</option>@endforeach
                                            </select>
                                        </div>
                                    </form>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-heading)]"><span class="h-2 w-2 rounded-full" style="background: {{ $colColor[$task->status] ?? '#94a3b8' }};"></span>{{ $statusOptions[$task->status] ?? $task->status }}</span>
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
    @endif

    @if ($canEdit)
        @include('admin.projects.tabs._task-modal', ['fixedStatus' => null])
    @endif
</div>
