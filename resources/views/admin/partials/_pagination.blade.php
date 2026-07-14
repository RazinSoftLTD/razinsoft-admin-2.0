@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        // Compact window: first three + last two, plus the current page ± 1. '…' fills gaps.
        $nums = collect([1, 2, 3, $last - 1, $last]);
        for ($i = $current - 1; $i <= $current + 1; $i++) {
            $nums->push($i);
        }
        $nums = $nums->filter(fn ($n) => $n >= 1 && $n <= $last)->unique()->sort()->values();

        $btn = 'inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-lg border px-3 text-sm font-medium transition';
        $idle = 'border-gray-200 text-[var(--color-heading)] hover:bg-gray-50';
        $off = 'border-gray-200 text-gray-300 cursor-not-allowed';
    @endphp

    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center gap-1.5">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="{{ $btn }} {{ $off }}" aria-disabled="true"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $btn }} {{ $idle }}" aria-label="Previous"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg></a>
        @endif

        @php $prev = 0; @endphp
        @foreach ($nums as $n)
            @if ($n - $prev > 1)
                <span class="px-1 text-sm text-gray-400">…</span>
            @endif
            @if ($n == $current)
                <span aria-current="page" class="{{ $btn }} border-[var(--color-primary)] bg-[var(--color-primary)] text-white">{{ $n }}</span>
            @else
                <a href="{{ $paginator->url($n) }}" class="{{ $btn }} {{ $idle }}">{{ $n }}</a>
            @endif
            @php $prev = $n; @endphp
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $btn }} {{ $idle }}" aria-label="Next"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 18 6-6-6-6"/></svg></a>
        @else
            <span class="{{ $btn }} {{ $off }}" aria-disabled="true"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 18 6-6-6-6"/></svg></span>
        @endif
    </nav>
@endif
