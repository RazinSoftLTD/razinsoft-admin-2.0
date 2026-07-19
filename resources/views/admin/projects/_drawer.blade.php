@php
    $statusStyle = ['todo' => 'bg-sky-50 text-sky-700', 'in_progress' => 'bg-blue-50 text-blue-700', 'on_hold' => 'bg-amber-50 text-amber-700', 'completed' => 'bg-emerald-50 text-emerald-700', 'cancelled' => 'bg-gray-100 text-gray-500'];
    $priorityBadge = ['low' => 'bg-gray-100 text-gray-500', 'medium' => 'bg-amber-50 text-amber-600', 'high' => 'bg-orange-50 text-orange-600', 'urgent' => 'bg-red-50 text-red-600'];
    $progress = $project->progressPercent();
    $avatar = function ($u, $size = 'h-7 w-7') {
        if ($u && $u->photo_url) return '<img src="'.e($u->photo_url).'" class="'.$size.' rounded-full object-cover ring-2 ring-white" alt="">';
        $i = strtoupper(substr($u->name ?? '?', 0, 1));
        return '<span class="'.$size.' grid place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)] ring-2 ring-white">'.$i.'</span>';
    };
@endphp

<div class="flex h-full flex-col">
    {{-- Header --}}
    <div class="border-b border-gray-100 px-5 py-4 pr-12">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $project->code }}</p>
        <h2 class="mt-0.5 text-lg font-bold leading-tight text-[var(--color-heading)]">{{ $project->name }}</h2>
        @if ($project->parent)
            <p class="mt-0.5 text-xs text-[var(--color-primary)]">↳ {{ $project->parent->name }}</p>
        @endif
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusStyle[$project->status] ?? 'bg-gray-100 text-gray-500' }}">{{ \App\Models\Project::STATUSES[$project->status] ?? $project->status }}</span>
            <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase {{ $priorityBadge[$project->priority] ?? 'bg-gray-100 text-gray-500' }}">{{ $project->priority }}</span>
        </div>
        <div class="mt-2.5 flex items-center gap-2">
            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full {{ $progress >= 100 ? 'bg-emerald-500' : 'bg-[var(--color-primary)]' }}" style="width: {{ $progress }}%"></div></div>
            <span class="text-[11px] font-semibold text-gray-400">{{ $progress }}%</span>
        </div>
    </div>

    {{-- Body --}}
    <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4">
        {{-- Meta --}}
        <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
            <div><dt class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Client</dt><dd class="mt-0.5 text-sm text-[var(--color-heading)]">{{ $project->client?->name ?? '—' }}</dd></div>
            <div><dt class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Category</dt><dd class="mt-0.5 text-sm text-[var(--color-heading)]">{{ $project->category ?: '—' }}</dd></div>
            <div><dt class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Manager</dt><dd class="mt-0.5 text-sm text-[var(--color-heading)]">{{ $project->projectManager?->name ?? '—' }}</dd></div>
            <div><dt class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Tasks</dt><dd class="mt-0.5 text-sm text-[var(--color-heading)]">{{ $project->tasks_total }}@if ($project->children_count) · {{ $project->children_count }} sub-project(s)@endif</dd></div>
            <div><dt class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Start</dt><dd class="mt-0.5 text-sm text-[var(--color-heading)]">{{ $project->start_date?->format('d M, Y') ?? '—' }}</dd></div>
            <div><dt class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Deadline</dt><dd class="mt-0.5 text-sm {{ $project->isOverdue() ? 'font-semibold text-red-500' : 'text-[var(--color-heading)]' }}">{{ $project->deadline?->format('d M, Y') ?? '—' }}</dd></div>
        </dl>

        {{-- Description --}}
        @if ($project->description)
            <div>
                <p class="mb-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">Description</p>
                <p class="whitespace-pre-line rounded-lg bg-gray-50 p-3 text-sm text-gray-600">{{ $project->description }}</p>
            </div>
        @endif

        {{-- Members --}}
        <div>
            <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-400">Members ({{ $project->members->count() }})</p>
            @if ($project->members->isEmpty())
                <p class="text-sm text-gray-400">No members assigned.</p>
            @else
                <div class="space-y-2">
                    @foreach ($project->members as $m)
                        <div class="flex items-center gap-2.5">
                            {!! $avatar($m->user) !!}
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-[var(--color-heading)]">{{ $m->user?->name ?? '—' }}</p>
                                @if ($m->user?->job_title)<p class="truncate text-[11px] text-gray-400">{{ $m->user->job_title }}</p>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Milestones --}}
        <div>
            <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-400">Milestones ({{ $project->milestones->count() }})</p>
            @if ($project->milestones->isEmpty())
                <p class="text-sm text-gray-400">No milestones yet.</p>
            @else
                <div class="space-y-1.5">
                    @foreach ($project->milestones as $ms)
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 px-3 py-2">
                            <span class="min-w-0 truncate text-sm text-[var(--color-heading)]">{{ $ms->title }}</span>
                            <span class="shrink-0 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $ms->status === 'complete' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $ms->status === 'complete' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                {{ \App\Models\ProjectMilestone::STATUSES[$ms->status] ?? $ms->status }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Footer --}}
    <div class="border-t border-gray-100 p-4">
        <a href="{{ route('admin.projects.show', $project) }}" class="flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            Open full project
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </a>
    </div>
</div>
