@extends('admin.layouts.app')
@section('title', $project->name)

@php
    $me = auth()->user();
    $canEdit = $me->allows('projects', 'edit');
    $statusDot = ['todo' => 'bg-sky-500', 'in_progress' => 'bg-blue-500', 'on_hold' => 'bg-amber-500', 'completed' => 'bg-emerald-500', 'cancelled' => 'bg-gray-400'];
    $tabs = [
        'overview' => 'Overview',
        'tasks' => 'Tasks',
        'board' => 'Task Board',
        'milestones' => 'Milestones',
        'files' => 'Files',
        'members' => 'Members',
        'activity' => 'Activity',
    ];
    $progress = $project->progressPercent();
@endphp

@section('content')
    <a href="{{ route('admin.projects.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Projects
    </a>

    {{-- Header --}}
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $project->name }}</h1>
                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-bold text-gray-500">{{ $project->code }}</span>
                @if ($project->isOverdue())<span class="rounded-md bg-red-50 px-2 py-0.5 text-xs font-bold text-red-600">Overdue</span>@endif
            </div>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                @if ($project->parent)<a href="{{ route('admin.projects.show', $project->parent) }}" class="font-semibold text-[var(--color-primary)] hover:underline">↳ {{ $project->parent->name }}</a> · @endif
                {{ $project->category ?: 'Uncategorised' }}@if ($project->client) · {{ $project->client->name }}@endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- Manage Columns — only on the Task Board tab (fires the modal inside board.blade) --}}
            @if ($canEdit && $tab === 'board')
                <div x-data>
                    <button type="button" @click="$dispatch('open-columns')" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg> Manage Columns
                    </button>
                </div>
            @endif
            @if ($canEdit)
                <form method="POST" action="{{ route('admin.projects.status', $project) }}">
                    @csrf
                    <div class="relative inline-flex items-center">
                        <span class="pointer-events-none absolute left-2.5 h-2 w-2 rounded-full {{ $statusDot[$project->status] ?? 'bg-gray-400' }}"></span>
                        <select name="status" onchange="this.form.submit()" class="h-10 rounded-lg border-gray-200 pl-6 pr-8 text-sm font-medium text-[var(--color-heading)]">
                            @foreach (\App\Models\Project::STATUSES as $k => $v)<option value="{{ $k }}" @selected($project->status === $k)>{{ $v }}</option>@endforeach
                        </select>
                    </div>
                </form>
                <a href="{{ route('admin.projects.edit', $project) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg> Edit
                </a>
            @endif
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-6 overflow-x-auto">
        <div class="flex min-w-max gap-1 border-b border-gray-200">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.projects.show', $project) }}?tab={{ $key }}"
                   class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $tab === $key ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
                    {{ $label }}
                    @if ($key === 'tasks')<span class="ml-1 rounded-full bg-gray-100 px-1.5 text-[10px] font-bold text-gray-500">{{ $tasks->count() }}</span>@endif
                </a>
            @endforeach
        </div>
    </div>

    @include('admin.projects.tabs.'.$tab)
@endsection
