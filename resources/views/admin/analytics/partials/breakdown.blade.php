{{-- Reusable breakdown card: $title (string), $rows ([['label','value','pct'], ...]) --}}
<div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
    <h3 class="mb-3 text-sm font-bold text-[var(--color-heading)]">{{ $title }}</h3>
    @if (empty($rows))
        <p class="text-sm text-gray-300">No data.</p>
    @else
        <div class="space-y-2.5">
            @foreach ($rows as $row)
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="truncate text-[var(--color-heading)]">{{ $row['label'] }}</span>
                        <span class="ml-2 shrink-0 font-semibold text-gray-500">{{ number_format($row['value']) }} <span class="text-xs text-gray-400">· {{ $row['pct'] }}%</span></span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-[var(--color-primary)]" style="width: {{ max($row['pct'], 2) }}%"></div></div>
                </div>
            @endforeach
        </div>
    @endif
</div>
