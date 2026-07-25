@extends('admin.layouts.app')
@section('title', 'Receivables')

@php $sym = \App\Models\Currency::symbolMap(); @endphp

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Receivables</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">What clients still owe us — read straight from the Invoice module, so it is always in step.</p>
    </div>

    @include('admin.finance._nav')

    @if (count($totals))
        <div class="mb-5 flex flex-wrap gap-3">
            @foreach ($totals as $cur => $due)
                <div class="rounded-xl border border-gray-100 bg-white px-5 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-400">{{ $cur }} outstanding</p>
                    <p class="text-xl font-extrabold text-amber-600">{{ $sym[$cur] ?? '' }}{{ number_format((float) $due, 2) }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Invoice or client…" class="h-10 w-56 rounded-lg border-gray-200 text-sm">
        <select name="currency" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="">All currencies</option>
            @foreach ($currencies as $c)<option value="{{ $c }}" @selected(request('currency') === $c)>{{ $c }}</option>@endforeach
        </select>
        <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Filter</button>
        @if (request()->hasAny(['search', 'currency']))
            <a href="{{ route('admin.finance.receivables') }}" class="text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">Clear</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm" style="min-width:860px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Invoice</th>
                    <th class="px-5 py-3 font-semibold">Client</th>
                    <th class="px-5 py-3 font-semibold">Due date</th>
                    <th class="px-5 py-3 text-right font-semibold">Total</th>
                    <th class="px-5 py-3 text-right font-semibold">Paid</th>
                    <th class="px-5 py-3 text-right font-semibold">Due</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $inv)
                    @php
                        $due = (float) $inv->total - (float) $inv->amount_paid;
                        $overdue = $inv->due_date && $inv->due_date->isPast();
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.invoices.show', $inv) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $inv->invoice_number }}</a>
                        </td>
                        <td class="px-5 py-3 text-[var(--color-heading)]">{{ $inv->client?->name ?? $inv->bill_to_name ?? '—' }}</td>
                        <td class="px-5 py-3 {{ $overdue ? 'font-semibold text-red-600' : 'text-[var(--color-muted)]' }}">
                            {{ $inv->due_date?->format('d M Y') ?? '—' }}{{ $overdue ? ' · overdue' : '' }}
                        </td>
                        <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ $sym[$inv->currency] ?? '' }}{{ number_format((float) $inv->total, 2) }}</td>
                        <td class="px-5 py-3 text-right text-emerald-600">{{ $sym[$inv->currency] ?? '' }}{{ number_format((float) $inv->amount_paid, 2) }}</td>
                        <td class="px-5 py-3 text-right font-bold text-amber-600">{{ $sym[$inv->currency] ?? '' }}{{ number_format($due, 2) }}</td>
                        <td class="px-5 py-3"><x-admin.status :status="$inv->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-gray-300">Nothing outstanding — every invoice is settled.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
