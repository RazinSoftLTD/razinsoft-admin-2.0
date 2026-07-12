@extends('admin.layouts.app')
@section('title', 'Projects')

@php
    $statusBadge = [
        'draft' => 'bg-gray-100 text-gray-600', 'requirement_collection' => 'bg-sky-50 text-sky-700',
        'requirements_pending' => 'bg-amber-50 text-amber-700', 'planning' => 'bg-indigo-50 text-indigo-700',
        'development' => 'bg-blue-50 text-blue-700', 'internal_testing' => 'bg-purple-50 text-purple-700',
        'client_review' => 'bg-cyan-50 text-cyan-700', 'bug_fixing' => 'bg-orange-50 text-orange-700',
        'deployment' => 'bg-teal-50 text-teal-700', 'delivered' => 'bg-emerald-50 text-emerald-700',
        'maintenance' => 'bg-lime-50 text-lime-700', 'completed' => 'bg-emerald-100 text-emerald-800',
        'on_hold' => 'bg-yellow-50 text-yellow-700', 'cancelled' => 'bg-red-50 text-red-600',
    ];
    $priorityDot = ['low' => 'bg-gray-300', 'medium' => 'bg-amber-400', 'high' => 'bg-orange-500', 'critical' => 'bg-red-500'];
    $me = auth()->user();
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Projects</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Workspace &rsaquo; Projects</p>
        </div>
        @if ($me->allows('projects', 'create'))
            <a href="{{ route('admin.projects.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> New Project
            </a>
        @endif
    </div>

    {{-- Stats --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([['Total', $stats['total'], 'text-[var(--color-heading)]'], ['Active', $stats['active'], 'text-blue-700'], ['On Hold', $stats['on_hold'], 'text-amber-600'], ['Completed', $stats['completed'], 'text-emerald-700']] as [$label, $value, $tone])
            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-[var(--color-muted)]">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold {{ $tone }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name / code / company…" class="h-10 w-64 rounded-lg border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
        <select name="status" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="">All Status</option>
            @foreach (\App\Models\Project::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="type" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="">All Types</option>
            @foreach (\App\Models\Project::TYPES as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>@endforeach
        </select>
        <select name="priority" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="">Any Priority</option>
            @foreach (\App\Models\Project::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(request('priority') === $k)>{{ $v }}</option>@endforeach
        </select>
        <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Filter</button>
        @if (request()->hasAny(['search', 'status', 'type', 'priority']))
            <a href="{{ route('admin.projects.index') }}" class="text-xs font-semibold text-gray-400 hover:text-red-500">Clear</a>
        @endif
    </form>

    @if ($projects->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-400">No projects found.</p>
            @if ($me->allows('projects', 'create'))<a href="{{ route('admin.projects.create') }}" class="mt-2 inline-block text-sm font-semibold text-[var(--color-primary)] hover:underline">Create your first project</a>@endif
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($projects as $project)
                @php $progress = $project->computed_progress; @endphp
                <a href="{{ route('admin.projects.show', $project) }}" class="group flex flex-col rounded-xl border border-gray-100 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $project->code }}</p>
                            <h3 class="mt-0.5 truncate font-bold text-[var(--color-heading)] group-hover:text-[var(--color-primary)]">{{ $project->name }}</h3>
                        </div>
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $priorityDot[$project->priority] ?? 'bg-gray-300' }}" title="{{ ucfirst($project->priority) }} priority"></span>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusBadge[$project->status] ?? 'bg-gray-100 text-gray-600' }}">{{ \App\Models\Project::STATUSES[$project->status] ?? $project->status }}</span>
                        @if ($project->project_type)<span class="inline-flex rounded-full bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-500">{{ $project->project_type }}</span>@endif
                    </div>
                    <p class="mt-3 truncate text-xs text-[var(--color-muted)]">{{ $project->client?->name ?? $project->company ?? 'No client' }}</p>

                    <div class="mt-4">
                        <div class="mb-1 flex items-center justify-between text-xs">
                            <span class="text-gray-400">Progress</span>
                            <span class="font-semibold text-[var(--color-heading)]">{{ $progress }}%</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-[var(--color-primary)]" style="width: {{ $progress }}%"></div></div>
                    </div>

                    <div class="mt-4 flex items-center justify-between border-t border-gray-50 pt-3 text-xs text-gray-400">
                        <span>{{ $project->workstreams_count }} workstream{{ $project->workstreams_count === 1 ? '' : 's' }} · {{ $project->tasks_total }} task{{ $project->tasks_total === 1 ? '' : 's' }}</span>
                        <span>{{ $project->expected_delivery?->format('d M Y') ?? '—' }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $projects->links() }}</div>
    @endif
@endsection
