{{-- Small stat strip used at the top of a tab. $stats = [[label, value, tone-classes, svg-path], ...] --}}
<div class="mb-4 grid gap-3 sm:grid-cols-2 {{ ['1'=>'','2'=>'lg:grid-cols-2','3'=>'lg:grid-cols-3','4'=>'lg:grid-cols-4'][(string) count($stats)] ?? 'lg:grid-cols-3' }}">
    @foreach ($stats as [$label, $value, $tone, $icon])
        <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3.5 shadow-sm">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $tone }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
            </span>
            <span class="min-w-0">
                <span class="block truncate text-[11px] uppercase tracking-wide text-gray-400">{{ $label }}</span>
                <span class="block text-lg font-extrabold leading-tight text-[var(--color-heading)]">{{ $value }}</span>
            </span>
        </div>
    @endforeach
</div>
