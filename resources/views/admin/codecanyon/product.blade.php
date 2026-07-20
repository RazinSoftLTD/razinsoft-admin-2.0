@extends('admin.layouts.app')
@section('title', $product->name)

@php
    $money = fn ($v) => '$'.number_format($v, 0);
    $snaps = $product->snapshots;
    $canManage = auth()->user()->allows('codecanyon', 'manage');
@endphp

@section('content')
    <nav class="mb-2 flex items-center gap-2 text-sm text-[var(--color-muted)]">
        <a href="{{ route('admin.codecanyon.index') }}" class="hover:text-[var(--color-heading)]">CodeCanyon</a>
        <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
        <span class="truncate text-[var(--color-heading)]">{{ $product->name }}</span>
    </nav>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            @if ($product->thumbnail_url)<img src="{{ $product->thumbnail_url }}" class="h-12 w-12 rounded-xl object-cover" alt="">@endif
            <div class="min-w-0">
                <h1 class="truncate text-xl font-bold text-[var(--color-heading)]">{{ $product->name }}</h1>
                <p class="text-sm text-[var(--color-muted)]">
                    {{ $product->author_username }} · {{ $product->categoryLabel() }}
                    @if ($product->url) · <a href="{{ $product->url }}" target="_blank" rel="noopener" class="font-semibold text-[var(--color-primary)] hover:underline">Open on CodeCanyon</a>@endif
                </p>
            </div>
        </div>
        @if ($canManage)
            <form method="POST" action="{{ route('admin.codecanyon.products.destroy', $product) }}" onsubmit="return confirm('Stop tracking this product?')">
                @csrf @method('DELETE')
                <button class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Stop tracking</button>
            </form>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Sales', number_format($product->number_of_sales)],
            ['Rating', $product->rating ? number_format((float) $product->rating, 2).' / 5' : '—'],
            ['Price', '$'.number_format($product->price(), 2)],
            ['Est. revenue', $money($product->estimatedRevenue())],
        ] as [$label, $value])
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <p class="text-sm text-[var(--color-muted)]">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold text-[var(--color-heading)]">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid items-start gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-bold text-[var(--color-heading)]">Details</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-[var(--color-muted)]">Item ID</dt><dd class="font-semibold text-[var(--color-heading)]">{{ $product->item_id }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-[var(--color-muted)]">Released</dt><dd class="font-semibold text-[var(--color-heading)]">{{ $product->published_at?->format('d M Y') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-[var(--color-muted)]">Last update</dt><dd class="font-semibold text-[var(--color-heading)]">{{ $product->item_updated_at?->format('d M Y') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-[var(--color-muted)]">Reviews</dt><dd class="font-semibold text-[var(--color-heading)]">{{ number_format($product->rating_count) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-[var(--color-muted)]">Trending</dt><dd class="font-semibold text-[var(--color-heading)]">{{ $product->trending ? 'Yes' : 'No' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-[var(--color-muted)]">Last synced</dt><dd class="font-semibold text-[var(--color-heading)]">{{ $product->synced_at?->diffForHumans() ?? '—' }}</dd></div>
            </dl>

            @if ($canManage)
                <form method="POST" action="{{ route('admin.codecanyon.products.update', $product) }}" class="mt-4 flex items-center gap-2">
                    @csrf @method('PUT')
                    <select name="envato_niche_id" onchange="this.form.submit()" class="h-9 w-full rounded-lg border-gray-200 text-xs">
                        <option value="">No niche</option>
                        @foreach (\App\Models\EnvatoNiche::orderBy('name')->get() as $n)
                            <option value="{{ $n->id }}" @selected($product->envato_niche_id === $n->id)>{{ $n->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            @if ($product->tags)
                <div class="mt-4 flex flex-wrap gap-1.5">
                    @foreach (array_slice($product->tags, 0, 15) as $tag)
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500">{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm lg:col-span-2">
            <h3 class="text-sm font-bold text-[var(--color-heading)]">Sales History</h3>
            <p class="text-xs text-[var(--color-muted)]">Built from our own daily snapshots — Envato's API provides no history.</p>
            @if ($snaps->count() < 2)
                <p class="py-12 text-center text-sm text-gray-400">Not enough snapshots yet. A daily reading is taken from the first sync onward.</p>
            @else
                <div id="ccSalesChart" class="mt-2 min-w-0"></div>
            @endif
        </div>
    </div>

    @if ($snaps->count() >= 2)
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        <script>
            (function render() {
                if (!window.ApexCharts) return setTimeout(render, 200);
                const el = document.querySelector('#ccSalesChart');
                if (!el || el.dataset.done) return;
                el.dataset.done = '1';
                new ApexCharts(el, {
                    chart: { type: 'area', height: 260, width: '100%', toolbar: { show: false }, fontFamily: 'inherit', redrawOnParentResize: true },
                    series: [{ name: 'Total sales', data: @js($snaps->pluck('number_of_sales')->map(fn ($v) => (int) $v)->all()) }],
                    xaxis: { categories: @js($snaps->pluck('captured_on')->map(fn ($d) => $d->format('d M'))->all()), axisBorder: { show: false }, axisTicks: { show: false } },
                    stroke: { curve: 'smooth', width: 3 },
                    colors: ['#4f46e5'],
                    fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
                    dataLabels: { enabled: false },
                    grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
                }).render();
                requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
            })();
        </script>
    @endif
@endsection
