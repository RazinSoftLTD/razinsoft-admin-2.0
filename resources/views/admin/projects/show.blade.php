@extends('admin.layouts.app')
@section('title', $project->name)

@php
    $me = auth()->user();
    $canEdit = $me->allows('projects', 'edit');
    $statusDot = ['todo' => 'bg-sky-500', 'in_progress' => 'bg-blue-500', 'on_hold' => 'bg-amber-500', 'completed' => 'bg-emerald-500', 'cancelled' => 'bg-gray-400'];
    $statusPill = ['todo' => 'bg-sky-50 text-sky-700', 'in_progress' => 'bg-blue-50 text-blue-700', 'on_hold' => 'bg-amber-50 text-amber-700', 'completed' => 'bg-emerald-50 text-emerald-700', 'cancelled' => 'bg-gray-100 text-gray-500'];

    // [key => [label, icon path, count|null]] — sequence set by the design
    $tabs = [
        'overview'   => ['Overview',   'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z', null],
        'board'      => ['Kanban',     'M4 5h6v14H4zM14 5h6v14h-6z', null],
        'tasks'      => ['Tasks',      'm9 12 2 2 4-4M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', $tasks->count()],
        'prd'        => ['PRD',        'M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1ZM8 6H6a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-2M9 12h6M9 16h4', null],
        'files'      => ['Files',      'M7 3h7l5 5v13H7zM14 3v5h5', $project->files->count()],
        'milestones' => ['Milestones', 'M5 21V4m0 0h11l-1.5 3.5L16 11H5', $project->milestones->count()],
        'members'    => ['Members',    'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', $project->members->count()],
    ];
    if ($canEdit) {
        $tabs['settings'] = ['Settings', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19.4 13a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 0 1-4 0v-.2a1.7 1.7 0 0 0-2.9-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0-1.1-2.9H3a2 2 0 0 1 0-4h.2a1.7 1.7 0 0 0 1.1-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 2.9-1.1V3a2 2 0 0 1 4 0v.2a1.7 1.7 0 0 0 2.9 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9Z', null];
    }
    $tabs['activity'] = ['Activity', 'M3 12h4l3 8 4-16 3 8h4', null];
    $isFavorite = in_array($project->id, (array) ($me->favorite_projects ?? []));
    $progress = $project->progressPercent();
@endphp

@section('content')
    {{-- Breadcrumb --}}
    <nav class="mb-2 flex items-center gap-2 text-sm text-[var(--color-muted)]">
        <a href="{{ route('admin.projects.index') }}" class="hover:text-[var(--color-heading)]">Projects</a>
        <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
        <span class="truncate text-[var(--color-heading)]">{{ $project->name }}</span>
    </nav>

    {{-- Title row --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold text-[var(--color-heading)]">{{ $project->name }}</h1>

            @if ($canEdit)
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="rounded-full px-3 py-1 text-sm font-semibold transition hover:opacity-80 {{ $statusPill[$project->status] ?? 'bg-gray-100 text-gray-500' }}">
                        {{ \App\Models\Project::STATUSES[$project->status] ?? $project->status }}
                    </button>
                    <div x-show="open" x-cloak class="absolute left-0 z-30 mt-1.5 w-40 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg">
                        @foreach (\App\Models\Project::STATUSES as $k => $v)
                            <form method="POST" action="{{ route('admin.projects.status', $project) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $k }}">
                                <button class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-medium text-[var(--color-heading)] hover:bg-gray-50 {{ $project->status === $k ? 'bg-gray-50' : '' }}">
                                    <span class="h-2 w-2 rounded-full {{ $statusDot[$k] }}"></span>{{ $v }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @else
                <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $statusPill[$project->status] ?? 'bg-gray-100 text-gray-500' }}">{{ \App\Models\Project::STATUSES[$project->status] ?? $project->status }}</span>
            @endif

            {{-- Favourite --}}
            <form method="POST" action="{{ route('admin.projects.favorite', $project) }}">
                @csrf
                <button type="submit" title="{{ $isFavorite ? 'Remove from favourites' : 'Add to favourites' }}"
                        class="grid h-8 w-8 place-items-center rounded-lg transition {{ $isFavorite ? 'text-amber-400 hover:text-amber-500' : 'text-gray-300 hover:text-gray-400' }}">
                    <svg class="h-5 w-5" fill="{{ $isFavorite ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5-5.8-3-5.8 3 1.1-6.5L2.6 9.8l6.5-.9L12 3Z"/></svg>
                </button>
            </form>
        </div>

        @if ($canEdit)
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open" class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                </button>
                <div x-show="open" x-cloak class="absolute right-0 z-30 mt-1.5 w-44 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg">
                    <a href="{{ route('admin.projects.edit', $project) }}" class="block px-3.5 py-2 text-xs font-medium text-[var(--color-heading)] hover:bg-gray-50">Edit project</a>
                    <a href="{{ route('admin.projects.show', $project) }}?tab=settings" class="block px-3.5 py-2 text-xs font-medium text-[var(--color-heading)] hover:bg-gray-50">Project settings</a>
                    @if ($me->allows('projects', 'delete'))
                        <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project and all of its tasks?')">
                            @csrf @method('DELETE')
                            <button class="block w-full px-3.5 py-2 text-left text-xs font-medium text-red-600 hover:bg-red-50">Delete project</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="mb-6 mt-4 overflow-x-auto">
        <div class="flex min-w-max gap-6 border-b border-gray-200">
            @foreach ($tabs as $key => [$label, $icon, $count])
                @php $on = $tab === $key; @endphp
                <a href="{{ route('admin.projects.show', $project) }}?tab={{ $key }}"
                   class="-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 pb-3 text-sm font-semibold transition {{ $on ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    {{ $label }}
                    @if ($count !== null)
                        <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[11px] font-bold text-gray-500">{{ $count }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    @include('admin.projects.tabs.'.$tab)
@endsection
