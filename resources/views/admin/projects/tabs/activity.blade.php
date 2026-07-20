@php
    // action => [icon path, tint]. Falls back to a neutral clock.
    $actionStyle = [
        'created' => ['M12 8v8m-4-4h8M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'bg-[var(--color-primary-soft)] text-[var(--color-primary)]'],
        'status' => ['M20 12a8 8 0 1 1-2.3-5.7M20 4v4h-4', 'bg-[var(--color-primary-soft)] text-[var(--color-primary)]'],
        'task' => ['M9 5h10M9 12h10M9 19h10M5 5h.01M5 12h.01M5 19h.01', 'bg-sky-50 text-sky-600'],
        'milestone' => ['M5 21V4m0 0h11l-1.5 3.5L16 11H5', 'bg-emerald-50 text-emerald-600'],
        'member' => ['M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'bg-violet-50 text-violet-600'],
        'file' => ['M7 3h7l5 5v13H7zM14 3v5h5', 'bg-amber-50 text-amber-600'],
        'comment' => ['M21 12a8 8 0 0 1-8 8H3l2-3a8 8 0 1 1 16-5Z', 'bg-blue-50 text-blue-600'],
        'updated' => ['M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z', 'bg-gray-100 text-gray-500'],
    ];
@endphp

<div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
    @if ($activities->isEmpty())
        <p class="py-10 text-center text-sm text-gray-400">No activity recorded yet.</p>
    @else
        <ul class="relative space-y-5">
            {{-- rail behind the icons --}}
            <span class="pointer-events-none absolute w-px bg-gray-100" style="left:16px;top:16px;bottom:16px"></span>

            @foreach ($activities as $log)
                @php [$icon, $tint] = $actionStyle[$log->action] ?? ['M12 8v5l3 2M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'bg-gray-100 text-gray-400']; @endphp
                <li class="relative flex items-start gap-3">
                    <span class="z-10 grid h-8 w-8 shrink-0 place-items-center rounded-full ring-4 ring-white {{ $tint }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </span>

                    @if ($log->user?->photo_url)
                        <img src="{{ $log->user->photo_url }}" class="h-8 w-8 shrink-0 rounded-full object-cover" alt="">
                    @else
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gray-100 text-xs font-bold text-gray-500">{{ strtoupper(substr($log->user?->name ?? '?', 0, 1)) }}</span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-[var(--color-muted)]">
                            <span class="font-semibold text-[var(--color-heading)]">{{ $log->user?->name ?? 'System' }}</span>
                            {{ $log->description }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">
                            {{ $log->created_at->format('d M, Y') }} at {{ $log->created_at->format('h:i A') }}
                            <span class="text-gray-300">·</span> {{ $log->created_at->diffForHumans() }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
