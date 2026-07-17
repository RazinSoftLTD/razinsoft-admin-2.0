@php
    $priDot = ['low' => 'bg-gray-300', 'medium' => 'bg-amber-400', 'high' => 'bg-orange-500', 'urgent' => 'bg-red-500'];
    $byStatus = $tasks->groupBy('status');
    $columns = $project->columns;
    $firstKey = $columns->first()?->key ?? 'todo';
@endphp

<div x-data="{ addOpen: false, addStatus: '{{ $firstKey }}', colsOpen: false }" @open-columns.window="colsOpen = true">
    {{-- Kanban — columns scroll vertically inside, so the horizontal scrollbar sits at the bottom --}}
    <div x-data="taskBoard()" class="flex gap-4 overflow-x-auto pb-2">
        @foreach ($columns as $col)
            @php $items = $byStatus->get($col->key, collect()); @endphp
            <div class="w-72 shrink-0" data-col="{{ $col->key }}">
                {{-- Colored header --}}
                <div class="mb-2 flex items-center justify-between rounded-lg px-3 py-2.5" style="background: {{ $col->color }}1a;">
                    <span class="flex items-center gap-2 text-sm font-bold" style="color: {{ $col->color }};">
                        <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $col->color }};"></span>{{ $col->name }}
                        <span data-count class="rounded-full bg-white/70 px-2 py-0.5 text-xs font-bold">{{ $items->count() }}</span>
                    </span>
                    @if ($col->is_done)<span class="text-[10px] font-bold uppercase" style="color: {{ $col->color }};">Done</span>@endif
                </div>
                <div data-dropzone="{{ $col->key }}" @dragover.prevent @dragenter.prevent="$el.classList.add('ring-2')" @dragleave="$el.classList.remove('ring-2')" @drop="drop($event, '{{ $col->key }}')"
                     class="max-h-[calc(100vh-22rem)] min-h-[9rem] space-y-2 overflow-y-auto rounded-xl p-2 transition ring-[var(--color-primary)]" style="background: {{ $col->color }}0d;">
                    @foreach ($items as $task)
                        <div draggable="{{ $canEdit ? 'true' : 'false' }}" @if ($canEdit) @dragstart="dragStart($event, {{ $task->id }})" @dragend="dragEnd($event)" @endif
                             data-task="{{ $task->id }}"
                             class="rounded-xl border border-gray-100 bg-white p-3 shadow-sm transition hover:shadow {{ $canEdit ? 'cursor-grab active:cursor-grabbing' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('admin.tasks.show', $task) }}" class="text-sm font-semibold leading-snug text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $task->title }}</a>
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $priDot[$task->priority] ?? 'bg-gray-300' }}" title="{{ ucfirst($task->priority) }}"></span>
                            </div>
                            <p class="mt-1 text-[10px] font-semibold text-gray-300">{{ $task->code() }}</p>
                            @if ($task->milestone)<span class="mt-1.5 inline-block rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500">{{ $task->milestone->title }}</span>@endif
                            <div class="mt-2.5 flex items-center justify-between text-[11px] text-gray-400">
                                <span class="inline-flex items-center gap-1 {{ $task->isOverdue() ? 'font-semibold text-red-500' : '' }}">
                                    @if ($task->due_date)
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M3 9h18M8 3v4M16 3v4"/></svg>
                                        {{ $task->due_date->format('d M, Y') }}
                                    @endif
                                </span>
                                <div class="flex items-center gap-1.5">
                                    @if ($task->subtasks->count())<span>{{ $task->subtasks->where('status', 'completed')->count() }}/{{ $task->subtasks->count() }}</span>@endif
                                    @include('admin.projects._avatars', ['users' => $task->assignee ? [$task->assignee] : [], 'max' => 1, 'size' => 5])
                                </div>
                            </div>
                        </div>
                    @endforeach
                    <div data-empty class="{{ $items->count() ? 'hidden' : '' }} rounded-lg border border-dashed border-gray-200 py-6 text-center text-xs text-gray-300">Drop tasks here</div>
                    @if ($canEdit)
                        <button type="button" @click="addStatus = '{{ $col->key }}'; addOpen = true"
                                class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-gray-200 py-2 text-xs font-semibold text-gray-400 transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Task
                        </button>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Add column shortcut --}}
        @if ($canEdit)
            <div class="w-64 shrink-0">
                <button type="button" @click="colsOpen = true" class="flex h-11 w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-gray-200 text-sm font-semibold text-gray-400 hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Column
                </button>
            </div>
        @endif
    </div>

    @if ($canEdit)
        @include('admin.projects.tabs._task-modal', ['fixedStatus' => 'alpine'])

        {{-- Manage Columns modal --}}
        <div x-show="colsOpen" x-cloak @keydown.escape.window="colsOpen = false">
            <div x-show="colsOpen" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="colsOpen = false"></div>
            <div x-show="colsOpen" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-16" @click.self="colsOpen = false">
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="text-base font-bold text-[var(--color-heading)]">Manage Board Columns</h3>
                        <button type="button" @click="colsOpen = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                    </div>
                    <div class="space-y-2 px-5 py-4">
                        @foreach ($columns as $col)
                            <div class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2.5" x-data="{ edit: false }">
                                <template x-if="!edit">
                                    <span class="flex flex-1 items-center gap-2">
                                        <span class="h-3 w-3 rounded-full" style="background: {{ $col->color }}"></span>
                                        <span class="flex-1 text-sm font-medium text-[var(--color-heading)]">{{ $col->name }}</span>
                                        @if ($col->is_done)<span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-600">DONE</span>@endif
                                        <button type="button" @click="edit = true" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg></button>
                                        @if ($columns->count() > 1)
                                            <form method="POST" action="{{ route('admin.projects.columns.destroy', [$project, $col]) }}" onsubmit="return confirm('Remove this column? Its tasks move to another column.')">@csrf @method('DELETE')
                                                <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                                            </form>
                                        @endif
                                    </span>
                                </template>
                                <form x-show="edit" x-cloak method="POST" action="{{ route('admin.projects.columns.update', [$project, $col]) }}" class="flex flex-1 flex-wrap items-center gap-2">
                                    @csrf @method('PUT')
                                    <input type="text" name="name" value="{{ $col->name }}" required class="h-9 flex-1 rounded-lg border-gray-200 text-sm">
                                    <input type="color" name="color" value="{{ $col->color }}" class="h-9 w-11 cursor-pointer rounded-lg border-gray-200 p-1">
                                    <label class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_done" value="1" @checked($col->is_done) class="rounded accent-[var(--color-primary)]"> Done</label>
                                    <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                                    <button type="button" @click="edit = false" class="text-xs text-gray-400">Cancel</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                    <div class="border-t border-gray-100 px-5 py-4">
                        <form method="POST" action="{{ route('admin.projects.columns.store', $project) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="text" name="name" required placeholder="New column name" class="h-10 flex-1 rounded-lg border-gray-200 text-sm">
                            <input type="color" name="color" value="#6366f1" class="h-10 w-12 cursor-pointer rounded-lg border-gray-200 p-1">
                            <label class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_done" value="1" class="rounded accent-[var(--color-primary)]"> Done</label>
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add Column</button>
                        </form>
                    </div>
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
            dragStart(e, id) { this.dragId = id; e.dataTransfer.effectAllowed = 'move'; e.target.classList.add('opacity-40'); },
            dragEnd(e) { e.target.classList.remove('opacity-40'); },
            drop(e, status) {
                e.currentTarget.classList.remove('ring-2');
                const card = document.querySelector('[data-task="' + this.dragId + '"]');
                const zone = e.currentTarget;
                if (!card || zone.contains(card)) return;
                zone.insertBefore(card, zone.querySelector('[data-empty]'));
                this.recount();
                fetch(@js(url('admin/tasks')) + '/' + this.dragId, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-HTTP-Method-Override': 'PUT' },
                    body: JSON.stringify({ status }),
                }).then(r => { if (!r.ok) location.reload(); }).catch(() => location.reload());
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
