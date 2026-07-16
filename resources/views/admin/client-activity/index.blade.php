@extends('admin.layouts.app')
@section('title', 'Client Activity')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-3 text-xl font-bold text-[var(--color-heading)]">
                Client Activity
                <span id="live-badge" class="hidden items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600">
                    <span class="relative flex h-2 w-2"><span id="live-ping" class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span></span>
                    Live
                </span>
            </h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Who visits the website, which pages they browse, and from which country — one row per visitor. Updates live as visits happen.</p>
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
            <a href="{{ route('admin.client-activity') }}" class="h-10 rounded-lg border border-gray-200 px-4 text-sm font-semibold leading-10 text-[var(--color-muted)] hover:bg-gray-50">Clear</a>
        </form>
    </div>

    <div id="live-region">
    {{-- ===== Headline stats ===== --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $stats = [
                ['label' => 'Total Visits', 'value' => number_format($totalVisits), 'icon' => 'M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z', 'tint' => 'bg-[var(--color-primary-soft)] text-[var(--color-primary)]'],
                ['label' => 'Unique Visitors', 'value' => number_format($uniqueVisitors), 'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0', 'tint' => 'bg-emerald-50 text-emerald-600'],
                ['label' => 'Logged-in Clients', 'value' => number_format($knownClients), 'icon' => 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM5 21v-1a6 6 0 0 1 12 0v1M19 8v4M21 10h-4', 'tint' => 'bg-sky-50 text-sky-600'],
                ['label' => 'Top Country', 'value' => $topCountry->country ?? '—', 'sub' => $topCountry ? number_format($topCountry->visits).' visits' : null, 'icon' => 'M2.5 12a9.5 9.5 0 1 0 19 0 9.5 9.5 0 0 0-19 0Zm0 0h19M12 2.5c2.5 2.6 2.5 16.4 0 19M12 2.5c-2.5 2.6-2.5 16.4 0 19', 'tint' => 'bg-violet-50 text-violet-600'],
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

    {{-- ===== Reports: top pages + top countries ===== --}}
    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        {{-- Top pages --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-1 text-sm font-bold text-[var(--color-heading)]">Most Visited Pages</h2>
            <p class="mb-4 text-xs text-[var(--color-muted)]">Which screens, blogs and products get the most attention.</p>
            @php $maxPage = max(1, (int) ($topPages->max('visits') ?? 1)); @endphp
            <div class="space-y-3">
                @forelse ($topPages as $p)
                    <div>
                        <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-[var(--color-heading)]">{{ $p->title ?: $p->path }}</span>
                                <span class="block truncate font-mono text-[11px] text-[var(--color-muted)]">{{ $p->path }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="font-bold text-[var(--color-heading)]">{{ number_format($p->visits) }}</span>
                                <span class="block text-[11px] text-[var(--color-muted)]">{{ number_format($p->visitors) }} visitor(s)</span>
                            </span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-[var(--color-primary)]" style="width: {{ round($p->visits / $maxPage * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No visits yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Top countries --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-1 text-sm font-bold text-[var(--color-heading)]">Visitors by Country</h2>
            <p class="mb-4 text-xs text-[var(--color-muted)]">Where your traffic comes from.</p>
            @php $maxCountry = max(1, (int) ($topCountries->max('visitors') ?? 1)); $sumCountryVisitors = max(1, (int) $topCountries->sum('visitors')); @endphp
            <div class="space-y-3">
                @forelse ($topCountries as $c)
                    <div>
                        <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-medium text-[var(--color-heading)]">{{ $c->country }}</span>
                            <span class="shrink-0 text-right">
                                <span class="font-bold text-[var(--color-heading)]">{{ number_format($c->visitors) }}</span>
                                <span class="text-[11px] text-[var(--color-muted)]">visitor(s) · {{ number_format($c->visits) }} visits · {{ round($c->visitors / $sumCountryVisitors * 100) }}%</span>
                            </span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ round($c->visitors / $maxCountry * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No country data yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ===== Visitors (one row per visitor — latest visit) ===== --}}
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Visitors</h2>
            <p class="text-xs text-[var(--color-muted)]">One row per visitor — their most recent visit. Open a visitor to see their full history.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Visitor</th>
                        <th class="px-5 py-3 font-semibold">Country</th>
                        <th class="px-5 py-3 font-semibold">Last page visited</th>
                        <th class="px-5 py-3 text-right font-semibold">Total visits</th>
                        <th class="px-5 py-3 font-semibold">Last seen</th>
                        <th class="px-5 py-3 text-right font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($visitors as $v)
                        @php $log = $lastRows[$v->last_id] ?? null; @endphp
                        @if ($log)
                            @php $detailUrl = $log->client_id ? route('admin.client-activity.details', ['client' => $log->client_id]) : route('admin.client-activity.details', ['ip' => $log->ip]); @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    @if ($log->client)
                                        <a href="{{ $detailUrl }}" class="flex items-center gap-2 hover:opacity-80">
                                            @if ($log->client->photo)
                                                <img src="{{ asset('storage/'.$log->client->photo) }}" alt="" class="h-8 w-8 rounded-full border border-gray-200 object-cover">
                                            @else
                                                <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[11px] font-bold text-[var(--color-primary)]">{{ strtoupper(substr($log->client->name, 0, 1)) }}</span>
                                            @endif
                                            <span>
                                                <span class="block font-medium text-[var(--color-heading)]">{{ $log->client->name }}</span>
                                                <span class="block text-xs text-[var(--color-muted)]">{{ $log->client->email }}</span>
                                            </span>
                                        </a>
                                    @else
                                        <a href="{{ $detailUrl }}" class="flex items-center gap-2 hover:opacity-80">
                                            <span class="grid h-8 w-8 place-items-center rounded-full bg-gray-100 text-gray-400">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12a9.5 9.5 0 1 0 19 0 9.5 9.5 0 0 0-19 0Zm0 0h19M12 2.5c2.5 2.6 2.5 16.4 0 19M12 2.5c-2.5 2.6-2.5 16.4 0 19"/></svg>
                                            </span>
                                            <span>
                                                <span class="block font-medium italic text-[var(--color-muted)]">Unknown visitor</span>
                                                <span class="block text-xs text-gray-400">{{ $log->ip }}</span>
                                            </span>
                                        </a>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $log->country ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    <span class="font-medium text-[var(--color-heading)]">{{ $log->title ?: '—' }}</span>
                                    <span class="block font-mono text-xs text-[var(--color-primary)]">{{ $log->path }}</span>
                                </td>
                                <td class="px-5 py-3 text-right"><span class="inline-flex rounded-full bg-[var(--color-primary-soft)] px-2.5 py-0.5 text-xs font-bold text-[var(--color-primary)]">{{ number_format($v->visits) }}</span></td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $log->created_at?->format('d M Y, h:i A') }} <span class="block text-xs text-gray-400">{{ $log->created_at?->diffForHumans() }}</span></td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ $detailUrl }}" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-gray-50">
                                        History
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 18 6-6-6-6"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">No visits recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $visitors->links() }}</div>
    </div>{{-- /#live-region --}}

    @include('admin.client-activity._live')
@endsection
