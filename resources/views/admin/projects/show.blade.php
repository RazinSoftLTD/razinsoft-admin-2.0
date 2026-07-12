@extends('admin.layouts.app')
@section('title', 'Project — '.$project->name)

@php
    use App\Models\Project; use App\Models\ProjectTask; use App\Models\ProjectWorkstream; use App\Models\ProjectChecklistItem; use App\Models\ProjectChangeRequest;
    $sym = \App\Models\Currency::symbolMap();
    $cur = $sym[$project->currency] ?? '';
    $statusBadge = [
        'draft' => 'bg-gray-100 text-gray-600', 'requirement_collection' => 'bg-sky-50 text-sky-700',
        'requirements_pending' => 'bg-amber-50 text-amber-700', 'planning' => 'bg-indigo-50 text-indigo-700',
        'development' => 'bg-blue-50 text-blue-700', 'internal_testing' => 'bg-purple-50 text-purple-700',
        'client_review' => 'bg-cyan-50 text-cyan-700', 'bug_fixing' => 'bg-orange-50 text-orange-700',
        'deployment' => 'bg-teal-50 text-teal-700', 'delivered' => 'bg-emerald-50 text-emerald-700',
        'maintenance' => 'bg-lime-50 text-lime-700', 'completed' => 'bg-emerald-100 text-emerald-800',
        'on_hold' => 'bg-yellow-50 text-yellow-700', 'cancelled' => 'bg-red-50 text-red-600',
    ];
    $taskCol = ['todo' => 'bg-gray-400', 'in_progress' => 'bg-blue-500', 'blocked' => 'bg-red-500', 'review' => 'bg-purple-500', 'qa' => 'bg-amber-500', 'completed' => 'bg-emerald-500', 'cancelled' => 'bg-gray-300'];
    $priDot = ['low' => 'bg-gray-300', 'medium' => 'bg-amber-400', 'high' => 'bg-orange-500', 'critical' => 'bg-red-500'];
    $chkBadge = ['waiting' => 'bg-gray-100 text-gray-600', 'received' => 'bg-blue-50 text-blue-700', 'rejected' => 'bg-red-50 text-red-600', 'approved' => 'bg-emerald-50 text-emerald-700', 'need_update' => 'bg-amber-50 text-amber-700'];
    $wsBadge = ['not_started' => 'bg-gray-100 text-gray-600', 'planning' => 'bg-indigo-50 text-indigo-700', 'development' => 'bg-blue-50 text-blue-700', 'testing' => 'bg-amber-50 text-amber-700', 'review' => 'bg-purple-50 text-purple-700', 'completed' => 'bg-emerald-50 text-emerald-700'];
    $progress = $project->computed_progress;
    $tabs = ['overview' => 'Overview', 'workstreams' => 'Workstreams', 'tasks' => 'Tasks', 'checklist' => 'Checklist', 'documents' => 'Documents', 'change-requests' => 'Change Requests', 'activity' => 'Activity'];
    $me = auth()->user();
    $tabUrl = fn ($t) => route('admin.projects.show', $project).'?tab='.$t;
@endphp

@section('content')
    {{-- Header --}}
    <div class="mb-5">
        <a href="{{ route('admin.projects.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Projects
        </a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $project->code }}@if ($project->project_type) · {{ $project->project_type }}@endif</p>
                <h1 class="mt-0.5 text-xl font-bold text-[var(--color-heading)]">{{ $project->name }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('admin.projects.status', $project) }}" data-turbo="false">
                    @csrf
                    <select name="status" onchange="this.form.submit()" class="h-10 rounded-lg border-gray-200 text-sm font-semibold {{ $statusBadge[$project->status] ?? '' }}">
                        @foreach (Project::STATUSES as $k => $v)<option value="{{ $k }}" @selected($project->status === $k)>{{ $v }}</option>@endforeach
                    </select>
                </form>
                @if ($me->allows('projects', 'edit'))
                    <a href="{{ route('admin.projects.edit', $project) }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Edit</a>
                @endif
            </div>
        </div>
        {{-- Progress --}}
        <div class="mt-4 flex items-center gap-3">
            <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-[var(--color-primary)]" style="width: {{ $progress }}%"></div></div>
            <span class="text-sm font-bold text-[var(--color-heading)]">{{ $progress }}%</span>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-5 flex gap-1 overflow-x-auto border-b border-gray-100">
        @foreach ($tabs as $key => $label)
            @php
                $count = match ($key) {
                    'workstreams' => $project->workstreams->count(),
                    'tasks' => $project->allTasks->whereNull('parent_id')->count(),
                    'checklist' => $project->checklistItems->count(),
                    'documents' => $project->documents->count(),
                    'change-requests' => $project->changeRequests->count(),
                    default => null,
                };
            @endphp
            <a href="{{ $tabUrl($key) }}" data-turbo="false" class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $tab === $key ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
                {{ $label }}@if ($count !== null)<span class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-500">{{ $count }}</span>@endif
            </a>
        @endforeach
    </div>

    @includeWhen($tab === 'overview', 'admin.projects.tabs.overview')
    @includeWhen($tab === 'workstreams', 'admin.projects.tabs.workstreams')
    @includeWhen($tab === 'tasks', 'admin.projects.tabs.tasks')
    @includeWhen($tab === 'checklist', 'admin.projects.tabs.checklist')
    @includeWhen($tab === 'documents', 'admin.projects.tabs.documents')
    @includeWhen($tab === 'change-requests', 'admin.projects.tabs.change-requests')
    @includeWhen($tab === 'activity', 'admin.projects.tabs.activity')
@endsection
