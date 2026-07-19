@extends('admin.layouts.app')
@section('title', 'Projects')

@php
    $me = auth()->user();
    $canReorder = $me->allows('projects', 'edit');
    $statusDot = ['todo' => 'bg-sky-500', 'in_progress' => 'bg-blue-500', 'on_hold' => 'bg-amber-500', 'completed' => 'bg-emerald-500', 'cancelled' => 'bg-gray-400'];
    $statusPill = ['todo' => 'bg-sky-50 text-sky-700', 'in_progress' => 'bg-blue-50 text-blue-700', 'on_hold' => 'bg-amber-50 text-amber-700', 'completed' => 'bg-emerald-50 text-emerald-700', 'cancelled' => 'bg-gray-100 text-gray-500'];
    $priorityBadge = ['low' => 'bg-gray-100 text-gray-500', 'medium' => 'bg-amber-50 text-amber-600', 'high' => 'bg-orange-50 text-orange-600', 'urgent' => 'bg-red-50 text-red-600'];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Projects</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Workspace &rsaquo; Projects</p>
        </div>
        @php $hasFilters = request()->hasAny(['search', 'status', 'priority', 'category', 'client', 'from', 'to', 'end_from', 'end_to', 'sort']); @endphp
        <div class="flex items-center gap-2">
            @if ($me->allows('projects', 'create'))
                <a href="{{ route('admin.projects.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Project
                </a>
            @endif
            <button type="button" @click="window.dispatchEvent(new CustomEvent('open-filters'))" title="Filters" class="relative grid h-11 w-11 place-items-center rounded-lg border border-gray-200 text-[var(--color-primary)] transition hover:bg-indigo-50">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16l-6.3 7.5V19l-3.4 2v-9.5L4 4Z"/></svg>
                @if ($hasFilters)<span class="absolute -right-1 -top-1 h-3 w-3 rounded-full border-2 border-white bg-[var(--color-primary)]"></span>@endif
            </button>
        </div>
    </div>

    {{-- Stats --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $curStatus = request('status', 'all') ?: 'all';
            $keep = request()->except(['status', 'page']);   // clicking a card keeps the other filters
        @endphp
        @foreach ([
            ['Total Projects', $stats['total'], 'all', 'text-[var(--color-heading)]', 'bg-gray-50', 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z'],
            ['In Progress', $stats['in_progress'], 'in_progress', 'text-blue-700', 'bg-blue-50', 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM10 9l5 3-5 3V9Z'],
            ['Overdue', $stats['overdue'], 'overdue', 'text-red-600', 'bg-red-50', 'M12 8v4l3 2M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z'],
            ['Completed', $stats['completed'], 'completed', 'text-emerald-700', 'bg-emerald-50', 'm9 12 2 2 4-4M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z'],
        ] as [$label, $value, $key, $tone, $chip, $icon])
            @php $active = $curStatus === $key; @endphp
            <a href="{{ route('admin.projects.index', array_merge($keep, ['status' => $key])) }}"
               title="Filter by {{ $label }}"
               class="flex items-center gap-3 rounded-xl border px-4 py-3 shadow-sm transition {{ $active ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)] ring-1 ring-[var(--color-primary)]' : 'border-gray-100 bg-white hover:border-gray-200 hover:shadow' }}">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full {{ $chip }} {{ $tone }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                </span>
                <span class="text-sm font-semibold text-[var(--color-heading)]">{{ $label }}</span>
                <span class="ml-auto text-2xl font-bold {{ $tone }}">{{ $value }}</span>
            </a>
        @endforeach
    </div>

    @if ($projects->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-400">No projects found.</p>
            @if ($me->allows('projects', 'create'))<a href="{{ route('admin.projects.create') }}" class="mt-2 inline-block text-sm font-semibold text-[var(--color-primary)] hover:underline">Create your first project</a>@endif
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full min-w-[950px] text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/70 text-left text-[11px] uppercase tracking-wide text-gray-400">
                        @if ($canReorder)<th class="w-8 px-2 py-3"></th>@endif
                        <th class="px-4 py-3 font-semibold">Code</th>
                        <th class="px-4 py-3 font-semibold">Project Name</th>
                        <th class="px-4 py-3 font-semibold">Members</th>
                        <th class="px-4 py-3 font-semibold">Start Date</th>
                        <th class="px-4 py-3 font-semibold">Deadline</th>
                        <th class="px-4 py-3 font-semibold">Client</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="w-40 px-4 py-3 font-semibold">Progress</th>
                        <th class="px-4 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody id="projects-tbody" class="divide-y divide-gray-50">
                    @foreach ($projects as $project)
                        @php $progress = $project->progressPercent(); @endphp
                        <tr class="transition hover:bg-gray-50/60" data-project-id="{{ $project->id }}">
                            @if ($canReorder)
                                <td class="px-2 py-3.5 align-middle">
                                    <span data-drag-handle title="Drag to reorder" class="grid h-7 w-6 cursor-grab place-items-center text-gray-300 hover:text-gray-500 active:cursor-grabbing">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="9" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                                    </span>
                                </td>
                            @endif
                            <td class="px-4 py-3.5 whitespace-nowrap text-xs font-semibold"><a href="{{ route('admin.projects.show', $project) }}" class="text-gray-400 hover:text-[var(--color-primary)] hover:underline">{{ $project->code }}</a></td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.projects.show', $project) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $project->name }}</a>
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $priorityBadge[$project->priority] ?? 'bg-gray-100 text-gray-500' }}">{{ $project->priority }}</span>
                                </div>
                                <p class="mt-0.5 text-[11px] text-gray-400">
                                    @if ($project->parent)<span class="text-[var(--color-primary)]">↳ {{ $project->parent->name }}</span> · @endif
                                    {{ $project->tasks_total }} task{{ $project->tasks_total === 1 ? '' : 's' }}@if ($project->children_count) · {{ $project->children_count }} child project{{ $project->children_count === 1 ? '' : 's' }}@endif
                                </p>
                            </td>
                            <td class="px-4 py-3.5">@include('admin.projects._avatars', ['users' => $project->members->map->user])</td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-[var(--color-muted)]">{{ $project->start_date?->format('d M, Y') ?? '—' }}</td>
                            <td class="px-4 py-3.5 whitespace-nowrap {{ $project->isOverdue() ? 'font-semibold text-red-500' : 'text-[var(--color-muted)]' }}">{{ $project->deadline?->format('d M, Y') ?? '—' }}</td>
                            <td class="px-4 py-3.5">
                                @if ($project->client && $me->allows('clients', 'view'))
                                    <a href="{{ route('admin.clients.show', $project->client) }}" class="font-medium text-[var(--color-primary)] hover:underline">{{ $project->client->name }}</a>
                                @else
                                    {{ $project->client?->name ?? '—' }}
                                @endif
                            </td>
                            {{-- Status --}}
                            <td class="px-4 py-3.5">
                                @if ($me->allows('projects', 'edit'))
                                    <form method="POST" action="{{ route('admin.projects.status', $project) }}">
                                        @csrf
                                        <div class="relative inline-flex items-center rounded-full {{ $statusPill[$project->status] ?? 'bg-gray-50 text-gray-500' }}">
                                            <span class="pointer-events-none absolute left-3 h-2 w-2 rounded-full {{ $statusDot[$project->status] ?? 'bg-gray-400' }}"></span>
                                            <select name="status" onchange="this.form.submit()" style="color:inherit" class="h-8 cursor-pointer appearance-none rounded-full border-0 bg-transparent pl-7 pr-4 text-xs font-semibold focus:ring-1 focus:ring-[var(--color-primary)]">
                                                @foreach (\App\Models\Project::STATUSES as $k => $v)<option value="{{ $k }}" @selected($project->status === $k)>{{ $v }}</option>@endforeach
                                            </select>
                                        </div>
                                    </form>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusPill[$project->status] ?? 'bg-gray-50 text-gray-500' }}"><span class="h-2 w-2 rounded-full {{ $statusDot[$project->status] ?? 'bg-gray-400' }}"></span>{{ \App\Models\Project::STATUSES[$project->status] ?? $project->status }}</span>
                                @endif
                            </td>
                            {{-- Progress --}}
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full {{ $progress >= 100 ? 'bg-emerald-500' : 'bg-[var(--color-primary)]' }}" style="width: {{ $progress }}%"></div></div>
                                    <span class="text-[11px] font-semibold text-gray-400">{{ $progress }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="window.dispatchEvent(new CustomEvent('project-drawer', { detail: '{{ route('admin.projects.drawer', $project) }}' }))" title="Quick view" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-[var(--color-primary-soft)] hover:text-[var(--color-primary)]">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/><path stroke-linecap="round" d="M15 5v14"/></svg>
                                </button>
                                <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false">
                                    <button type="button" @click="open = !open" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>
                                    <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-36 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg">
                                        <a href="{{ route('admin.projects.show', $project) }}" class="block px-3.5 py-2 text-xs font-medium text-[var(--color-heading)] hover:bg-gray-50">View</a>
                                        @if ($me->allows('projects', 'edit'))<a href="{{ route('admin.projects.edit', $project) }}" class="block px-3.5 py-2 text-xs font-medium text-[var(--color-heading)] hover:bg-gray-50">Edit</a>@endif
                                        @if ($me->allows('projects', 'delete'))
                                            <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project and all of its tasks?')">
                                                @csrf @method('DELETE')
                                                <button class="block w-full px-3.5 py-2 text-left text-xs font-medium text-red-600 hover:bg-red-50">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $projects->links() }}</div>
    @endif

    {{-- ===== Filters drawer (right side) ===== --}}
    <div x-data="{ open: false }" x-cloak @open-filters.window="open = true" @keydown.escape.window="open = false">
        <div class="fixed inset-0 z-50" :class="open ? '' : 'pointer-events-none'">
            <div class="absolute inset-0 bg-black/30" style="transition:opacity .4s ease, backdrop-filter .4s ease" :style="open ? 'opacity:1; backdrop-filter:blur(2px)' : 'opacity:0; backdrop-filter:blur(0)'" @click="open = false"></div>
            <div class="absolute right-0 top-0 flex h-full w-full max-w-sm flex-col bg-white shadow-2xl" style="transition:transform .42s cubic-bezier(.32,.72,0,1)" :style="open ? 'transform:translateX(0)' : 'transform:translateX(100%)'">
                <div class="flex items-start justify-between border-b border-gray-100 px-6 py-5">
                    <div>
                        <h2 class="text-lg font-bold text-[var(--color-heading)]">Filters</h2>
                        <p class="mt-0.5 text-sm text-[var(--color-muted)]">Refine your project list</p>
                    </div>
                    <button type="button" @click="open = false" class="grid h-9 w-9 place-items-center rounded-lg bg-gray-50 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                </div>
                <form method="GET" action="{{ route('admin.projects.index') }}" class="flex flex-1 flex-col overflow-y-auto px-6 py-5">
                    <div class="space-y-5">
                        @php
                            $statusOpts = array_merge(['all' => 'All', 'overdue' => 'Overdue'], \App\Models\Project::STATUSES);
                            $pillOn = 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white shadow-sm';
                            $pillOff = 'border-gray-200 bg-white text-[var(--color-muted)] hover:border-gray-300 hover:bg-gray-50 hover:text-[var(--color-heading)]';
                            $pillBase = 'rounded-full border px-3 py-1.5 text-xs font-semibold transition';
                        @endphp

                        {{-- Status --}}
                        <div x-data="{ val: @js(request('status', 'all')) }">
                            <label class="mb-2 block text-sm font-semibold text-[var(--color-heading)]">Status</label>
                            <input type="hidden" name="status" :value="val">
                            <div class="flex flex-wrap gap-2">
                                @foreach ($statusOpts as $k => $v)
                                    <button type="button" @click="val = @js((string) $k)" :class="val === @js((string) $k) ? '{{ $pillOn }}' : '{{ $pillOff }}'" class="{{ $pillBase }}">{{ $v }}</button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Priority --}}
                        <div x-data="{ val: @js(request('priority', '')) }">
                            <label class="mb-2 block text-sm font-semibold text-[var(--color-heading)]">Priority</label>
                            <input type="hidden" name="priority" :value="val">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="val = ''" :class="val === '' ? '{{ $pillOn }}' : '{{ $pillOff }}'" class="{{ $pillBase }}">All</button>
                                @foreach (\App\Models\Project::PRIORITIES as $k => $v)
                                    <button type="button" @click="val = @js((string) $k)" :class="val === @js((string) $k) ? '{{ $pillOn }}' : '{{ $pillOff }}'" class="{{ $pillBase }}">{{ $v }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Client</label>
                            <x-admin.searchable-select
                                name="client"
                                :options="$clients->map(fn ($c) => ['id' => $c->id, 'label' => $c->name])"
                                :selected="request('client')"
                                placeholder="All Clients"
                                search-placeholder="Search client…"
                                clear-label="All Clients" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Start Date</label>
                            <div class="flex items-center gap-2">
                                <input type="date" name="from" value="{{ request('from') }}" class="h-11 flex-1 rounded-lg border-gray-200 text-sm">
                                <span class="text-sm text-gray-400">to</span>
                                <input type="date" name="to" value="{{ request('to') }}" class="h-11 flex-1 rounded-lg border-gray-200 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">End Date</label>
                            <div class="flex items-center gap-2">
                                <input type="date" name="end_from" value="{{ request('end_from') }}" class="h-11 flex-1 rounded-lg border-gray-200 text-sm">
                                <span class="text-sm text-gray-400">to</span>
                                <input type="date" name="end_to" value="{{ request('end_to') }}" class="h-11 flex-1 rounded-lg border-gray-200 text-sm">
                            </div>
                        </div>
                        {{-- Sort --}}
                        <div x-data="{ sort: @js(request('sort', '')), order: @js(request('order') === 'oldest' ? 'oldest' : 'newest') }">
                            <label class="mb-2 block text-sm font-semibold text-[var(--color-heading)]">Sort By</label>
                            <input type="hidden" name="sort" :value="sort">
                            <input type="hidden" name="order" :value="order">
                            <div class="flex flex-wrap gap-2">
                                @foreach (['' => 'Manual order', 'start' => 'Start Date', 'end' => 'End Date', 'name' => 'Name', 'created' => 'Date Added'] as $k => $v)
                                    <button type="button" @click="sort = @js((string) $k)" :class="sort === @js((string) $k) ? '{{ $pillOn }}' : '{{ $pillOff }}'" class="{{ $pillBase }}">{{ $v }}</button>
                                @endforeach
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2" x-show="sort" x-cloak>
                                @foreach (['newest' => 'Newest First', 'oldest' => 'Oldest First'] as $k => $v)
                                    <button type="button" @click="order = @js($k)" :class="order === @js($k) ? '{{ $pillOn }}' : '{{ $pillOff }}'" class="{{ $pillBase }}">{{ $v }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3 border-t border-gray-100 pt-5">
                        <a href="{{ route('admin.projects.index') }}" class="flex-1 rounded-lg border border-gray-200 px-4 py-3 text-center text-sm font-bold text-[var(--color-heading)] hover:bg-gray-50">Clear Filters</a>
                        <button class="flex-1 rounded-lg bg-[var(--color-primary)] px-4 py-3 text-sm font-bold text-white hover:bg-[var(--color-primary-hover)]">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===== Project quick-view drawer ===== --}}
    <div x-data="projectDrawer()" x-cloak @project-drawer.window="show($event.detail)" @keydown.escape.window="close()">
        <div class="fixed inset-0 z-50" :class="open ? '' : 'pointer-events-none'">
            <div class="absolute inset-0 bg-black/30" style="transition:opacity .4s ease, backdrop-filter .4s ease" :style="open ? 'opacity:1; backdrop-filter:blur(2px)' : 'opacity:0; backdrop-filter:blur(0)'" @click="close()"></div>
            <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl" style="transition:transform .42s cubic-bezier(.32,.72,0,1)" :style="open ? 'transform:translateX(0)' : 'transform:translateX(100%)'">
                <button type="button" @click="close()" title="Close" class="absolute right-3 top-3 z-10 grid h-8 w-8 place-items-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
                <div class="h-full" x-html="content"></div>
            </div>
        </div>
    </div>

    <script>
        function projectDrawer() {
            return {
                open: false, content: '',
                async show(url) {
                    this.open = true;
                    this.content = '<div class="grid h-full place-items-center text-sm text-gray-400">Loading…</div>';
                    try {
                        const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                        this.content = r.ok ? await r.text() : '<p class="p-6 text-sm text-red-500">Failed to load project.</p>';
                    } catch (e) {
                        this.content = '<p class="p-6 text-sm text-red-500">Failed to load project.</p>';
                    }
                },
                close() { this.open = false; },
            };
        }
    </script>

    @if ($canReorder && $projects->isNotEmpty())
        <script>
            (function () {
                const tbody = document.getElementById('projects-tbody');
                if (!tbody) return;
                const url = @js(route('admin.projects.reorder'));
                const base = {{ ($projects->firstItem() ?? 1) - 1 }};
                const csrf = document.querySelector('meta[name=csrf-token]').content;
                let dragRow = null;

                tbody.querySelectorAll('tr[data-project-id]').forEach(function (row) {
                    const handle = row.querySelector('[data-drag-handle]');
                    if (!handle) return;
                    // Only start a drag from the handle (so clicks on selects/buttons still work).
                    handle.addEventListener('mousedown', () => { row.draggable = true; });
                    handle.addEventListener('mouseup', () => { row.draggable = false; });
                    row.addEventListener('dragstart', function (e) { dragRow = row; row.classList.add('opacity-40'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', ''); } catch (x) {} });
                    row.addEventListener('dragend', function () { row.draggable = false; row.classList.remove('opacity-40'); if (dragRow) { dragRow = null; persist(); } });
                    row.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        if (!dragRow || dragRow === row) return;
                        const rect = row.getBoundingClientRect();
                        const after = (e.clientY - rect.top) > rect.height / 2;
                        tbody.insertBefore(dragRow, after ? row.nextSibling : row);
                    });
                });

                function persist() {
                    const ids = [...tbody.querySelectorAll('tr[data-project-id]')].map(r => r.dataset.projectId);
                    fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ ids, base }),
                    }).then(() => { if (window.Razin?.toast) window.Razin.toast('Order saved'); }).catch(() => {});
                }
            })();
        </script>
    @endif
@endsection
