@extends('admin.layouts.app')
@section('title', 'Finance')

@php
    $money = fn ($n, $cur = '') => ($cur ? $cur.' ' : '').number_format((float) $n, 2);
    $sym = \App\Models\Currency::symbolMap();

    // Chart geometry, computed server-side (the panel ships no JS chart library).
    $peak = max(1, collect($monthly)->flatMap(fn ($m) => [$m['income'], $m['expense']])->max());
    $catTotal = max(0.01, collect($byCategory)->sum('total'));
    $donutColors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#94a3b8'];
@endphp

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Finance</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">RazinSoft's own money — wallets, banks, income and spending. Paid invoices arrive here automatically.</p>
    </div>

    @include('admin.finance._nav')

    {{-- Balances --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-400">Wallet Balance</p>
            @forelse ($walletTotal as $cur => $amount)
                <p class="mt-1 text-2xl font-extrabold text-[var(--color-heading)]">{{ $sym[$cur] ?? '' }}{{ number_format($amount, 2) }} <span class="text-xs font-medium text-gray-400">{{ $cur }}</span></p>
            @empty
                <p class="mt-1 text-2xl font-extrabold text-gray-300">—</p>
            @endforelse
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-400">Bank Balance</p>
            @forelse ($bankTotal as $cur => $amount)
                <p class="mt-1 text-2xl font-extrabold text-[var(--color-heading)]">{{ $sym[$cur] ?? '' }}{{ number_format($amount, 2) }} <span class="text-xs font-medium text-gray-400">{{ $cur }}</span></p>
            @empty
                <p class="mt-1 text-2xl font-extrabold text-gray-300">—</p>
            @endforelse
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-400">Today</p>
            <p class="mt-1 text-lg font-bold text-emerald-600">+ {{ $money($todayIncome) }}</p>
            <p class="text-lg font-bold text-red-600">− {{ $money($todayExpense) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-400">This Month</p>
            <p class="mt-1 text-sm font-semibold text-emerald-600">Income {{ $money($monthIncome) }}</p>
            <p class="text-sm font-semibold text-red-600">Expense {{ $money($monthExpense) }}</p>
            <p class="mt-1 border-t border-gray-50 pt-1 text-lg font-extrabold {{ $monthProfit >= 0 ? 'text-[var(--color-heading)]' : 'text-red-600' }}">Profit {{ $money($monthProfit) }}</p>
        </div>
    </div>

    {{-- Outstanding --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <a href="{{ route('admin.finance.receivables') }}" class="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-[var(--color-primary)]">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400">Outstanding Client Due</p>
                <p class="mt-1 text-2xl font-extrabold text-amber-600">{{ $money($clientDue) }}</p>
                <p class="text-xs text-[var(--color-muted)]">From unpaid invoices</p>
            </div>
            <svg class="h-5 w-5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
        </a>
        <a href="{{ route('admin.finance.payables') }}" class="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-[var(--color-primary)]">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400">Outstanding Vendor Due</p>
                <p class="mt-1 text-2xl font-extrabold text-red-600">{{ $money($vendorDue) }}</p>
                <p class="text-xs text-[var(--color-muted)]">Bills we still owe</p>
            </div>
            <svg class="h-5 w-5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
        </a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Income vs expense, last 12 months --}}
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Income vs Expense</h2>
                <p class="flex items-center gap-3 text-xs text-[var(--color-muted)]">
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Income</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-500"></span>Expense</span>
                </p>
            </div>
            <div class="flex items-end gap-2" style="height:12rem">
                @foreach ($monthly as $m)
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <div class="flex w-full items-end justify-center gap-0.5" style="height:10rem">
                            <span class="w-full rounded-t bg-emerald-500" style="height: {{ max(1, round($m['income'] / $peak * 100)) }}%" title="Income {{ number_format($m['income'], 2) }}"></span>
                            <span class="w-full rounded-t bg-red-500" style="height: {{ max(1, round($m['expense'] / $peak * 100)) }}%" title="Expense {{ number_format($m['expense'], 2) }}"></span>
                        </div>
                        <span class="text-[10px] text-gray-400">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Expense split for the month --}}
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Expense Categories</h2>
            @forelse (array_slice($byCategory, 0, 8) as $i => $cat)
                @php $pct = round($cat['total'] / $catTotal * 100); @endphp
                <div class="mb-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="truncate text-[var(--color-heading)]">{{ $cat['name'] }}</span>
                        <span class="shrink-0 font-semibold text-[var(--color-muted)]">{{ $money($cat['total']) }} · {{ $pct }}%</span>
                    </div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full" style="width: {{ max(2, $pct) }}%; background: {{ $donutColors[$i % count($donutColors)] }}"></div>
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-gray-300">No expenses this month.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Cash flow (net per month) --}}
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm lg:col-span-2">
            <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Cash Flow</h2>
            <div class="flex items-center gap-2" style="height:8rem">
                @foreach ($monthly as $m)
                    @php $h = max(2, round(abs($m['net']) / $peak * 50)); @endphp
                    <div class="flex flex-1 flex-col items-center">
                        <div class="flex w-full items-end justify-center" style="height:3rem">
                            @if ($m['net'] >= 0)<span class="w-full rounded-t bg-[var(--color-primary)]" style="height: {{ $h }}%" title="{{ number_format($m['net'], 2) }}"></span>@endif
                        </div>
                        <span class="w-full bg-gray-200" style="height:1px"></span>
                        <div class="flex w-full items-start justify-center" style="height:3rem">
                            @if ($m['net'] < 0)<span class="w-full rounded-b bg-red-500" style="height: {{ $h }}%" title="{{ number_format($m['net'], 2) }}"></span>@endif
                        </div>
                        <span class="text-[10px] text-gray-400">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Where the money sits --}}
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Wallet Distribution</h2>
            @php $balTotal = max(0.01, $accounts->sum(fn ($a) => abs((float) $a->current_balance))); @endphp
            @forelse ($accounts->sortByDesc('current_balance')->take(8) as $i => $acc)
                @php $pct = round(abs((float) $acc->current_balance) / $balTotal * 100); @endphp
                <div class="mb-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="truncate text-[var(--color-heading)]">{{ $acc->name }}</span>
                        <span class="shrink-0 font-semibold text-[var(--color-muted)]">{{ $acc->symbol() }}{{ number_format((float) $acc->current_balance, 2) }}</span>
                    </div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full" style="width: {{ max(2, $pct) }}%; background: {{ $donutColors[$i % count($donutColors)] }}"></div>
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-gray-300">
                    No accounts yet — <a href="{{ route('admin.finance.wallets') }}" class="font-semibold text-[var(--color-primary)] hover:underline">add a wallet</a>.
                </p>
            @endforelse
        </div>
    </div>

    {{-- Recent movement --}}
    <div class="mt-6 rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Recent Transactions</h2>
            <a href="{{ route('admin.finance.transactions') }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">View all</a>
        </div>
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-2.5 font-semibold">Date</th>
                    <th class="px-5 py-2.5 font-semibold">Type</th>
                    <th class="px-5 py-2.5 font-semibold">Account</th>
                    <th class="px-5 py-2.5 font-semibold">Reference</th>
                    <th class="px-5 py-2.5 text-right font-semibold">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($recent as $t)
                    <tr>
                        <td class="px-5 py-2.5 text-[var(--color-muted)]">{{ $t->occurred_on?->format('d M Y') }}</td>
                        <td class="px-5 py-2.5">
                            <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $t->direction === 'in' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">{{ $t->typeLabel() }}</span>
                            @if ($t->isAutomatic())<span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500">auto</span>@endif
                        </td>
                        <td class="px-5 py-2.5 text-[var(--color-muted)]">{{ $t->account?->name ?? 'Unassigned' }}</td>
                        <td class="px-5 py-2.5 text-[var(--color-muted)]">{{ $t->reference ?: '—' }}</td>
                        <td class="px-5 py-2.5 text-right font-semibold {{ $t->direction === 'in' ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $t->direction === 'in' ? '+' : '−' }} {{ $t->symbol() }}{{ number_format((float) $t->amount, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-300">Nothing recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
