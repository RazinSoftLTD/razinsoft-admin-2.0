@extends('admin.layouts.app')
@section('title', $cfg['label'].' Activity')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $cfg['label'] }} Activity</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">{{ $cfg['hint'] }}</p>
        </div>

        {{-- Date filter --}}
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <select name="date_range" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                <option value="">All time</option>
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $dv => $dl)
                    <option value="{{ $dv }}" @selected(request('date_range') === $dv)>{{ $dl }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="h-10 rounded-lg border border-gray-200 px-2 text-sm">
            <input type="date" name="to" value="{{ request('to') }}" class="h-10 rounded-lg border border-gray-200 px-2 text-sm">
            <button class="h-10 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply</button>
            <a href="{{ route('admin.client-activity.content', ['type' => $type]) }}" class="h-10 rounded-lg border border-gray-200 px-4 text-sm font-semibold leading-10 text-[var(--color-muted)] hover:bg-gray-50">Clear</a>
        </form>
    </div>

    {{-- ===== Headline stats ===== --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $stats = [
                ['label' => 'Total Views', 'value' => number_format($totalViews), 'icon' => 'M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z', 'tint' => 'bg-[var(--color-primary-soft)] text-[var(--color-primary)]'],
                ['label' => 'Unique Visitors', 'value' => number_format($uniqueVisitors), 'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0', 'tint' => 'bg-emerald-50 text-emerald-600'],
                ['label' => 'Logged-in Clients', 'value' => number_format($knownClients), 'icon' => 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM5 21v-1a6 6 0 0 1 12 0v1M19 8v4M21 10h-4', 'tint' => 'bg-sky-50 text-sky-600'],
                ['label' => 'Top Country', 'value' => $topCountry->country ?? '—', 'sub' => $topCountry ? number_format($topCountry->views).' views' : null, 'icon' => 'M2.5 12a9.5 9.5 0 1 0 19 0 9.5 9.5 0 0 0-19 0Zm0 0h19M12 2.5c2.5 2.6 2.5 16.4 0 19M12 2.5c-2.5 2.6-2.5 16.4 0 19', 'tint' => 'bg-violet-50 text-violet-600'],
            ];
        @endphp
        @foreach ($stats as $s)
            <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg {{ $s['tint'] }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $s['icon'] }}"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $s['label'] }}</p>
                    <p class="text-lg font-bold text-[var(--color-heading)]">{{ $s['value'] }}</p>
                    @if (! empty($s['sub']))<p class="text-xs text-[var(--color-muted)]">{{ $s['sub'] }}</p>@endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ===== Popularity table ===== --}}
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Most Popular {{ $cfg['label'] }}</h2>
                <p class="text-xs text-[var(--color-muted)]">Ranked by views — with unique visitors, logged-in clients and the top country for each {{ $cfg['noun'] }}.</p>
            </div>
            @php $maxViews = max(1, (int) collect($items->items())->max('views')); @endphp
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">{{ Str::singular($cfg['label']) }}</th>
                            <th class="px-5 py-3 text-right font-semibold">Views</th>
                            <th class="px-5 py-3 text-right font-semibold">Unique visitors</th>
                            <th class="px-5 py-3 text-right font-semibold">Clients</th>
                            <th class="px-5 py-3 font-semibold">Top country</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $i => $item)
                            <tr class="hover:bg-gray-50">
                                <td class="max-w-md px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-gray-100 text-xs font-bold text-[var(--color-muted)]">{{ $items->firstItem() + $loop->index }}</span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-[var(--color-heading)]">{{ $item->title ?: $item->path }}</span>
                                            <span class="block truncate font-mono text-[11px] text-[var(--color-primary)]">{{ $item->path }}</span>
                                            <span class="mt-1 block h-1 overflow-hidden rounded-full bg-gray-100"><span class="block h-full rounded-full bg-[var(--color-primary)]" style="width: {{ round($item->views / $maxViews * 100) }}%"></span></span>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right font-bold text-[var(--color-heading)]">{{ number_format($item->views) }}</td>
                                <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ number_format($item->visitors) }}</td>
                                <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ number_format($item->clients) }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">
                                    @php $cc = $countryPerItem[$item->path] ?? null; @endphp
                                    {{ $cc?->country ?? '—' }}@if ($cc) <span class="text-xs text-gray-400">({{ number_format($cc->views) }})</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-12 text-center text-gray-400">No {{ strtolower($cfg['label']) }} views recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $items->links() }}</div>
        </div>

        {{-- ===== Country breakdown ===== --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-1">
            <h2 class="mb-1 text-sm font-bold text-[var(--color-heading)]">{{ $cfg['label'] }} Traffic by Country</h2>
            <p class="mb-4 text-xs text-[var(--color-muted)]">Where {{ strtolower($cfg['label']) }} readers come from.</p>
            @php $maxC = max(1, (int) ($topCountries->max('visitors') ?? 1)); $sumC = max(1, (int) $topCountries->sum('visitors')); @endphp
            <div class="space-y-3">
                @forelse ($topCountries as $c)
                    <div>
                        <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-medium text-[var(--color-heading)]">{{ $c->country }}</span>
                            <span class="shrink-0 text-right">
                                <span class="font-bold text-[var(--color-heading)]">{{ number_format($c->visitors) }}</span>
                                <span class="text-[11px] text-[var(--color-muted)]">· {{ number_format($c->views) }} views · {{ round($c->visitors / $sumC * 100) }}%</span>
                            </span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ round($c->visitors / $maxC * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No country data yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
