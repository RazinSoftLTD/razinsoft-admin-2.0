@extends('admin.layouts.app')
@section('title', 'Projects')

@php
    $me = auth()->user();
    $statusDot = ['todo' => 'bg-sky-500', 'in_progress' => 'bg-blue-500', 'on_hold' => 'bg-amber-500', 'completed' => 'bg-emerald-500', 'cancelled' => 'bg-gray-400'];
    $priorityBadge = ['low' => 'bg-gray-100 text-gray-500', 'medium' => 'bg-amber-50 text-amber-600', 'high' => 'bg-orange-50 text-orange-600', 'urgent' => 'bg-red-50 text-red-600'];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Projects</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Workspace &rsaquo; Projects</p>
        </div>
        @if ($me->allows('projects', 'create'))
            <a href="{{ route('admin.projects.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Project
            </a>
        @endif
    </div>

    {{-- Stats --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([['Total Projects', $stats['total'], 'text-[var(--color-heading)]', 'bg-gray-50'], ['In Progress', $stats['in_progress'], 'text-blue-700', 'bg-blue-50'], ['Overdue', $stats['overdue'], 'text-red-600', 'bg-red-50'], ['Completed', $stats['completed'], 'text-emerald-700', 'bg-emerald-50']] as [$label, $value, $tone, $chip])
            <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                <div>
                    <p class="text-xs font-medium text-[var(--color-muted)]">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold {{ $tone }}">{{ $value }}</p>
                </div>
                <span class="grid h-10 w-10 place-items-center rounded-full {{ $chip }} {{ $tone }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg>
                </span>
            </div>
        @endforeach
    </div>

    {{-- Filter bar --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2 rounded-xl border border-gray-100 bg-white p-3 shadow-sm">
        <div class="flex items-center gap-1.5 text-sm">
            <span class="text-xs font-semibold text-[var(--color-muted)]">Start</span>
            <input type="date" name="from" value="{{ request('from') }}" class="h-10 rounded-lg border-gray-200 text-sm">
            <span class="text-gray-300">→</span>
            <input type="date" name="to" value="{{ request('to') }}" class="h-10 rounded-lg border-gray-200 text-sm">
        </div>
        <select name="status" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="all">All Status</option>
            <option value="overdue" @selected(request('status') === 'overdue')>Overdue</option>
            @foreach (\App\Models\Project::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="category" class="h-10 max-w-44 rounded-lg border-gray-200 text-sm">
            <option value="">All Categories</option>
            @foreach (\App\Models\Project::CATEGORIES as $c)<option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>@endforeach
        </select>
        <select name="client" class="h-10 max-w-44 rounded-lg border-gray-200 text-sm">
            <option value="">All Clients</option>
            @foreach ($clients as $c)<option value="{{ $c->id }}" @selected(request('client') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Start typing to search…" class="h-10 w-52 rounded-lg border-gray-200 pl-9 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
        </div>
        <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Filter</button>
        @if (request()->hasAny(['search', 'status', 'category', 'client', 'from', 'to']))
            <a href="{{ route('admin.projects.index') }}" class="text-xs font-semibold text-gray-400 hover:text-red-500">Clear</a>
        @endif
    </form>

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
                        <th class="px-4 py-3 font-semibold">Code</th>
                        <th class="px-4 py-3 font-semibold">Project Name</th>
                        <th class="px-4 py-3 font-semibold">Members</th>
                        <th class="px-4 py-3 font-semibold">Start Date</th>
                        <th class="px-4 py-3 font-semibold">Deadline</th>
                        <th class="px-4 py-3 font-semibold">Client</th>
                        <th class="w-56 px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($projects as $project)
                        @php $progress = $project->progressPercent(); @endphp
                        <tr class="transition hover:bg-gray-50/60">
                            <td class="px-4 py-3.5 whitespace-nowrap text-xs font-semibold text-gray-400">{{ $project->code }}</td>
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
                            <td class="px-4 py-3.5">{{ $project->client?->name ?? '—' }}</td>
                            <td class="px-4 py-3.5">
                                <div class="mb-1.5 flex items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full {{ $progress >= 100 ? 'bg-emerald-500' : 'bg-[var(--color-primary)]' }}" style="width: {{ $progress }}%"></div></div>
                                    <span class="text-[11px] font-semibold text-gray-400">{{ $progress }}%</span>
                                </div>
                                @if ($me->allows('projects', 'edit'))
                                    <form method="POST" action="{{ route('admin.projects.status', $project) }}">
                                        @csrf
                                        <div class="relative inline-flex items-center">
                                            <span class="pointer-events-none absolute left-2.5 h-2 w-2 rounded-full {{ $statusDot[$project->status] ?? 'bg-gray-400' }}"></span>
                                            <select name="status" onchange="this.form.submit()" class="h-8 rounded-lg border-gray-200 pl-6 pr-7 text-xs font-medium text-[var(--color-heading)] focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                                @foreach (\App\Models\Project::STATUSES as $k => $v)<option value="{{ $k }}" @selected($project->status === $k)>{{ $v }}</option>@endforeach
                                            </select>
                                        </div>
                                    </form>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-heading)]"><span class="h-2 w-2 rounded-full {{ $statusDot[$project->status] ?? 'bg-gray-400' }}"></span>{{ \App\Models\Project::STATUSES[$project->status] ?? $project->status }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right">
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
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $projects->links() }}</div>
    @endif
@endsection
