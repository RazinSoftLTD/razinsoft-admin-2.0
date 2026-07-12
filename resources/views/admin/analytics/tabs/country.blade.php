@php
    $cards = [
        ['label' => 'Countries', 'value' => $totals['countries'], 'tone' => 'text-[var(--color-heading)]'],
        ['label' => 'New', 'value' => $totals['new'], 'tone' => 'text-blue-700'],
        ['label' => 'Qualified', 'value' => $totals['qualified'], 'tone' => 'text-emerald-700'],
        ['label' => 'Unqualified', 'value' => $totals['unqualified'], 'tone' => 'text-red-600'],
        ['label' => 'Won', 'value' => $totals['won'], 'tone' => 'text-violet-700'],
        ['label' => 'Total Leads', 'value' => $totals['total'], 'tone' => 'text-[var(--color-heading)]'],
    ];
@endphp

<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    @foreach ($cards as $c)
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm"><p class="text-xs font-medium text-[var(--color-muted)]">{{ $c['label'] }}</p><p class="mt-1 text-2xl font-bold {{ $c['tone'] }}">{{ number_format($c['value']) }}</p></div>
    @endforeach
</div>

<div class="rounded-xl border border-gray-100 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
        <h2 class="text-sm font-bold text-[var(--color-heading)]">Country Breakdown</h2>
        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500">{{ $totals['countries'] }} countries</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                    <th class="px-5 py-3 font-semibold">Country</th>
                    <th class="px-4 py-3 text-center font-semibold">New</th>
                    <th class="px-4 py-3 text-center font-semibold">Qualified</th>
                    <th class="px-4 py-3 text-center font-semibold">Unqualified</th>
                    <th class="px-4 py-3 text-center font-semibold">Won</th>
                    <th class="px-4 py-3 text-center font-semibold">Total</th>
                    <th class="px-5 py-3 font-semibold">Share</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($breakdown as $row)
                    @php $share = $totals['total'] ? round($row->total / $totals['total'] * 100) : 0; @endphp
                    <tr class="hover:bg-gray-50/60">
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.leads.index', array_filter(['country' => $row->country === 'Unknown' ? null : $row->country, 'date_range' => request('date_range'), 'from' => request('from'), 'to' => request('to')])) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $row->country }}</a>
                        </td>
                        <td class="px-4 py-3 text-center"><span class="inline-flex min-w-[2rem] justify-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">{{ $row->new }}</span></td>
                        <td class="px-4 py-3 text-center"><span class="inline-flex min-w-[2rem] justify-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ $row->qualified }}</span></td>
                        <td class="px-4 py-3 text-center"><span class="inline-flex min-w-[2rem] justify-center rounded-md bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600">{{ $row->unqualified }}</span></td>
                        <td class="px-4 py-3 text-center"><span class="inline-flex min-w-[2rem] justify-center rounded-md bg-violet-50 px-2 py-0.5 text-xs font-semibold text-violet-700">{{ $row->won }}</span></td>
                        <td class="px-4 py-3 text-center font-bold text-[var(--color-heading)]">{{ $row->total }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-[var(--color-primary)]" style="width: {{ $share }}%"></div></div>
                                <span class="text-xs font-medium text-gray-400">{{ $share }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-gray-300">No leads in this period.</td></tr>
                @endforelse
            </tbody>
            @if ($breakdown->isNotEmpty())
                <tfoot>
                    <tr class="border-t border-gray-100 bg-gray-50/60 font-bold text-[var(--color-heading)]">
                        <td class="px-5 py-3">All Countries</td>
                        <td class="px-4 py-3 text-center text-blue-700">{{ $totals['new'] }}</td>
                        <td class="px-4 py-3 text-center text-emerald-700">{{ $totals['qualified'] }}</td>
                        <td class="px-4 py-3 text-center text-red-600">{{ $totals['unqualified'] }}</td>
                        <td class="px-4 py-3 text-center text-violet-700">{{ $totals['won'] }}</td>
                        <td class="px-4 py-3 text-center">{{ $totals['total'] }}</td>
                        <td class="px-5 py-3 text-xs text-gray-400">100%</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
