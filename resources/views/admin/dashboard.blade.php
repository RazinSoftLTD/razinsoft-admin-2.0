@extends('admin.layouts.app')
@section('title', 'Analytics')

@php
    $kpiMeta = [
        'revenue'   => ['label' => 'Total Revenue', 'display' => '$' . number_format($kpis['revenue']['value'], 2), 'icon' => 'M12 3v18M16 7H10a2.5 2.5 0 0 0 0 5h4a2.5 2.5 0 0 1 0 5H8'],
        'orders'    => ['label' => 'Orders', 'display' => number_format($kpis['orders']['value']), 'icon' => 'M3 7h18l-1.4 12a2 2 0 0 1-2 1.8H6.4a2 2 0 0 1-2-1.8L3 7Z'],
        'customers' => ['label' => 'Customers', 'display' => number_format($kpis['customers']['value']), 'icon' => 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z'],
        'products'  => ['label' => 'Products', 'display' => number_format($kpis['products']['value']), 'icon' => 'M3 7l9-4 9 4-9 4-9-4Zm0 0v10l9 4 9-4V7'],
    ];
    $statusColors = ['completed' => '#22c55e', 'paid' => '#465fff', 'processing' => '#465fff', 'pending' => '#f59e0b', 'refunded' => '#94a3b8', 'cancelled' => '#ef4444'];
@endphp

@section('content')
    {{-- KPI cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpiMeta as $key => $m)
            @php $chg = $kpis[$key]['change']; $up = $chg >= 0; @endphp
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <span class="grid h-12 w-12 place-items-center rounded-xl bg-gray-100 text-gray-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $m['icon'] }}"/></svg>
                </span>
                <div class="mt-4 flex items-end justify-between">
                    <div>
                        <p class="text-sm text-gray-500">{{ $m['label'] }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800">{{ $m['display'] }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold {{ $up ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600' }}">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $up ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6' }}"/></svg>
                        {{ abs($chg) }}%
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Revenue area chart + activity --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 lg:col-span-2">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-800">Revenue Overview</h3>
                    <p class="text-sm text-gray-500">Paid orders · last 12 months</p>
                </div>
                <span class="rounded-lg bg-[var(--color-brand-soft)] px-3 py-1 text-xs font-semibold text-[var(--color-brand)]">12 months</span>
            </div>
            <div id="revChart" class="mt-4 -ml-2"></div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            <h3 class="font-semibold text-gray-800">Order Activity</h3>
            <div class="mt-4 grid grid-cols-2 gap-4">
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'pending' => 'Pending'] as $k => $lbl)
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <p class="text-2xl font-bold text-gray-800">{{ number_format($active[$k]) }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $lbl }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Orders bar + status donut --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 lg:col-span-2">
            <h3 class="font-semibold text-gray-800">Orders per Month</h3>
            <div id="ordChart" class="mt-4 -ml-2"></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            <h3 class="font-semibold text-gray-800">Orders by Status</h3>
            @if ($statuses->sum() > 0)
                <div id="statusChart" class="mt-2"></div>
                <ul class="mt-4 space-y-2">
                    @foreach ($statuses as $st => $c)
                        <li class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 capitalize text-gray-600"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $statusColors[$st] ?? '#94a3b8' }}"></span>{{ $st }}</span>
                            <span class="font-semibold text-gray-800">{{ $c }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="py-16 text-center text-sm text-gray-400">No orders yet.</p>
            @endif
        </div>
    </div>

    {{-- Recent orders + top products --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white lg:col-span-2">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h3 class="font-semibold text-gray-800">Recent Orders</h3>
                <a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-[var(--color-brand)] hover:underline">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-gray-400">
                        <tr><th class="px-5 py-3 font-medium">Order</th><th class="px-5 py-3 font-medium">Customer</th><th class="px-5 py-3 font-medium">Status</th><th class="px-5 py-3 text-right font-medium">Value</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($recentOrders as $o)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3"><a href="{{ route('admin.orders.show', $o) }}" class="font-semibold text-[var(--color-brand)] hover:underline">{{ $o->order_number }}</a><p class="text-xs text-gray-400">{{ $o->items->pluck('product_name')->join(', ') }}</p></td>
                                <td class="px-5 py-3 text-gray-600">{{ $o->user?->name ?? '—' }}</td>
                                <td class="px-5 py-3"><x-admin.status :status="$o->status" /></td>
                                <td class="px-5 py-3 text-right font-semibold text-gray-800">${{ number_format($o->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-gray-400">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            <h3 class="font-semibold text-gray-800">Top Products</h3>
            <ul class="mt-4 space-y-4">
                @forelse ($topProducts as $tp)
                    <li class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-800">{{ $tp->product_name }}</p>
                            <p class="text-xs text-gray-400">{{ $tp->qty }} sold</p>
                        </div>
                        <span class="shrink-0 text-sm font-bold text-gray-800">${{ number_format($tp->revenue, 0) }}</span>
                    </li>
                @empty
                    <li class="py-10 text-center text-sm text-gray-400">No sales yet.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const series = @json($series);
            const brand = '#465fff';
            const baseOpts = { chart: { fontFamily: 'inherit', toolbar: { show: false } }, grid: { borderColor: '#f1f5f9', strokeDashArray: 4 }, dataLabels: { enabled: false }, xaxis: { categories: series.labels, axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#9ca3af' } } }, yaxis: { labels: { style: { colors: '#9ca3af' } } }, tooltip: { theme: 'light' } };

            if (window.ApexCharts) {
                new ApexCharts(document.querySelector('#revChart'), {
                    ...baseOpts, chart: { ...baseOpts.chart, type: 'area', height: 300 }, colors: [brand], stroke: { curve: 'smooth', width: 2 },
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
                    series: [{ name: 'Revenue', data: series.revenue }],
                    yaxis: { labels: { formatter: v => '$' + Math.round(v), style: { colors: '#9ca3af' } } },
                }).render();

                new ApexCharts(document.querySelector('#ordChart'), {
                    ...baseOpts, chart: { ...baseOpts.chart, type: 'bar', height: 280 }, colors: [brand],
                    plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
                    series: [{ name: 'Orders', data: series.orders }],
                }).render();

                const el = document.querySelector('#statusChart');
                if (el) {
                    const st = @json($statuses);
                    const colorMap = @json($statusColors);
                    const labels = Object.keys(st);
                    new ApexCharts(el, {
                        chart: { type: 'donut', height: 240, fontFamily: 'inherit' },
                        labels, series: Object.values(st).map(Number),
                        colors: labels.map(l => colorMap[l] || '#94a3b8'),
                        legend: { show: false }, dataLabels: { enabled: false },
                        plotOptions: { pie: { donut: { size: '70%' } } },
                        stroke: { width: 0 },
                    }).render();
                }
            }
        });
    </script>
@endsection
