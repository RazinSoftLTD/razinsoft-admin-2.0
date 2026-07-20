@php use App\Models\ProjectMember; @endphp

<div class="grid items-start gap-6 lg:grid-cols-2">
    {{-- ===== Project details ===== --}}
    <section class="rounded-2xl border border-gray-100 bg-white shadow-sm lg:col-span-2"
             x-data="{ preview: @js($project->avatarUrl()), remove: false }">
        <form method="POST" action="{{ route('admin.projects.profile.update', $project) }}" enctype="multipart/form-data">
            @csrf
            <div class="flex flex-wrap items-start gap-6 p-6">
                {{-- Avatar with camera badge --}}
                <div class="flex shrink-0 flex-col items-center gap-2">
                    <label class="group relative cursor-pointer">
                        <span class="grid shrink-0 place-items-center overflow-hidden rounded-2xl border border-gray-100 bg-[var(--color-primary-soft)] text-2xl font-bold text-[var(--color-primary)]" style="height:150px;width:150px">
                            <template x-if="preview"><img :src="preview" class="h-full w-full object-cover" alt=""></template>
                            <template x-if="!preview"><span>{{ $project->initials() }}</span></template>
                        </span>
                        <span class="absolute bottom-2 right-2 grid h-9 w-9 place-items-center rounded-full bg-white text-gray-500 shadow-lg transition group-hover:text-[var(--color-primary)]">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8h3l2-2h6l2 2h3v11H4zM12 16a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>
                        </span>
                        <input type="file" name="avatar" accept="image/*" class="sr-only"
                               @change="remove = false; const f = $event.target.files[0]; if (f) preview = URL.createObjectURL(f)">
                    </label>
                    <label class="cursor-pointer text-sm font-semibold text-[var(--color-primary)] hover:underline">
                        Change photo
                        <input type="file" name="avatar" accept="image/*" class="sr-only"
                               @change="remove = false; const f = $event.target.files[0]; if (f) preview = URL.createObjectURL(f)">
                    </label>
                    <button type="button" x-show="preview" x-cloak @click="preview = null; remove = true"
                            class="inline-flex items-center gap-1 text-sm font-semibold text-red-500 hover:underline">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5h6v2m2 0v13H7V7"/></svg>
                        Remove
                    </button>
                    <input type="hidden" name="remove_avatar" :value="remove ? 1 : 0">
                </div>

                {{-- Fields --}}
                <div class="min-w-0 flex-1 divide-y divide-gray-100" style="min-width:280px">
                    <div class="pb-4">
                        <span class="flex items-center gap-2 text-sm text-[var(--color-muted)]">
                            <svg class="h-4 w-4 text-[var(--color-primary)]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM8 5v14"/></svg>
                            Project name
                        </span>
                        <input type="text" name="name" maxlength="160" required value="{{ old('name', $project->name) }}"
                               class="mt-1 w-full border-0 p-0 text-xl font-bold text-[var(--color-heading)] focus:ring-0">
                        <p class="mt-1 text-xs text-[var(--color-muted)]">This is the project name visible to all members.</p>
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="pt-5">
                        <span class="flex items-center gap-2 text-sm text-[var(--color-muted)]">
                            <svg class="h-4 w-4 text-[var(--color-primary)]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 6h14M12 6v13"/></svg>
                            Subtitle
                        </span>
                        <input type="text" name="subtitle" maxlength="160" value="{{ old('subtitle', $project->subtitle) }}"
                               placeholder="Add a short subtitle"
                               class="mt-1 w-full border-0 p-0 text-xl font-bold text-[var(--color-heading)] placeholder:font-normal placeholder:text-gray-300 focus:ring-0">
                        <p class="mt-1 text-xs text-[var(--color-muted)]">A short description or subtitle for this project.</p>
                        @error('subtitle')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
                <a href="{{ route('admin.projects.edit', $project) }}"
                   class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] transition hover:bg-gray-50">Edit all fields</a>
                <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save changes</button>
            </div>
        </form>
    </section>

    {{-- ===== Member Access ===== --}}
    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="flex items-start gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
            </span>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-[var(--color-heading)]">Member Access</h2>
                <p class="text-xs text-[var(--color-muted)]">Manage who can access and what they can do in this project.</p>
            </div>
        </div>

        <div class="mt-4 space-y-2">
            @forelse ($project->members as $m)
                @php
                    $isOwner = $m->user_id === $project->project_manager_id || $m->user_id === $project->created_by;
                    $isClient = $m->user?->role === \App\Models\User::ROLE_CUSTOMER;
                    $tag = $isOwner ? ['Owner', 'bg-indigo-50 text-indigo-600'] : ($isClient ? ['Client', 'bg-emerald-50 text-emerald-600'] : ['Team', 'bg-gray-100 text-gray-500']);
                @endphp
                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-3">
                    @if ($m->user?->photo_url)
                        <img src="{{ $m->user->photo_url }}" class="h-9 w-9 shrink-0 rounded-full object-cover" alt="">
                    @else
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]">{{ strtoupper(substr($m->user?->name ?? '?', 0, 1)) }}</span>
                    @endif

                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        <span class="truncate text-sm font-semibold text-[var(--color-heading)]">{{ $m->user?->name ?? '—' }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $tag[1] }}">{{ $tag[0] }}</span>
                    </div>

                    <form method="POST" action="{{ route('admin.projects.members.access', [$project, $m]) }}" class="shrink-0">
                        @csrf @method('PUT')
                        <select name="access_level" onchange="this.form.submit()" class="h-9 rounded-lg border-gray-200 text-xs font-medium focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            @foreach (ProjectMember::ACCESS_LEVELS as $k => $v)<option value="{{ $k }}" @selected(($m->access_level ?? 'manage') === $k)>{{ $v }}</option>@endforeach
                        </select>
                    </form>

                    <div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute right-0 z-30 mt-1.5 w-44 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg">
                            <form method="POST" action="{{ route('admin.projects.members.destroy', [$project, $m]) }}" onsubmit="return confirm('Remove {{ $m->user?->name }} from this project?')">
                                @csrf @method('DELETE')
                                <button class="block w-full px-3.5 py-2 text-left text-xs font-medium text-red-600 hover:bg-red-50">Remove from project</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <p class="rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-400">No members yet.</p>
            @endforelse

            <a href="{{ route('admin.projects.show', $project) }}?tab=members"
               class="flex items-center justify-center gap-2 rounded-xl border border-dashed border-gray-200 py-3 text-sm font-semibold text-[var(--color-muted)] transition hover:border-[var(--color-primary)] hover:bg-gray-50 hover:text-[var(--color-primary)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                Add New Member
            </a>
        </div>

        <p class="mt-4 text-[11px] text-gray-400"><strong>View only</strong> = read the project · <strong>Manage tasks</strong> = work on tasks/board · <strong>Full manage</strong> = everything</p>
    </section>

    {{-- ===== Board Columns ===== --}}
    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm" x-data="{ adding: false }">
        <div class="flex items-start gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM4 10h16"/></svg>
            </span>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-[var(--color-heading)]">Task Board Columns</h2>
                <p class="text-xs text-[var(--color-muted)]">Customize the columns used in this project's task board.</p>
            </div>
        </div>

        {{-- Add column --}}
        <form method="POST" action="{{ route('admin.projects.columns.store', $project) }}" class="mt-4">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="inline-flex items-center gap-1.5 text-sm text-[var(--color-muted)]" title="The colour used for this column on the board">
                    Column color
                    <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v5m0-8h.01"/></svg>
                </span>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_done" value="1" class="rounded border-gray-300 accent-[var(--color-primary)]"> Done</label>
                    <label class="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_review" value="1" class="rounded border-gray-300 accent-amber-500"> Review</label>
                    <button type="button" @click="adding = !adding" x-show="!adding"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Add Column
                    </button>
                </div>
            </div>
            <div x-show="adding" x-cloak class="mt-3 flex flex-wrap items-center gap-2">
                <input type="color" name="color" value="#3b82f6" class="h-10 w-12 cursor-pointer rounded-lg border-gray-200 p-1">
                <input type="text" name="name" placeholder="Column name" class="h-10 min-w-0 flex-1 rounded-lg border-gray-200 text-sm">
                <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                <button type="button" @click="adding = false" class="px-2 text-sm text-gray-400 hover:text-[var(--color-heading)]">Cancel</button>
            </div>
        </form>

        <ul class="mt-4 space-y-2">
            @foreach ($project->columns as $col)
                <li class="flex items-center gap-3 rounded-xl border border-gray-100 px-3 py-2.5" x-data="{ edit: false }">
                    <template x-if="!edit">
                        <span class="flex flex-1 items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 cursor-move text-gray-300" fill="currentColor" viewBox="0 0 24 24"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg>
                            <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $col->color }}"></span>
                            <span class="flex-1 truncate text-sm font-semibold text-[var(--color-heading)]">{{ $col->name }}</span>
                            @if ($col->is_done)<span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-600">DONE</span>@endif
                            @if ($col->is_review)<span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-600">REVIEW</span>@endif
                            @if ($col->is_excluded)<span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-500">EXCLUDED</span>@endif
                            <button type="button" @click="edit = true" title="Edit" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg></button>
                            @if ($project->columns->count() > 1)
                                <form method="POST" action="{{ route('admin.projects.columns.destroy', [$project, $col]) }}" onsubmit="return confirm('Remove this column? Its tasks move to another column.')">@csrf @method('DELETE')
                                    <button title="Remove" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                                </form>
                            @endif
                        </span>
                    </template>
                    <form x-show="edit" x-cloak method="POST" action="{{ route('admin.projects.columns.update', [$project, $col]) }}" class="flex flex-1 flex-wrap items-center gap-2">
                        @csrf @method('PUT')
                        <input type="color" name="color" value="{{ $col->color }}" class="h-9 w-11 cursor-pointer rounded-lg border-gray-200 p-1">
                        <input type="text" name="name" value="{{ $col->name }}" required class="h-9 min-w-0 flex-1 rounded-lg border-gray-200 text-sm">
                        <label class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_done" value="1" @checked($col->is_done) class="rounded accent-[var(--color-primary)]"> Done</label>
                        <label class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_review" value="1" @checked($col->is_review) class="rounded accent-amber-500"> Review</label>
                        <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                        <button type="button" @click="edit = false" class="text-xs text-gray-400">Cancel</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ===== Time tracking ===== --}}
    <section class="rounded-2xl border border-gray-100 bg-white shadow-sm lg:col-span-2"
             x-data="{ on: {{ $project->time_tracking ? 'true' : 'false' }} }">
        <form method="POST" action="{{ route('admin.projects.settings.update', $project) }}">
            @csrf @method('PUT')
            {{-- keep the PRD choices intact when saving from this card --}}
            <input type="hidden" name="needs_requirements" value="{{ $project->needs_requirements ? 1 : 0 }}">
            @foreach ($project->prdSectionKeys() as $k)
                <input type="hidden" name="prd_sections[]" value="{{ $k }}">
            @endforeach

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <div class="flex items-start gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-[var(--color-heading)]">Time Tracking</h2>
                        <p class="text-xs text-[var(--color-muted)]">Log hours on tasks and see the time history for this project.</p>
                    </div>
                </div>
                <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
            </div>

            <div class="p-6">
                <label class="flex cursor-pointer items-start justify-between gap-4 rounded-lg border border-gray-200 p-4">
                    <span>
                        <span class="block text-sm font-semibold text-[var(--color-heading)]">Enable time tracking</span>
                        <span class="mt-0.5 block text-xs text-[var(--color-muted)]">Members can log time on each task; totals show per task and for the whole project.</span>
                    </span>
                    <span class="relative mt-0.5 inline-flex shrink-0">
                        <input type="checkbox" name="time_tracking" value="1" x-model="on" class="peer sr-only">
                        <span class="h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-[var(--color-primary)]"></span>
                        <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition" :class="on ? 'translate-x-5' : ''"></span>
                    </span>
                </label>

                @if ($project->time_tracking)
                    @php $logged = $project->totalMinutes(); @endphp
                    <div class="mt-4 flex flex-wrap items-center gap-6 rounded-lg bg-gray-50 px-4 py-3 text-sm">
                        <span class="text-[var(--color-muted)]">Logged so far: <strong class="text-[var(--color-heading)]">{{ \App\Models\ProjectTimeLog::humanMinutes($logged) }}</strong></span>
                        @if ($project->hours_allocated)
                            <span class="text-[var(--color-muted)]">Estimate: <strong class="text-[var(--color-heading)]">{{ $project->hours_allocated }}h</strong></span>
                        @endif
                    </div>
                @endif
            </div>
        </form>
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
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3 transition hover:border-gray-200 hover:bg-gray-50">
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

    {{-- ===== Danger zone ===== --}}
    @if (auth()->user()->allows('projects', 'delete'))
        <section class="rounded-xl border border-red-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-red-200 px-6 py-4">
                <h2 class="text-sm font-bold text-red-600">Danger Zone</h2>
                <p class="text-xs text-[var(--color-muted)]">Irreversible actions for this project.</p>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4 p-6">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-[var(--color-heading)]">Delete this project</p>
                    <p class="mt-0.5 text-xs text-[var(--color-muted)]">
                        It moves to the super admin's <strong>Trash</strong> with all its tasks, files and milestones, and is
                        permanently removed after 30 days. A super admin can restore it before then.
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.projects.destroy', $project) }}"
                      onsubmit="return confirm('Move “{{ $project->name }}” to Trash? A super admin can restore it within 30 days.')">
                    @csrf @method('DELETE')
                    <button class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7"/></svg>
                        Delete project
                    </button>
                </form>
            </div>
        </section>
    @endif
</div>
