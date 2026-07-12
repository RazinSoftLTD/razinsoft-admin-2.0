@php
    $cur = $currency['BDT'] ?? '৳';
    // Reusable breakdown list renderer
@endphp

@if (! $leadReport && ! $dealReport)
    <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center text-sm text-gray-400">No report data available for your permissions.</div>
@endif

@if ($leadReport)
    <div class="mb-8">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-[var(--color-heading)]">
            <span class="grid h-6 w-6 place-items-center rounded-md bg-sky-50 text-sky-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0"/></svg></span>
            Lead Report
        </h2>
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ([['Total', $leadReport['total'], 'text-[var(--color-heading)]'], ['New', $leadReport['new'], 'text-blue-700'], ['Qualified', $leadReport['qualified'], 'text-emerald-700'], ['Unqualified', $leadReport['unqualified'], 'text-red-600'], ['Converted', $leadReport['converted'], 'text-violet-700']] as [$l, $v, $t])
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm"><p class="text-xs text-[var(--color-muted)]">{{ $l }}</p><p class="mt-1 text-2xl font-bold {{ $t }}">{{ number_format($v) }}</p></div>
            @endforeach
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ([['By Status', $leadReport['by_status']], ['By Source', $leadReport['by_source']], ['By Priority', $leadReport['by_priority']], ['By Owner', $leadReport['by_owner']]] as [$title, $rows])
                @include('admin.analytics.partials.breakdown', ['title' => $title, 'rows' => $rows])
            @endforeach
        </div>
    </div>
@endif

@if ($dealReport)
    <div>
        <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-[var(--color-heading)]">
            <span class="grid h-6 w-6 place-items-center rounded-md bg-violet-50 text-violet-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 3v18h18M7 14l4-4 3 3 5-6"/></svg></span>
            Deal Report
        </h2>
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
            @foreach ([
                ['Total', number_format($dealReport['total']), 'text-[var(--color-heading)]'],
                ['Open', number_format($dealReport['open']), 'text-blue-700'],
                ['Won', number_format($dealReport['won']), 'text-emerald-700'],
                ['Win Rate', $dealReport['win_rate'].'%', 'text-sky-700'],
                ['Pipeline', $cur.number_format($dealReport['pipeline'], 0), 'text-indigo-700'],
                ['Forecast', $cur.number_format($dealReport['forecast'], 0), 'text-amber-700'],
                ['Won Value', $cur.number_format($dealReport['won_value'], 0), 'text-emerald-700'],
                ['Avg Deal', $cur.number_format($dealReport['avg_size'], 0), 'text-fuchsia-700'],
            ] as [$l, $v, $t])
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm"><p class="text-xs text-[var(--color-muted)]">{{ $l }}</p><p class="mt-1 text-lg font-bold {{ $t }}">{{ $v }}</p></div>
            @endforeach
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ([['By Stage', $dealReport['by_stage']], ['By Project Type', $dealReport['by_type']], ['By Owner', $dealReport['by_owner']]] as [$title, $rows])
                @include('admin.analytics.partials.breakdown', ['title' => $title, 'rows' => $rows])
            @endforeach
        </div>
    </div>
@endif
