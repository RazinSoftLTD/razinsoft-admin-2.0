@extends('admin.layouts.app')
@section('title', 'Finance Reports')

@php
    $reports = [
        'income' => 'Income Report',
        'expense' => 'Expense Report',
        'profit' => 'Profit Report',
        'cash_flow' => 'Cash Flow Report',
        'wallet' => 'Wallet Report',
        'bank' => 'Bank Report',
        'receivable' => 'Receivable Report',
        'payable' => 'Payable Report',
        'vat' => 'VAT Report',
        'tax' => 'Tax Report',
    ];
    $query = fn (array $extra = []) => route('admin.finance.reports', array_merge(['report' => $report, 'from' => $from, 'to' => $to], $extra));
@endphp

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Reports</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Pick a report and a date range, then export it as PDF, Excel or CSV.</p>
    </div>

    @include('admin.finance._nav')

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-400">Report</label>
            <select name="report" class="h-10 rounded-lg border-gray-200 text-sm">
                @foreach ($reports as $k => $label)<option value="{{ $k }}" @selected($report === $k)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-400">From</label>
            <input type="date" name="from" value="{{ $from }}" class="h-10 rounded-lg border-gray-200 text-sm">
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-400">To</label>
            <input type="date" name="to" value="{{ $to }}" class="h-10 rounded-lg border-gray-200 text-sm">
        </div>
        <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Run</button>

        <div class="ml-auto flex items-center gap-2">
            <a href="{{ $query(['export' => 'pdf']) }}" target="_blank" rel="noopener" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">PDF</a>
            <a href="{{ $query(['export' => 'excel']) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Excel</a>
            <a href="{{ $query(['export' => 'csv']) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">CSV</a>
        </div>
    </form>

    @if (count($summary))
        <div class="mb-4 flex flex-wrap gap-3">
            @foreach ($summary as $line)
                <div class="rounded-xl border border-gray-100 bg-white px-5 py-3 shadow-sm">
                    <p class="text-lg font-extrabold text-[var(--color-heading)]">{{ $line }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>@foreach ($columns as $col)<th class="px-5 py-3 font-semibold">{{ $col }}</th>@endforeach</tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-gray-50">
                        @foreach ($row as $i => $cell)
                            <td class="px-5 py-2.5 {{ $i === 0 ? 'font-medium text-[var(--color-heading)]' : 'text-[var(--color-muted)]' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ max(1, count($columns)) }}" class="px-5 py-12 text-center text-gray-300">Nothing in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
