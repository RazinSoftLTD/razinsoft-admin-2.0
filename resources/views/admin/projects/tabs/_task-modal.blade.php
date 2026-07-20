{{-- Add Task modal (project pages). Needs an Alpine scope with `addOpen` (+ optional `addStatus`). --}}
@php
    $me = auth()->user();
    $firstStatusKey = array_key_first($project->statusOptions());
    // The id a new task will get — tasks are numbered "<project code>-<task id>".
    $nextTaskCode = ($project->code ?: 'TASK').'-'.((int) \App\Models\ProjectTask::withTrashed()->max('id') + 1);
    // Suggested labels: whatever is already in use on this project's tasks.
    $labelSuggestions = $tasks->pluck('labels')->filter()->flatten()->unique()->take(12)->values();
@endphp

<div x-show="addOpen" x-cloak @keydown.escape.window="addOpen = false"
     x-data="taskForm({{ $me->id }})"
     @reopen-add-task.window="addOpen = true">

    <div x-show="addOpen" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="addOpen = false"></div>

    <div x-show="addOpen" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 py-10" @click.self="addOpen = false">
        <div class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">

            {{-- Header --}}
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1ZM8 6H6a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-2M12 11v6M9 14h6"/></svg>
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-bold text-[var(--color-heading)]">Add New Task</h3>
                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500" title="Assigned automatically when the task is created">{{ $nextTaskCode }}</span>
                        </div>
                        <p class="text-xs text-[var(--color-muted)]">Create a new task and add details</p>
                    </div>
                </div>
                <button type="button" @click="addOpen = false" class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.tasks.store') }}" enctype="multipart/form-data" data-turbo="false" @submit="submitting = true">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project->id }}">

                <div class="grid gap-0 lg:grid-cols-3">
                    {{-- ============ Main column ============ --}}
                    <div class="space-y-5 p-6 lg:col-span-2">
                        {{-- Title --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Task Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" required maxlength="120" x-model="title"
                                   placeholder="Enter a clear and concise task title"
                                   class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                            <p class="mt-1 text-right text-xs text-[var(--color-muted)]"><span x-text="title.length">0</span> / 120</p>
                        </div>

                        {{-- Description --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Description</label>
                            <x-admin.rich-editor name="description" placeholder="Describe the task in detail..." :min-height="150" />
                            <p class="mt-1 text-xs text-[var(--color-muted)]">Bold, italics, lists and links are supported.</p>
                        </div>

                        {{-- Project + column --}}
                        <div class="grid gap-4 sm:grid-cols-2">
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
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Task Board Column <span class="text-red-500">*</span></label>
                                <select name="status" @if (isset($fixedStatus) && $fixedStatus === 'alpine') x-model="addStatus" @endif
                                        class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                    @foreach ($project->statusOptions() as $k => $v)
                                        <option value="{{ $k }}" @selected(! isset($fixedStatus) && $k === $firstStatusKey)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Priority + labels --}}
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Priority</label>
                                <select name="priority" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                    @foreach (\App\Models\ProjectTask::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($k === 'medium')>{{ $v }}</option>@endforeach
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
                                @if ($labelSuggestions->isNotEmpty())
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        @foreach ($labelSuggestions as $sug)
                                            <button type="button" @click="addLabel(@js($sug))" class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-500 transition hover:bg-gray-200">{{ $sug }}</button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Attachments --}}
                        <div class="rounded-xl border border-gray-200 p-4">
                            <p class="flex items-center gap-2 text-sm font-semibold text-[var(--color-heading)]">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.5 12.8 20.7a5 5 0 0 1-7-7l8.2-8.3a3.3 3.3 0 1 1 4.7 4.7l-8.2 8.2a1.7 1.7 0 0 1-2.4-2.4l7.6-7.5"/></svg>
                                Attachments <span class="font-normal text-[var(--color-muted)]">(Optional)</span>
                            </p>
                            <label class="mt-3 flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed py-8 text-center transition"
                                   :class="over ? 'border-[var(--color-primary)] bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'"
                                   @dragover.prevent="over = true" @dragleave="over = false"
                                   @drop.prevent="over = false; $refs.files.files = $event.dataTransfer.files; readFiles()">
                                <svg class="h-7 w-7 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                                <span class="mt-2 text-sm text-[var(--color-muted)]">Drag &amp; drop files here or <span class="font-semibold text-[var(--color-primary)]">browse</span></span>
                                <span class="mt-0.5 text-xs text-gray-400">Supports: JPG, PNG, PDF, DOC, ZIP (Max. 20MB)</span>
                                <input type="file" name="attachments[]" multiple x-ref="files" class="sr-only" @change="readFiles()">
                            </label>
                            <ul x-show="fileNames.length" x-cloak class="mt-2 space-y-1">
                                <template x-for="(f, i) in fileNames" :key="i">
                                    <li class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-1.5 text-xs text-[var(--color-heading)]">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7zM14 3v5h5"/></svg>
                                        <span class="truncate" x-text="f"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    {{-- ============ Sidebar ============ --}}
                    <div class="task-modal-side space-y-5 border-gray-100 bg-gray-50 p-6">
                        {{-- Assignee --}}
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
                            <input type="date" name="due_date" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Estimated Time <span class="font-normal text-[var(--color-muted)]">(Optional)</span></label>
                            <input type="text" name="estimate" maxlength="40" placeholder="e.g. 4h, 2d, 30m"
                                   class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                            <p class="mt-1 text-xs text-[var(--color-muted)]">A day counts as 8h, a week as 5 days.</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Start Date <span class="font-normal text-[var(--color-muted)]">(Optional)</span></label>
                            <input type="date" name="start_date" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Milestone <span class="font-normal text-[var(--color-muted)]">(Optional)</span></label>
                            <select name="milestone_id" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                <option value="">No milestone</option>
                                @foreach ($project->milestones as $m)<option value="{{ $m->id }}">{{ $m->title }}</option>@endforeach
                            </select>
                        </div>

                        {{-- Task type --}}
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

                        {{-- Parent task — only meaningful for a subtask --}}
                        <div x-show="type === 'subtask'" x-cloak>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Parent Task <span class="text-red-500">*</span></label>
                            <select name="parent_id" :required="type === 'subtask'" :disabled="type !== 'subtask'"
                                    class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                <option value="">Select parent task</option>
                                @foreach ($tasks as $t)<option value="{{ $t->id }}">{{ Str::limit($t->title, 60) }}</option>@endforeach
                            </select>
                        </div>

                        <div x-show="type === 'task'" x-cloak class="flex gap-2 rounded-xl bg-[var(--color-primary-soft)] p-3 text-xs text-[var(--color-heading)]">
                            <svg class="h-4 w-4 shrink-0 text-[var(--color-primary)]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v5m0-8h.01"/></svg>
                            You can break down larger tasks into subtasks after creating this task.
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-6 py-4">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-[var(--color-heading)]">
                        <input type="checkbox" name="create_another" value="1" class="h-4 w-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                        Create Another
                    </label>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="addOpen = false" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] transition hover:bg-gray-50">Cancel</button>
                        <button :disabled="submitting"
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
    /* Sidebar divider: above on mobile, beside on desktop (these Tailwind variants aren't in the build). */
    .task-modal-side { border-top-width: 1px; }
    @media (min-width: 1024px) { .task-modal-side { border-top-width: 0; border-left-width: 1px; } }
</style>
<script>
    function taskForm(meId) {
        return {
            title: '',
            labels: [],
            labelDraft: '',
            assignee: '',
            type: 'task',
            fileNames: [],
            over: false,
            submitting: false,

            addLabel(value) {
                const v = (value ?? this.labelDraft).trim().replace(/,$/, '');
                if (v && !this.labels.includes(v) && this.labels.length < 12) this.labels.push(v);
                this.labelDraft = '';
            },
            readFiles() {
                this.fileNames = [...(this.$refs.files?.files || [])].map(f => f.name);
            },
        };
    }
</script>

@if (session('task_created_again'))
    {{-- "Create Another" was ticked — reopen the modal once Alpine is ready. --}}
    <script>
        document.addEventListener('alpine:initialized', () => window.dispatchEvent(new CustomEvent('reopen-add-task')));
    </script>
@endif
