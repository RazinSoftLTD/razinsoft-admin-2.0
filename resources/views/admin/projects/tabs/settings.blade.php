@php use App\Models\ProjectMember; @endphp

<div class="grid items-start gap-6 lg:grid-cols-2">
    {{-- ===== Member Access ===== --}}
    <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Member Access</h2>
            <p class="text-xs text-[var(--color-muted)]">Set what each member can do in <strong>this</strong> project. Add or remove members from the <a href="{{ route('admin.projects.show', $project) }}?tab=members" class="font-semibold text-[var(--color-primary)] hover:underline">Members</a> tab.</p>
        </div>
        <div class="p-6">
            @if ($project->members->isEmpty())
                <p class="rounded-lg border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-400">No members yet.</p>
            @else
                <ul class="divide-y divide-gray-50">
                    @foreach ($project->members as $m)
                        <li class="flex items-center justify-between gap-3 py-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                @if ($m->user?->photo_url)<img src="{{ $m->user->photo_url }}" class="h-8 w-8 rounded-full object-cover" alt="">@else<span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]">{{ strtoupper(substr($m->user?->name ?? '?', 0, 1)) }}</span>@endif
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-[var(--color-heading)]">{{ $m->user?->name ?? '—' }}</p>
                                    @if ($m->user?->job_title)<p class="truncate text-[11px] text-gray-400">{{ $m->user->job_title }}</p>@endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('admin.projects.members.access', [$project, $m]) }}">
                                @csrf @method('PUT')
                                <select name="access_level" onchange="this.form.submit()" class="h-9 rounded-lg border-gray-200 text-xs font-medium focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                    @foreach (ProjectMember::ACCESS_LEVELS as $k => $v)<option value="{{ $k }}" @selected(($m->access_level ?? 'manage') === $k)>{{ $v }}</option>@endforeach
                                </select>
                            </form>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-[11px] text-gray-400"><strong>View only</strong> = read the project · <strong>Manage tasks</strong> = work on tasks/board · <strong>Full manage</strong> = everything.</p>
            @endif
        </div>
    </section>

    {{-- ===== Board Columns ===== --}}
    <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Task Board Columns</h2>
            <p class="text-xs text-[var(--color-muted)]">This project's own board columns.</p>
        </div>
        <div class="p-6">
            <form method="POST" action="{{ route('admin.projects.columns.store', $project) }}" class="mb-4 flex flex-wrap items-center gap-2">
                @csrf
                <input type="text" name="name" required placeholder="Column name" class="h-10 flex-1 rounded-lg border-gray-200 text-sm">
                <input type="color" name="color" value="#3b82f6" class="h-10 w-12 cursor-pointer rounded-lg border-gray-200 p-1">
                <label class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_done" value="1" class="rounded accent-[var(--color-primary)]"> Done</label>
                <label class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_review" value="1" class="rounded accent-amber-500"> Review</label>
                <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
            </form>
            <ul class="space-y-2">
                @foreach ($project->columns as $col)
                    <li class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2.5" x-data="{ edit: false }">
                        <template x-if="!edit">
                            <span class="flex flex-1 items-center gap-2">
                                <span class="h-3 w-3 rounded-full" style="background: {{ $col->color }}"></span>
                                <span class="flex-1 text-sm font-medium text-[var(--color-heading)]">{{ $col->name }}</span>
                                @if ($col->is_done)<span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-600">DONE</span>@endif
                                @if ($col->is_review)<span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-600">REVIEW</span>@endif
                                @if ($col->is_excluded)<span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-500">EXCLUDED</span>@endif
                                <button type="button" @click="edit = true" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg></button>
                                @if ($project->columns->count() > 1)
                                    <form method="POST" action="{{ route('admin.projects.columns.destroy', [$project, $col]) }}" onsubmit="return confirm('Remove this column? Its tasks move to another column.')">@csrf @method('DELETE')
                                        <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                                    </form>
                                @endif
                            </span>
                        </template>
                        <form x-show="edit" x-cloak method="POST" action="{{ route('admin.projects.columns.update', [$project, $col]) }}" class="flex flex-1 flex-wrap items-center gap-2">
                            @csrf @method('PUT')
                            <input type="color" name="color" value="{{ $col->color }}" class="h-9 w-11 cursor-pointer rounded-lg border-gray-200 p-1">
                            <input type="text" name="name" value="{{ $col->name }}" required class="h-9 flex-1 rounded-lg border-gray-200 text-sm">
                            <label class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_done" value="1" @checked($col->is_done) class="rounded accent-[var(--color-primary)]"> Done</label>
                            <label class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_review" value="1" @checked($col->is_review) class="rounded accent-amber-500"> Review</label>
                            <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                            <button type="button" @click="edit = false" class="text-xs text-gray-400">Cancel</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ===== PRD sections ===== --}}
    @php $picked = $project->prdSectionKeys(); @endphp
    <section class="rounded-xl border border-gray-100 bg-white shadow-sm lg:col-span-2"
             x-data="{
                 on: {{ $project->needs_requirements ? 'true' : 'false' }},
                 total: {{ count(\App\Models\Project::PRD_SECTIONS) }},
                 picked: {{ count($picked) }},
                 get allOn() { return this.picked === this.total },
                 toggleAll(checked) {
                     this.$refs.list.querySelectorAll('input[name=\'prd_sections[]\']').forEach(c => c.checked = checked);
                     this.picked = checked ? this.total : 0;
                 },
                 recount() {
                     this.picked = this.$refs.list.querySelectorAll('input[name=\'prd_sections[]\']:checked').length;
                 },
             }">
        <form method="POST" action="{{ route('admin.projects.settings.update', $project) }}">
            @csrf @method('PUT')

            {{-- Header + Save --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <div>
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">PRD / Requirements</h2>
                    <p class="text-xs text-[var(--color-muted)]">Tick what this project needs — only ticked items show on the PRD tab for upload.</p>
                </div>
                <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
            </div>

            <div class="p-6">
                <label class="flex cursor-pointer items-start justify-between gap-4 rounded-lg border border-gray-200 p-4">
                    <span>
                        <span class="block text-sm font-semibold text-[var(--color-heading)]">Collect a PRD for this project</span>
                        <span class="mt-0.5 block text-xs text-[var(--color-muted)]">Turn off to hide the PRD tab content entirely.</span>
                    </span>
                    <span class="relative mt-0.5 inline-flex shrink-0">
                        <input type="checkbox" name="needs_requirements" value="1" x-model="on" class="peer sr-only">
                        <span class="h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-[var(--color-primary)]"></span>
                        <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition" :class="on ? 'translate-x-5' : ''"></span>
                    </span>
                </label>

                <div x-show="on" x-cloak>
                    {{-- Select all --}}
                    <label class="mt-4 flex cursor-pointer items-center justify-between gap-3 rounded-lg bg-gray-50 px-4 py-3">
                        <span class="flex items-center gap-3">
                            <input type="checkbox" :checked="allOn" @change="toggleAll($event.target.checked)"
                                   class="h-4 w-4 shrink-0 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            <span class="text-sm font-semibold text-[var(--color-heading)]">Select all</span>
                        </span>
                        <span class="text-xs text-[var(--color-muted)]"><span x-text="picked"></span> of <span x-text="total"></span> selected</span>
                    </label>

                    <div class="mt-2 space-y-2" x-ref="list" @change="recount()">
                        @foreach (\App\Models\Project::PRD_SECTIONS as $key => [$label, $hint, $required, $icon, $tint])
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3 transition hover:border-gray-300 hover:bg-gray-50">
                                <input type="checkbox" name="prd_sections[]" value="{{ $key }}" @checked(in_array($key, $picked, true))
                                       class="h-4 w-4 shrink-0 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $tint }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-[var(--color-heading)]">{{ $label }}</span>
                                        <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $required ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500' }}">{{ $required ? 'Required' : 'Optional' }}</span>
                                    </span>
                                    <span class="mt-0.5 block text-xs text-[var(--color-muted)]">{{ $hint }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </form>
    </section>
</div>
