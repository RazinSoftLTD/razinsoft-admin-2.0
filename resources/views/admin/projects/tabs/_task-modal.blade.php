{{-- Add Task modal (project pages). Needs an Alpine scope with `addOpen` (+ optional `addStatus`). --}}
<div x-show="addOpen" x-cloak @keydown.escape.window="addOpen = false">
    <div x-show="addOpen" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="addOpen = false"></div>
    <div x-show="addOpen" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-16" @click.self="addOpen = false">
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h3 class="text-base font-bold text-[var(--color-heading)]">Add Task</h3>
                <button type="button" @click="addOpen = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
            </div>
            <form method="POST" action="{{ route('admin.tasks.store') }}" data-turbo="false" class="space-y-4 px-5 py-4">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project->id }}">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required placeholder="What needs to be done?" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Description</label>
                    <textarea name="description" rows="3" placeholder="Optional details…" class="w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]"></textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Milestone</label>
                        <x-admin.searchable-select name="milestone_id" :options="$project->milestones->map(fn ($m) => ['id' => $m->id, 'label' => $m->title])" placeholder="Search milestone…" clear-label="No milestone" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Assigned To</label>
                        <x-admin.searchable-select name="assigned_to" :options="$staff->map(fn ($s) => ['id' => $s->id, 'label' => $s->name])" placeholder="Search staff…" clear-label="Unassigned" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Start Date</label>
                        <input type="date" name="start_date" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Due Date</label>
                        <input type="date" name="due_date" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Status</label>
                        @if (isset($fixedStatus) && $fixedStatus === 'alpine')
                            <select name="status" x-model="addStatus" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                @foreach ($project->statusOptions() as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                            </select>
                        @else
                            <select name="status" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                @foreach ($project->statusOptions() as $k => $v)<option value="{{ $k }}" @selected($loop->first)>{{ $v }}</option>@endforeach
                            </select>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Priority</label>
                        <select name="priority" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                            @foreach (\App\Models\ProjectTask::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($k === 'medium')>{{ $v }}</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Estimate</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="estimated_hours" min="0" placeholder="Hours" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                            <input type="number" name="estimated_extra_minutes" min="0" max="59" placeholder="Minutes" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="addOpen = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                    <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save Task</button>
                </div>
            </form>
        </div>
    </div>
</div>
