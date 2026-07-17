@php
    $actionIcon = [
        'created' => 'M12 5v14M5 12h14', 'status' => 'M4 12h16', 'task' => 'M9 5h10M9 12h10M9 19h10M5 5h.01M5 12h.01M5 19h.01',
        'milestone' => 'M5 3v18M5 4h11l-2 4 2 4H5', 'member' => 'M16 21v-2a4 4 0 0 0-8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
        'file' => 'M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z', 'comment' => 'M21 12a8 8 0 0 1-8 8H3l2-3a8 8 0 1 1 16-5Z',
        'updated' => 'M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z',
    ];
@endphp

<div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
    @if ($activities->isEmpty())
        <p class="py-10 text-center text-sm text-gray-400">No activity recorded yet.</p>
    @else
        <ol class="relative space-y-5 border-l border-gray-100 pl-6">
            @foreach ($activities as $log)
                <li class="relative">
                    <span class="absolute -left-[31px] grid h-6 w-6 place-items-center rounded-full border-2 border-white bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $actionIcon[$log->action] ?? 'M12 6v6l4 2' }}"/></svg>
                    </span>
                    <p class="text-sm text-[var(--color-heading)]">{{ $log->description }}</p>
                    <p class="mt-0.5 text-xs text-gray-400">{{ $log->user?->name ?? 'System' }} · {{ $log->created_at->format('d M, Y h:i A') }} · {{ $log->created_at->diffForHumans() }}</p>
                </li>
            @endforeach
        </ol>
    @endif
</div>
