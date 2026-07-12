@php
    $groups = [
        ['key' => 'overdue', 'label' => 'Overdue', 'tone' => 'text-red-600', 'dot' => 'bg-red-500'],
        ['key' => 'today', 'label' => 'Today', 'tone' => 'text-amber-600', 'dot' => 'bg-amber-500'],
        ['key' => 'week', 'label' => 'This Week', 'tone' => 'text-blue-600', 'dot' => 'bg-blue-500'],
        ['key' => 'month', 'label' => 'This Month', 'tone' => 'text-indigo-600', 'dot' => 'bg-indigo-500'],
        ['key' => 'later', 'label' => 'Later', 'tone' => 'text-emerald-600', 'dot' => 'bg-emerald-500'],
    ];
    $total = collect($buckets)->sum(fn ($b) => $b->count());
@endphp

<div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
    @foreach ($groups as $g)
        <a href="#{{ $g['key'] }}" class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition hover:shadow">
            <div class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full {{ $g['dot'] }}"></span><p class="text-xs font-medium text-[var(--color-muted)]">{{ $g['label'] }}</p></div>
            <p class="mt-1 text-2xl font-bold {{ $g['tone'] }}">{{ $buckets[$g['key']]->count() }}</p>
        </a>
    @endforeach
</div>

@if ($total === 0)
    <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center text-sm text-gray-400">No pending follow-ups. 🎉</div>
@else
    <div class="space-y-8">
        @foreach ($groups as $g)
            @php $rows = $buckets[$g['key']]; @endphp
            @if ($rows->isNotEmpty())
                <section id="{{ $g['key'] }}">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full {{ $g['dot'] }}"></span>
                        <h2 class="text-sm font-bold {{ $g['tone'] }}">{{ $g['label'] }}</h2>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ $rows->count() }}</span>
                    </div>
                    <div class="space-y-2">
                        @foreach ($rows as $item)
                            <a href="{{ $item->url }}" class="flex flex-wrap items-center gap-4 rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition hover:shadow">
                                @if ($item->kind === 'lead')
                                    <span class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-sky-50 px-2 py-1 text-xs font-semibold text-sky-700"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0"/></svg> Lead</span>
                                @else
                                    <span class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-violet-50 px-2 py-1 text-xs font-semibold text-violet-700"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 3v18h18M7 14l4-4 3 3 5-6"/></svg> Deal</span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-[var(--color-heading)]">{{ $item->title }}</p>
                                    <p class="truncate text-xs text-[var(--color-muted)]">{{ $item->subtitle ?: '—' }}@if ($item->ref) · {{ $item->ref }}@endif @if ($item->note) · {{ \Illuminate\Support\Str::limit($item->note, 60) }}@endif</p>
                                </div>
                                <div class="text-right"><p class="text-xs text-gray-400">Due</p><p class="text-sm font-semibold {{ $g['tone'] }}">{{ $item->due->format($item->has_time ? 'd M Y, g:i A' : 'd M Y') }}</p></div>
                                <div class="hidden text-right sm:block"><p class="text-xs text-gray-400">Owner</p><p class="text-sm font-medium text-[var(--color-heading)]">{{ $item->owner ?? '—' }}</p></div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
@endif
