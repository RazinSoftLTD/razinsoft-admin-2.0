{{--
    Day-by-day sales grid.

    $dates  array<string>                     column order
    $series array<array{label,sub,href,daily}> one row each
    $empty  string                            shown when there is nothing to draw
--}}
@php
    // Scale the heat to the busiest single cell so a quiet niche still shows
    // contrast instead of a wall of pale grey.
    $peak = 0;
    foreach ($series as $row) {
        foreach ($row['daily'] as $n) { $peak = max($peak, $n); }
    }
@endphp

@if (! $dates || ! count($series))
    <p class="px-5 py-8 text-center text-sm text-[var(--color-muted)]">{{ $empty }}</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="sticky left-0 z-10 bg-gray-50 px-5 py-3 font-semibold">Name</th>
                    @foreach ($dates as $date)
                        <th class="whitespace-nowrap px-3 py-3 text-center font-semibold">
                            {{ \Illuminate\Support\Carbon::parse($date)->format('d M') }}
                        </th>
                    @endforeach
                    <th class="px-4 py-3 text-right font-semibold">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($series as $row)
                    <tr>
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            @if (! empty($row['href']))
                                <a href="{{ $row['href'] }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $row['label'] }}</a>
                            @else
                                <span class="font-semibold text-[var(--color-heading)]">{{ $row['label'] }}</span>
                            @endif
                            @if (! empty($row['sub']))<p class="text-xs text-[var(--color-muted)]">{{ $row['sub'] }}</p>@endif
                        </td>
                        @foreach ($dates as $date)
                            @php
                                $n = $row['daily'][$date] ?? null;
                                $shade = $n && $peak ? min(4, (int) ceil($n / $peak * 4)) : 0;
                                $tint = [0 => '', 1 => 'bg-emerald-50', 2 => 'bg-emerald-100', 3 => 'bg-emerald-200', 4 => 'bg-emerald-300'][$shade];
                            @endphp
                            <td class="px-3 py-3 text-center {{ $tint }}">
                                @if ($n === null)
                                    {{-- No snapshot that day. Not the same as zero sales. --}}
                                    <span class="text-gray-300" title="No sync that day">·</span>
                                @else
                                    <span class="{{ $n ? 'font-semibold text-[var(--color-heading)]' : 'text-gray-300' }}">{{ $n }}</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-3 text-right font-bold text-[var(--color-heading)]">{{ array_sum($row['daily']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
