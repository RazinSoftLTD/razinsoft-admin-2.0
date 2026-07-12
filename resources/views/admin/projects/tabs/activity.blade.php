@php
    $icon = [
        'created' => 'M12 5v14M5 12h14', 'updated' => 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z',
        'status' => 'M3 3v18h18M7 14l4-4 3 3 5-6', 'task' => 'm5 13 4 4L19 7', 'workstream' => 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z',
        'checklist' => 'M9 11l3 3 8-8M21 12v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h11', 'document' => 'M7 3h7l5 5v13H7zM14 3v5h5', 'change_request' => 'M4 4v6h6M20 20v-6h-6M20 9A8 8 0 0 0 5.6 5.6M4 15a8 8 0 0 0 14.4 3.4',
    ];
@endphp

<div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
    <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]">Activity Log</h2>
    <div class="space-y-4">
        @forelse ($project->activityLogs as $log)
            <div class="flex gap-3">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[var(--color-primary)]"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon[$log->action] ?? $icon['updated'] }}"/></svg></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-[var(--color-heading)]">{{ $log->description }}</p>
                    <p class="mt-0.5 text-xs text-gray-400">{{ $log->user?->name ?? 'System' }} · {{ $log->created_at->diffForHumans() }}</p>
                </div>
            </div>
        @empty
            <p class="text-sm text-[var(--color-muted)]">No activity yet.</p>
        @endforelse
    </div>
</div>
