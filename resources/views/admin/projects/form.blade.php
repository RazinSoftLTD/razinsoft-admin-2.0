@extends('admin.layouts.app')
@section('title', $project->exists ? 'Edit Project' : 'Add New Project')

@php
    $val = fn ($k, $default = null) => old($k, $project->$k ?? $default);
    $memberIds = collect(old('member_ids', $project->exists ? $project->members->pluck('user_id')->all() : []))->map(fn ($v) => (int) $v)->all();
    $dateVal = fn ($k) => $val($k) instanceof \Carbon\CarbonInterface ? $val($k)->toDateString() : $val($k);
    $noDeadline = old('no_deadline', $project->exists ? ($project->deadline ? false : true) : true);
    $catOptions = ['' => '--'] + array_combine($categories, $categories);
    $clientsJson = $clients->map(fn ($c) => ['id' => $c->id, 'label' => $c->name.($c->company ? ' — '.$c->company : '')])->values();
    $selectedClientId = (int) $val('client_id');
@endphp

@section('content')
    <form method="POST" action="{{ $project->exists ? route('admin.projects.update', $project) : route('admin.projects.store') }}" enctype="multipart/form-data"
          x-data="{
              auto: {{ $val('auto_progress', true) ? 'true' : 'false' }},
              progress: {{ (int) $val('progress', 0) }},
              members: {{ Illuminate\Support\Js::from($memberIds) }},
              noDeadline: {{ $noDeadline ? 'true' : 'false' }},
          }">
        @csrf
        @if ($project->exists) @method('PUT') @endif

        {{-- ── Sticky action bar ── --}}
        <div class="sticky top-16 z-10 -mx-4 mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-[var(--color-body)]/95 px-4 py-3 backdrop-blur sm:-mt-4 sm:px-6">
            <div class="flex items-center gap-3">
                <a href="{{ $project->exists ? route('admin.projects.show', $project) : route('admin.projects.index') }}" class="grid h-9 w-9 place-items-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50" title="Back">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 12H5m6 6-6-6 6-6"/></svg>
                </a>
                <div>
                    <h1 class="text-base font-bold text-[var(--color-heading)] sm:text-lg">{{ $project->exists ? 'Edit Project' : 'Add New Project' }}</h1>
                    <p class="text-xs text-[var(--color-muted)]">Workspace › Projects › {{ $project->exists ? $project->code : 'New' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $project->exists ? route('admin.projects.show', $project) : route('admin.projects.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                    {{ $project->exists ? 'Save Changes' : 'Save Project' }}
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <p class="font-semibold">Please fix the following:</p>
                <ul class="mt-1 list-inside list-disc space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- ── Main grid: content + sidebar ── --}}
        <div class="grid gap-6 xl:grid-cols-3">

            {{-- Left column --}}
            <div class="space-y-6 xl:col-span-2">

                {{-- Project Details --}}
                <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-4">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg></span>
                        <div>
                            <h2 class="text-sm font-bold text-[var(--color-heading)]">Project Details</h2>
                            <p class="text-xs text-[var(--color-muted)]">Name, schedule and who it's for.</p>
                        </div>
                    </div>
                    <div class="grid gap-5 p-6 sm:grid-cols-2 lg:grid-cols-6">
                        <div class="lg:col-span-2"><x-admin.field label="Short Code" name="code" :value="$project->code" placeholder="Auto — or set your own" hint="Leave blank to auto-generate." /></div>
                        <div class="sm:col-span-2 lg:col-span-4"><x-admin.field label="Project Name" name="name" :value="$project->name" required placeholder="Write a project name" /></div>

                        <div class="lg:col-span-3"><x-admin.field label="Start Date" name="start_date" type="date" :value="$dateVal('start_date')" /></div>
                        <div class="lg:col-span-3">
                            <label class="mb-1.5 flex items-center text-sm font-medium text-[var(--color-heading)]">
                                <span :class="noDeadline && 'text-gray-300 line-through'">Deadline</span>
                                <label class="ml-auto inline-flex cursor-pointer items-center gap-1.5 text-xs font-normal text-[var(--color-muted)]">
                                    <input type="checkbox" name="no_deadline" value="1" x-model="noDeadline" class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"> No deadline
                                </label>
                            </label>
                            <input type="date" name="deadline" value="{{ $dateVal('deadline') }}" x-bind:disabled="noDeadline" x-bind:class="noDeadline && 'bg-gray-50 text-gray-300'" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                        </div>

                        <div class="lg:col-span-2"><x-admin.field label="Project Category" name="category" type="select" :value="$project->category" :options="$catOptions" /></div>
                        <div class="lg:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Parent Project</label>
                            <select name="parent_id" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                <option value="">-- Top level --</option>
                                @foreach ($parents as $p)
                                    @continue($project->exists && $p->id === $project->id)
                                    <option value="{{ $p->id }}" @selected($val('parent_id') == $p->id)>{{ $p->name }} ({{ $p->code }})</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Optional — makes this a child project.</p>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Client</label>
                            <x-admin.searchable-select name="client_id" :options="$clientsJson" :selected="$selectedClientId ?: null" placeholder="Search client…" clear-label="No client" />
                        </div>

                        <div class="lg:col-span-3"><x-admin.field label="Project Summary" name="summary" type="textarea" rows="4" :value="$project->summary" placeholder="What is this project about?" /></div>
                        <div class="lg:col-span-3"><x-admin.field label="Notes" name="notes" type="textarea" rows="4" :value="$project->notes" placeholder="Internal notes only your team sees…" /></div>
                    </div>
                </section>

                {{-- Other Details — collapsible --}}
                <section x-data="{ show: {{ $project->exists ? 'true' : 'false' }} }" class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <button type="button" @click="show = !show" class="flex w-full items-center gap-3 px-6 py-4 text-left" :class="show ? 'border-b border-gray-100' : ''">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-amber-50 text-amber-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20Z"/></svg></span>
                        <div class="flex-1">
                            <h2 class="text-sm font-bold text-[var(--color-heading)]">Other Details</h2>
                            <p class="text-xs text-[var(--color-muted)]">Files, budget and hours. <span x-show="!show">Click to expand.</span></p>
                        </div>
                        <svg class="h-5 w-5 text-gray-400 transition" :class="show && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="show" x-cloak class="space-y-5 p-6">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Add File</label>
                            <input type="file" name="files[]" multiple class="w-full rounded-lg border border-dashed border-gray-200 p-4 text-sm text-[var(--color-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                            <p class="mt-1 text-xs text-gray-400">Attach one or more files (max 20 MB each).</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-3">
                            <x-admin.field label="Currency" name="currency" type="select" :value="$val('currency', 'USD')" :options="array_combine($currencies, $currencies)" />
                            <x-admin.field label="Project Budget" name="budget" type="number" step="0.01" min="0" :value="$project->budget" placeholder="e.g. 10000" />
                            <x-admin.field label="Hours Estimate (In Hours)" name="hours_allocated" type="number" min="0" :value="$project->hours_allocated" placeholder="e.g. 500" />
                        </div>
                    </div>
                </section>
            </div>

            {{-- Right sidebar --}}
            <div class="space-y-6">

                {{-- Status & Team --}}
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Status &amp; Team</h2>
                    </div>
                    <div class="space-y-5 p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <x-admin.field label="Status" name="status" type="select" :value="$val('status', 'todo')" :options="\App\Models\Project::STATUSES" />
                            <x-admin.field label="Priority" name="priority" type="select" :value="$val('priority', 'medium')" :options="\App\Models\Project::PRIORITIES" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Project Manager</label>
                            <select name="project_manager_id" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                <option value="">Not set</option>
                                @foreach ($staff as $s)<option value="{{ $s->id }}" @selected($val('project_manager_id') == $s->id)>{{ $s->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Project Members</label>
                            <div class="flex flex-wrap gap-2 rounded-lg border border-gray-200 p-3">
                                @forelse ($staff as $s)
                                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                           :class="members.includes({{ $s->id }}) ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)] text-[var(--color-primary)]' : 'border-gray-200 text-[var(--color-muted)] hover:bg-gray-50'">
                                        <input type="checkbox" name="member_ids[]" value="{{ $s->id }}" class="hidden" :checked="members.includes({{ $s->id }})"
                                               @change="members.includes({{ $s->id }}) ? members = members.filter(id => id !== {{ $s->id }}) : members.push({{ $s->id }})">
                                        {{ $s->name }}
                                    </label>
                                @empty
                                    <span class="text-sm text-gray-300">No staff available.</span>
                                @endforelse
                            </div>
                            <p class="mt-1 text-xs text-gray-400"><span x-text="members.length"></span> selected.</p>
                        </div>
                    </div>
                </section>

                {{-- Privacy — only visible to roles with the "Make Private" permission --}}
                @if (auth()->user()->allows('projects', 'private'))
                    <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h2 class="text-sm font-bold text-[var(--color-heading)]">Privacy</h2>
                        </div>
                        <div class="p-6">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="checkbox" name="is_private" value="1" @checked($val('is_private', false))
                                       class="mt-0.5 h-4 w-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                <span>
                                    <span class="block text-sm font-semibold text-[var(--color-heading)]">Make this project private</span>
                                    <span class="mt-0.5 block text-xs text-[var(--color-muted)]">Only the super admin, you, and the Project Members selected above will be able to see it.</span>
                                </span>
                            </label>
                            @if ($project->exists && $project->is_private && $project->made_private_by)
                                <p class="mt-2 text-xs text-gray-400">Made private by <strong class="text-[var(--color-heading)]">{{ $project->madePrivateBy?->name ?? '—' }}</strong>.</p>
                            @endif
                        </div>
                    </section>
                @endif

                {{-- Progress --}}
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Progress</h2>
                    </div>
                    <div class="space-y-4 p-6">
                        <div class="flex flex-wrap items-center gap-5 text-sm">
                            <label class="inline-flex items-center gap-2"><input type="radio" name="auto_progress" value="1" @checked($val('auto_progress', true)) @click="auto = true" class="accent-[var(--color-primary)]"> Auto</label>
                            <label class="inline-flex items-center gap-2"><input type="radio" name="auto_progress" value="0" @checked(! $val('auto_progress', true)) @click="auto = false" class="accent-[var(--color-primary)]"> Manual</label>
                        </div>
                        <p class="text-xs text-gray-400" x-show="auto">Calculated automatically from completed tasks.</p>
                        <div x-show="!auto" x-cloak>
                            <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Manual Progress — <span class="font-bold text-[var(--color-primary)]" x-text="progress + '%'"></span></label>
                            <input type="range" name="progress" min="0" max="100" x-model="progress" class="w-full accent-[var(--color-primary)]">
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
