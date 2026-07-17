@php
    $byStatus = $tasks->groupBy('status');
    // Donut segments straight from the project's own board columns.
    $statusMeta = $project->columns->mapWithKeys(fn ($c) => [$c->key => [$c->name, $c->color]])->all();
    $taskTotal = max(1, $tasks->count());
    $stops = []; $acc = 0;
    foreach ($statusMeta as $key => [$label, $color]) {
        $count = $byStatus->get($key, collect())->count();
        if (! $count) continue;
        $deg = $count / $taskTotal * 360;
        $stops[] = "{$color} {$acc}deg ".($acc + $deg).'deg';
        $acc += $deg;
    }
    $donut = $stops ? 'conic-gradient('.implode(', ', $stops).')' : 'conic-gradient(#f3f4f6 0deg 360deg)';
    $estimatedMinutes = $tasks->sum('estimated_minutes');
    $overdueTasks = $tasks->filter->isOverdue()->count();
    $cur = $project->currency;
@endphp

<div class="grid gap-5 lg:grid-cols-3">
    {{-- Progress + dates --}}
    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h3 class="text-sm font-bold text-[var(--color-heading)]">Project Progress</h3>
        <div class="mt-5 flex items-center gap-5">
            <div class="relative grid h-24 w-24 shrink-0 place-items-center rounded-full" style="background: conic-gradient(var(--color-primary) {{ $progress * 3.6 }}deg, #f3f4f6 {{ $progress * 3.6 }}deg 360deg)">
                <span class="grid h-[4.5rem] w-[4.5rem] place-items-center rounded-full bg-white text-lg font-extrabold text-[var(--color-heading)]">{{ $progress }}%</span>
            </div>
            <div class="space-y-2 text-sm">
                <p><span class="text-gray-400">Start:</span> <span class="font-semibold text-[var(--color-heading)]">{{ $project->start_date?->format('d M, Y') ?? '—' }}</span></p>
                <p><span class="text-gray-400">Deadline:</span> <span class="font-semibold {{ $project->isOverdue() ? 'text-red-500' : 'text-[var(--color-heading)]' }}">{{ $project->deadline?->format('d M, Y') ?? 'No deadline' }}</span></p>
                <p><span class="text-gray-400">Priority:</span> <span class="font-semibold capitalize text-[var(--color-heading)]">{{ $project->priority }}</span></p>
                <p class="text-xs text-gray-400">{{ $project->auto_progress ? 'Auto — from completed tasks' : 'Manual progress' }}</p>
            </div>
        </div>
    </div>

    {{-- Tasks donut --}}
    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h3 class="text-sm font-bold text-[var(--color-heading)]">Tasks</h3>
        <div class="mt-5 flex items-center gap-5">
            <div class="relative grid h-24 w-24 shrink-0 place-items-center rounded-full" style="background: {{ $donut }}">
                <span class="grid h-[4.5rem] w-[4.5rem] place-items-center rounded-full bg-white text-lg font-extrabold text-[var(--color-heading)]">{{ $tasks->count() }}</span>
            </div>
            <ul class="space-y-1 text-xs">
                @foreach ($statusMeta as $key => [$label, $color])
                    @php $count = $byStatus->get($key, collect())->count(); @endphp
                    @if ($count)
                        <li class="flex items-center gap-2"><span class="h-2 w-2 rounded-full" style="background: {{ $color }}"></span><span class="text-[var(--color-muted)]">{{ $label }}</span><span class="font-bold text-[var(--color-heading)]">{{ $count }}</span></li>
                    @endif
                @endforeach
                @if ($tasks->isEmpty())<li class="text-gray-300">No tasks yet</li>@endif
            </ul>
        </div>
    </div>

    {{-- Client --}}
    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h3 class="text-sm font-bold text-[var(--color-heading)]">Client</h3>
        @if ($project->client)
            <div class="mt-5 flex items-center gap-3">
                @if ($project->client->photo_url)
                    <img src="{{ $project->client->photo_url }}" class="h-12 w-12 rounded-full object-cover" alt="">
                @else
                    <span class="grid h-12 w-12 place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]">{{ collect(explode(' ', $project->client->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('') }}</span>
                @endif
                <div class="min-w-0">
                    <p class="truncate font-semibold text-[var(--color-heading)]">{{ $project->client->name }}</p>
                    @if ($project->client->company)<p class="truncate text-xs text-[var(--color-muted)]">{{ $project->client->company }}</p>@endif
                    <a href="{{ route('admin.clients.show', $project->client) }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">View client</a>
                </div>
            </div>
        @else
            <p class="mt-5 text-sm text-gray-300">No client assigned to this project.</p>
        @endif
        @if ($project->projectManager)
            <div class="mt-4 border-t border-gray-50 pt-3 text-sm">
                <span class="text-gray-400">Project Manager:</span>
                <span class="font-semibold text-[var(--color-heading)]">{{ $project->projectManager->name }}</span>
            </div>
        @endif
    </div>
</div>

{{-- Statistics (desk-style tiles with coin/clock icons) --}}
<h3 class="mb-3 mt-6 text-sm font-bold text-[var(--color-heading)]">Statistics</h3>
<div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
    @php
        $coin = 'M12 2C6.5 2 2 4 2 6.5S6.5 11 12 11s10-2 10-4.5S17.5 2 12 2ZM2 6.5v4C2 13 6.5 15 12 15s10-2 10-4.5v-4M2 10.5v4C2 17 6.5 19 12 19s10-2 10-4.5v-4';
        $clock = 'M12 6v6l4 2M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20Z';
        $done = collect($project->doneKeys())->sum(fn ($k) => $byStatus->get($k, collect())->count());
        $tiles = [
            ['Project Budget', $project->budget ? $cur.' '.number_format((float) $project->budget, 0) : '0', $coin, 'text-[var(--color-primary)]'],
            ['Hours Estimate', $project->hours_allocated ? $project->hours_allocated.'h' : '0h', $clock, 'text-blue-600'],
            ['Estimated Work', $estimatedMinutes ? intdiv($estimatedMinutes, 60).'h '.($estimatedMinutes % 60 ? ($estimatedMinutes % 60).'m' : '') : '0h', $clock, 'text-indigo-600'],
            ['Tasks Done', $done.' / '.$tasks->count(), 'M9 5h10M9 12h10M9 19h10M5 5h.01M5 12h.01M5 19h.01', 'text-emerald-600'],
            ['Overdue Tasks', (string) $overdueTasks, 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z', $overdueTasks ? 'text-red-500' : 'text-gray-400'],
        ];
    @endphp
    @foreach ($tiles as [$label, $value, $icon, $tone])
        <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <div class="min-w-0">
                <p class="truncate text-xs font-medium text-[var(--color-muted)]">{{ $label }}</p>
                <p class="mt-1 text-lg font-bold {{ $tone }}">{{ $value }}</p>
            </div>
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gray-50 {{ $tone }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
            </span>
        </div>
    @endforeach
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-2">
    {{-- Summary --}}
    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h3 class="text-sm font-bold text-[var(--color-heading)]">Summary</h3>
        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-[var(--color-muted)]">{{ $project->summary ?: 'No summary yet.' }}</p>
        @if ($project->notes)
            <h3 class="mt-5 text-sm font-bold text-[var(--color-heading)]">Internal Notes</h3>
            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-[var(--color-muted)]">{{ $project->notes }}</p>
        @endif
    </div>

    {{-- Milestones snapshot + child projects --}}
    <div class="space-y-5">
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-[var(--color-heading)]">Milestones</h3>
                <a href="{{ route('admin.projects.show', $project) }}?tab=milestones" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">View all</a>
            </div>
            <ul class="mt-3 space-y-2">
                @forelse ($project->milestones->take(4) as $m)
                    <li class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full {{ $m->status === 'complete' ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                            <span class="text-[var(--color-heading)]">{{ $m->title }}</span>
                        </span>
                        <span class="text-xs text-gray-400">{{ $m->end_date?->format('d M') ?? '—' }}</span>
                    </li>
                @empty
                    <li class="text-sm text-gray-300">No milestones yet.</li>
                @endforelse
            </ul>
        </div>

        @if ($project->children->isNotEmpty())
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-bold text-[var(--color-heading)]">Child Projects</h3>
                <ul class="mt-3 space-y-2">
                    @foreach ($project->children as $child)
                        <li class="flex items-center justify-between text-sm">
                            <a href="{{ route('admin.projects.show', $child) }}" class="font-medium text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $child->name }}</a>
                            <span class="text-xs text-gray-400">{{ $child->tasks_total }} tasks · {{ \App\Models\Project::STATUSES[$child->status] ?? $child->status }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
