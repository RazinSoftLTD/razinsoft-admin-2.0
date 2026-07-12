@php
    $taskCol = ['todo' => 'bg-gray-400', 'in_progress' => 'bg-blue-500', 'blocked' => 'bg-red-500', 'review' => 'bg-purple-500', 'qa' => 'bg-amber-500', 'completed' => 'bg-emerald-500', 'cancelled' => 'bg-gray-300'];
    $priDot = ['low' => 'bg-gray-300', 'medium' => 'bg-amber-400', 'high' => 'bg-orange-500', 'critical' => 'bg-red-500'];
    $byStatus = $project->tasks->groupBy('status');
    $canEdit = $me->allows('projects', 'edit');
@endphp

<div x-data="{ addOpen: false }">
    @if ($canEdit)
        <div class="mb-4 flex justify-end">
            <button type="button" @click="addOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Task
            </button>
        </div>
    @endif

    {{-- Kanban --}}
    <div x-data="taskBoard()" class="flex gap-4 overflow-x-auto pb-4">
        @foreach (\App\Models\ProjectTask::STATUSES as $key => $label)
            @php $col = $byStatus->get($key, collect()); @endphp
            <div class="w-72 shrink-0" data-col="{{ $key }}">
                <div class="mb-2 flex items-center justify-between rounded-lg border border-gray-100 bg-white px-3 py-2">
                    <span class="flex items-center gap-2 text-sm font-bold text-[var(--color-heading)]">
                        <span class="h-2.5 w-2.5 rounded-full {{ $taskCol[$key] }}"></span>{{ $label }}
                        <span data-count class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ $col->count() }}</span>
                    </span>
                </div>
                <div data-dropzone="{{ $key }}" @dragover.prevent @dragenter.prevent="$el.classList.add('ring-2','ring-[var(--color-primary)]')" @dragleave="$el.classList.remove('ring-2','ring-[var(--color-primary)]')" @drop="drop($event, '{{ $key }}')"
                     class="min-h-[8rem] space-y-2 rounded-xl bg-gray-50/60 p-2 transition">
                    @foreach ($col as $task)
                        <div draggable="{{ $canEdit ? 'true' : 'false' }}" @if ($canEdit) @dragstart="dragStart($event, {{ $task->id }})" @dragend="dragEnd($event)" @endif
                             data-task="{{ $task->id }}" x-data="{ subs: false }"
                             class="rounded-xl border border-gray-100 bg-white p-3 shadow-sm hover:shadow {{ $canEdit ? 'cursor-grab active:cursor-grabbing' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-[var(--color-heading)]">{{ $task->title }}</p>
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $priDot[$task->priority] ?? 'bg-gray-300' }}" title="{{ ucfirst($task->priority) }}"></span>
                            </div>
                            @if ($task->workstream)<span class="mt-1.5 inline-block rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500">{{ $task->workstream->name }}</span>@endif
                            @if ($task->isOverdue())<span class="ml-1 text-[10px] font-semibold text-red-600">· Overdue</span>@endif

                            <div class="mt-2 flex items-center justify-between text-xs text-gray-400">
                                <button type="button" @click="subs = !subs" class="inline-flex items-center gap-1 hover:text-[var(--color-primary)]">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                                    {{ $task->subtasks->where('status', 'completed')->count() }}/{{ $task->subtasks->count() }} subtasks
                                </button>
                                <div class="flex items-center gap-1.5">
                                    @if ($task->due_date)<span>{{ $task->due_date->format('d M') }}</span>@endif
                                    @if ($task->assignee)<span class="grid h-5 w-5 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[9px] font-bold text-[var(--color-primary)]" title="{{ $task->assignee->name }}">{{ strtoupper(substr($task->assignee->name, 0, 1)) }}</span>@endif
                                    @if ($canEdit)
                                        <form method="POST" action="{{ route('admin.projects.tasks.destroy', [$project, $task]) }}" data-turbo="false" onsubmit="return confirm('Delete task?')">@csrf @method('DELETE')
                                            <button class="text-gray-300 hover:text-red-500" title="Delete"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            {{-- Subtasks --}}
                            <div x-show="subs" x-cloak class="mt-2 space-y-1 border-t border-gray-50 pt-2">
                                @foreach ($task->subtasks as $sub)
                                    <div class="flex items-center gap-2 text-xs">
                                        @if ($canEdit)
                                            <form method="POST" action="{{ route('admin.projects.tasks.update', [$project, $sub]) }}" data-turbo="false">@csrf @method('PUT')
                                                <input type="hidden" name="status" value="{{ $sub->status === 'completed' ? 'todo' : 'completed' }}">
                                                <button class="grid h-4 w-4 place-items-center rounded border {{ $sub->status === 'completed' ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-gray-300' }}">
                                                    @if ($sub->status === 'completed')<svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>@endif
                                                </button>
                                            </form>
                                        @endif
                                        <span class="flex-1 {{ $sub->status === 'completed' ? 'text-gray-400 line-through' : 'text-[var(--color-heading)]' }}">{{ $sub->title }}</span>
                                        @if ($canEdit)
                                            <form method="POST" action="{{ route('admin.projects.tasks.destroy', [$project, $sub]) }}" data-turbo="false">@csrf @method('DELETE')
                                                <button class="text-gray-300 hover:text-red-500"><svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('admin.projects.tasks.store', $project) }}" data-turbo="false" class="flex items-center gap-1 pt-1">
                                        @csrf
                                        <input type="hidden" name="parent_id" value="{{ $task->id }}">
                                        <input type="hidden" name="status" value="todo">
                                        <input type="hidden" name="priority" value="medium">
                                        @if ($task->workstream_id)<input type="hidden" name="workstream_id" value="{{ $task->workstream_id }}">@endif
                                        <input type="text" name="title" required placeholder="Add subtask…" class="h-7 flex-1 rounded border-gray-200 text-xs">
                                        <button class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-500 hover:bg-gray-200">+</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <div data-empty class="{{ $col->count() ? 'hidden' : '' }} rounded-lg border border-dashed border-gray-200 py-6 text-center text-xs text-gray-300">Drop tasks here</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Add Task modal --}}
    @if ($canEdit)
        <div x-show="addOpen" x-cloak @keydown.escape.window="addOpen = false">
            <div x-show="addOpen" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="addOpen = false"></div>
            <div x-show="addOpen" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-20" @click.self="addOpen = false">
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="text-base font-bold text-[var(--color-heading)]">Add Task</h3>
                        <button type="button" @click="addOpen = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                    </div>
                    <form method="POST" action="{{ route('admin.projects.tasks.store', $project) }}" data-turbo="false" class="space-y-4 px-5 py-4">
                        @csrf
                        <input type="text" name="title" required placeholder="Task title" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                        <textarea name="description" rows="2" placeholder="Description (optional)" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <select name="workstream_id" class="h-10 rounded-lg border-gray-200 text-sm">
                                <option value="">No workstream</option>
                                @foreach ($project->workstreams as $ws)<option value="{{ $ws->id }}">{{ $ws->name }}</option>@endforeach
                            </select>
                            <select name="assigned_to" class="h-10 rounded-lg border-gray-200 text-sm">
                                <option value="">Unassigned</option>
                                @foreach ($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                            </select>
                            <select name="status" class="h-10 rounded-lg border-gray-200 text-sm">
                                @foreach (\App\Models\ProjectTask::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                            </select>
                            <select name="priority" class="h-10 rounded-lg border-gray-200 text-sm">
                                @foreach (\App\Models\ProjectTask::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($k === 'medium')>{{ $v }}</option>@endforeach
                            </select>
                            <input type="date" name="due_date" class="h-10 rounded-lg border-gray-200 text-sm sm:col-span-2">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="addOpen = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add Task</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    function taskBoard() {
        return {
            dragId: null,
            csrf: document.querySelector('meta[name="csrf-token"]').content,
            base: @js(route('admin.projects.show', $project)),
            dragStart(e, id) { this.dragId = id; e.dataTransfer.effectAllowed = 'move'; e.target.classList.add('opacity-40'); },
            dragEnd(e) { e.target.classList.remove('opacity-40'); },
            drop(e, status) {
                e.currentTarget.classList.remove('ring-2', 'ring-[var(--color-primary)]');
                const card = document.querySelector('[data-task="' + this.dragId + '"]');
                const zone = e.currentTarget;
                if (!card || zone.contains(card)) return;
                zone.insertBefore(card, zone.querySelector('[data-empty]'));
                this.recount();
                fetch(@js(url('admin/projects/'.$project->id.'/tasks')) + '/' + this.dragId, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-HTTP-Method-Override': 'PUT' },
                    body: JSON.stringify({ status }),
                }).catch(() => location.reload());
            },
            recount() {
                document.querySelectorAll('[data-col]').forEach(col => {
                    const zone = col.querySelector('[data-dropzone]');
                    const cards = zone.querySelectorAll('[data-task]');
                    col.querySelector('[data-count]').textContent = cards.length;
                    const ph = zone.querySelector('[data-empty]');
                    if (ph) ph.classList.toggle('hidden', cards.length > 0);
                });
            },
        };
    }
</script>
