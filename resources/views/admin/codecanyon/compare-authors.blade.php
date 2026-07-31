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
        <div class="flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
            @foreach ($ranges as $value => $label)
                <a href="{{ route('admin.codecanyon.compare-authors', ['days' => $value]) }}"
                   class="rounded-md px-3 py-1.5 font-semibold transition {{ $days === $value ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
                    {{ $label }}
                </a>
            @endforeach
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
            <p class="text-xs text-[var(--color-muted)]">Ranked by sales inside the selected window.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">#</th>
                        <th class="px-5 py-3 font-semibold">Author</th>
                        <th class="px-5 py-3 font-semibold text-right">Sold ({{ $days }}d)</th>
                        <th class="px-5 py-3 font-semibold">Best seller in window</th>
                        <th class="px-5 py-3 font-semibold text-right">Products</th>
                        <th class="px-5 py-3 font-semibold text-right">Lifetime sales</th>
                        <th class="px-5 py-3 font-semibold text-right">Est. revenue</th>
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
                            <td class="px-5 py-3 text-right text-lg font-bold text-[var(--color-heading)]">{{ number_format($row['sold']) }}</td>
                            <td class="px-5 py-3">
                                @if ($row['top'] && $row['top_sold'])
                                    <a href="{{ route('admin.codecanyon.product', $row['top']) }}" class="text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $row['top']->name }}</a>
                                    <span class="text-[var(--color-muted)]">— {{ $row['top_sold'] }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">{{ $row['products'] }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($row['lifetime']) }}</td>
                            <td class="px-5 py-3 text-right">{{ $money($row['revenue']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-gray-100 px-5 py-3 text-xs text-[var(--color-muted)]">
            Revenue is <strong>estimated</strong> (sales × current price) — the Envato API never exposes another author's real earnings.
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
@endsection
