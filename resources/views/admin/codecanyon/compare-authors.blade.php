@extends('admin.layouts.app')
@section('title', 'Author Compare')

@php $money = fn ($v) => '$'.number_format($v, 0); @endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Author Compare</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                Who sold how much, day by day, and which product carried it —
                {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
                @foreach ($ranges as $value => $label)
                    <a href="{{ route('admin.codecanyon.compare-authors', ['days' => $value]) }}"
                       class="rounded-md px-3 py-1.5 font-semibold transition {{ $days === $value ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            @if ($canManage && $sync['configured'])
                <form method="POST" action="{{ route('admin.codecanyon.compare-sync') }}">
                    @csrf
                    <button @disabled($sync['active'])
                            class="inline-flex h-10 items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] disabled:cursor-not-allowed disabled:opacity-60">
                        <svg class="h-4 w-4 {{ $sync['active'] ? 'animate-spin' : '' }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12a8 8 0 1 1-2.3-5.7M20 4v4h-4"/></svg>
                        {{ $sync['active'] ? 'Syncing…' : 'Sync now' }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if (session('status'))<div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    {{-- ===== Sync ===== --}}
    @php
        $tone = match (true) {
            ! $sync['configured'], $sync['stalled'], $sync['last']?->status === 'failed' => ['border-red-200', 'bg-red-50', 'text-red-700'],
            $sync['active'] => ['border-sky-200', 'bg-sky-50', 'text-sky-700'],
            $sync['captured_today'] => ['border-emerald-200', 'bg-emerald-50', 'text-emerald-700'],
            default => ['border-amber-200', 'bg-amber-50', 'text-amber-800'],
        };
    @endphp
    <div class="rounded-2xl border {{ $tone[0] }} {{ $tone[1] }} p-5"
         @if ($sync['active'])
             {{-- Reload once the run finishes, so the freshly captured day appears without a manual refresh. --}}
             x-data="{
                 poll() {
                     fetch('{{ route('admin.codecanyon.sync-status', ['days' => $days]) }}', { headers: { 'Accept': 'application/json' } })
                         .then(r => r.json())
                         .then(s => s.active ? setTimeout(() => this.poll(), 4000) : window.location.reload())
                         .catch(() => setTimeout(() => this.poll(), 8000));
                 }
             }" x-init="setTimeout(() => poll(), 4000)"
         @endif>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold {{ $tone[2] }}">{{ $sync['message'] }}</p>
                <p class="mt-1 text-xs text-[var(--color-muted)]">
                    Last sync:
                    <strong>{{ $sync['last_synced'] ? $sync['last_synced']->diffForHumans() : 'never' }}</strong>
                    · Auto-sync: <strong>{{ $sync['auto'] ? 'on, daily at 04:00' : 'off' }}</strong>
                    · Coverage in this window: <strong>{{ $sync['covered'] }} of {{ $sync['total_days'] }} days</strong>
                </p>
                @if ($sync['stalled'])
                    <p class="mt-2 text-xs text-red-700">
                        Start a worker with <code class="rounded bg-white/70 px-1 py-0.5">php artisan queue:work</code>,
                        or run <code class="rounded bg-white/70 px-1 py-0.5">php artisan codecanyon:sync --now --force</code> directly.
                    </p>
                @endif
                @if (! $sync['configured'])
                    <p class="mt-2 text-xs">
                        <a href="{{ route('admin.codecanyon-settings') }}" class="font-semibold underline">Settings → CodeCanyon Config</a>
                    </p>
                @endif
            </div>
            @if ($sync['missing'])
                <div class="text-right">
                    <p class="text-xs font-semibold text-[var(--color-muted)]">Days with no snapshot</p>
                    <p class="mt-1 max-w-xs text-xs text-[var(--color-muted)]">
                        {{ collect($sync['missing'])->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M'))->join(', ') }}
                    </p>
                    <p class="mt-1 text-[11px] text-[var(--color-muted)]">Envato serves only today's numbers, so past gaps cannot be filled in.</p>
                </div>
            @endif
        </div>
    </div>

    @if ($rows->isEmpty())
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            <p class="font-semibold">No authors on the watchlist yet.</p>
            <p class="mt-1">Add the authors you want to track on
                <a href="{{ route('admin.codecanyon.index') }}" class="font-semibold underline">CodeCanyon &rsaquo; Overview</a>, then run a sync.</p>
        </div>
    @elseif (! $hasHistory)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            <p class="font-semibold">Only one day of history so far.</p>
            <p class="mt-1">Daily sales are the difference between two syncs, so this page fills in from the second sync onwards
                (the scheduler runs at 04:00 daily). The totals below are lifetime figures and are already accurate.</p>
        </div>
    @endif

    {{-- ===== Standings ===== --}}
    <div class="mt-6 rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-lg font-bold text-[var(--color-heading)]">Standings</h3>
            <p class="text-xs text-[var(--color-muted)]">
                Ranked by sales inside the selected window. <strong>Today</strong> updates on every sync — press <em>Sync now</em> for the latest.
                The column beside it names what sold today; with no sales yet it falls back to the window's best seller.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">#</th>
                        <th class="px-5 py-3 font-semibold">Author</th>
                        <th class="px-5 py-3 font-semibold text-right">Today</th>
                        <th class="px-5 py-3 font-semibold text-right">Sold ({{ $days }}d)</th>
                        <th class="px-5 py-3 font-semibold">What sold today</th>
                        <th class="px-5 py-3 font-semibold text-right">Products</th>
                        <th class="px-5 py-3 font-semibold text-right">Portfolio sales</th>
                        <th class="px-5 py-3 font-semibold text-right">Est. revenue</th>
                        <th class="px-5 py-3 font-semibold text-right">Synced</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $i => $row)
                        <tr class="{{ $row['author']->is_own ? 'bg-indigo-50/40' : '' }}">
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $i + 1 }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.codecanyon.author', $row['author']) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">
                                    {{ $row['author']->username }}
                                </a>
                                @if ($row['author']->is_own)
                                    <span class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-600">Us</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($row['today'] > 0)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-bold text-emerald-700">+{{ number_format($row['today']) }}</span>
                                @else
                                    <span class="text-gray-300">0</span>
                                @endif
                                @if ($row['today_partial'])
                                    <span class="block text-[11px] text-[var(--color-muted)]" title="No reading from yesterday to measure against, so this counts from today's first sync">since first sync</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right text-lg font-bold text-[var(--color-heading)]">{{ number_format($row['sold']) }}</td>
                            <td class="px-5 py-3">
                                @if ($row['today_products']->isNotEmpty())
                                    {{-- What actually moved today beats the window's best seller when there is news. --}}
                                    @foreach ($row['today_products']->take(2) as $t)
                                        <div class="truncate">
                                            <a href="{{ route('admin.codecanyon.product', $t['product']) }}" class="text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $t['product']->name }}</a>
                                            <span class="font-semibold text-emerald-700">+{{ $t['sold'] }}</span>
                                        </div>
                                    @endforeach
                                    @if ($row['today_products']->count() > 2)
                                        <span class="text-xs text-[var(--color-muted)]">+{{ $row['today_products']->count() - 2 }} more today</span>
                                    @endif
                                @elseif ($row['top'] && $row['top_sold'])
                                    <a href="{{ route('admin.codecanyon.product', $row['top']) }}" class="text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $row['top']->name }}</a>
                                    <span class="text-[var(--color-muted)]">— {{ $row['top_sold'] }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">{{ $row['products'] }}</td>
                            <td class="px-5 py-3 text-right">
                                {{ number_format($row['lifetime']) }}
                                @if ($row['profile_sales'] !== $row['lifetime'])
                                    <span class="block text-xs text-[var(--color-muted)]" title="Envato's profile total — includes retired items and their other marketplaces">
                                        profile: {{ number_format($row['profile_sales']) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">{{ $money($row['revenue']) }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <span class="text-xs text-[var(--color-muted)]">{{ $row['author']->synced_at ? $row['author']->synced_at->diffForHumans(short: true) : 'never' }}</span>
                                @if ($canManage && $sync['configured'])
                                    <form method="POST" action="{{ route('admin.codecanyon.compare-sync') }}" class="ml-2 inline">
                                        @csrf
                                        <input type="hidden" name="author" value="{{ $row['author']->id }}">
                                        <button @disabled($sync['active']) title="Refresh just this author"
                                                class="rounded-lg border border-gray-200 px-2 py-1 text-xs font-semibold text-[var(--color-heading)] hover:bg-gray-50 disabled:opacity-50">
                                            Refresh
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-gray-100 px-5 py-3 text-xs text-[var(--color-muted)]">
            <strong>Portfolio sales</strong> adds up the items currently listed on CodeCanyon.
            <strong>Profile</strong> is Envato's own total for the account — it also counts retired items and their other marketplaces, so the two rarely match.
            Revenue is <strong>estimated</strong> (sales × current price) — the API never exposes real earnings.
        </p>
    </div>

    {{-- ===== Every date, per author ===== --}}
    <div class="mt-6 rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-lg font-bold text-[var(--color-heading)]">Sales by date</h3>
            <p class="text-xs text-[var(--color-muted)]">A dot means no sync ran that day — not a day without sales.</p>
        </div>
        @include('admin.codecanyon.partials.matrix', [
            'dates' => $dates,
            'empty' => 'No daily history yet. Come back after the next sync.',
            'series' => $rows->map(fn ($r) => [
                'label' => $r['author']->username,
                'sub' => $r['products'].' products tracked',
                'href' => route('admin.codecanyon.author', $r['author']),
                'daily' => $r['daily'],
            ])->all(),
        ])
    </div>

    {{-- ===== Which product sold how many, per author ===== --}}
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        @foreach ($rows as $row)
            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-sm font-bold text-[var(--color-heading)]">{{ $row['author']->username }}</h3>
                    <span class="text-xs text-[var(--color-muted)]">{{ number_format($row['sold']) }} sold in {{ $days }}d</span>
                </div>
                @if (! $row['per_product'] || ! $row['sold'])
                    <p class="px-5 py-6 text-center text-sm text-[var(--color-muted)]">No sales recorded in this window.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($row['per_product'] as $productId => $sold)
                            @php $product = $row['author']->products->firstWhere('id', $productId); @endphp
                            @continue(! $product || ! $sold)
                            <li class="px-5 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <a href="{{ route('admin.codecanyon.product', $product) }}" class="min-w-0 truncate text-sm text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $product->name }}</a>
                                    <span class="shrink-0 text-sm font-bold text-[var(--color-heading)]">{{ $sold }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full bg-[var(--color-primary)]" style="width: {{ round($sold / max(1, $row['sold']) * 100) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ===== Sync history ===== --}}
    <div class="mt-6 rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-lg font-bold text-[var(--color-heading)]">Sync history</h3>
            <p class="text-xs text-[var(--color-muted)]">Every attempt, so a gap in the grid above always has an explanation.</p>
        </div>
        @if ($runs->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-[var(--color-muted)]">No sync has run yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">When</th>
                            <th class="px-5 py-3 font-semibold">Trigger</th>
                            <th class="px-5 py-3 font-semibold">Scope</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Products</th>
                            <th class="px-5 py-3 text-right font-semibold" title="Days captured for the first time — later runs the same day update that row instead">New days</th>
                            <th class="px-5 py-3 text-right font-semibold">Took</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($runs as $run)
                            <tr>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    {{ $run->created_at->format('d M, H:i') }}
                                    @if ($run->triggeredBy)<span class="block text-xs text-[var(--color-muted)]">by {{ $run->triggeredBy->name }}</span>@endif
                                </td>
                                <td class="px-5 py-3">{{ $run->label() }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $run->author?->username ?: 'Whole watchlist' }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $badge = match ($run->status) {
                                            'success' => 'bg-emerald-50 text-emerald-700',
                                            'failed' => 'bg-red-50 text-red-700',
                                            'running' => 'bg-sky-50 text-sky-700',
                                            default => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge }}">{{ ucfirst($run->status) }}</span>
                                    @if ($run->error)<p class="mt-1 max-w-md text-xs text-red-600">{{ $run->error }}</p>@endif
                                </td>
                                <td class="px-5 py-3 text-right">{{ $run->products_synced }}</td>
                                <td class="px-5 py-3 text-right">{{ $run->snapshots_written }}</td>
                                <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ $run->durationForHumans() ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
