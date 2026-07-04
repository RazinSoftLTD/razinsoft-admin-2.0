@extends('admin.layouts.app')
@section('title', 'Invoices')

@php
    $statusBadge = [
        'draft' => 'bg-gray-100 text-gray-600', 'sent' => 'bg-blue-50 text-blue-700',
        'partially_paid' => 'bg-amber-50 text-amber-700', 'paid' => 'bg-emerald-50 text-emerald-700', 'overdue' => 'bg-red-50 text-red-600',
    ];
    $cur = \App\Models\Currency::symbolMap();
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">All Invoices</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">{{ $invoices->total() }} invoice(s)</p>
        </div>
        <a href="{{ route('admin.invoices.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Create Invoice
        </a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="search" value="{{ request('search') }}" placeholder="Search invoice #, client, company…" class="h-10 w-full max-w-sm rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
        <select name="status" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm">
            <option value="">All Status</option>
            @foreach (\App\Models\ClientInvoice::STATUSES as $k => $label)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>@endforeach
        </select>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Invoice #</th>
                        <th class="px-5 py-3 font-semibold">Client</th>
                        <th class="px-5 py-3 font-semibold">Date</th>
                        <th class="px-5 py-3 font-semibold">Due Date</th>
                        <th class="px-5 py-3 text-right font-semibold">Total</th>
                        <th class="px-5 py-3 text-right font-semibold">Due</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($invoices as $inv)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $inv->invoice_number }}</td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-[var(--color-heading)]">{{ $inv->bill_to_name ?: '—' }}</p>
                                @if ($inv->bill_to_company)<p class="text-xs text-[var(--color-muted)]">{{ $inv->bill_to_company }}</p>@endif
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $inv->invoice_date->format('d M Y') }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $inv->due_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-5 py-3 text-right font-medium text-[var(--color-heading)]">{{ $cur[$inv->currency] ?? '' }}{{ number_format($inv->total, 2) }}</td>
                            <td class="px-5 py-3 text-right font-semibold {{ $inv->amountDue() > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $cur[$inv->currency] ?? '' }}{{ number_format($inv->amountDue(), 2) }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge[$inv->status] ?? 'bg-gray-100 text-gray-600' }}">{{ \App\Models\ClientInvoice::STATUSES[$inv->status] ?? $inv->status }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.invoices.show', $inv) }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="View">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                    </a>
                                    <a href="{{ route('admin.invoices.pdf', $inv) }}" target="_blank" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="PDF">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7zM14 3v5h5M9 13h6M9 17h4"/></svg>
                                    </a>
                                    <a href="{{ route('admin.invoices.edit', $inv) }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('admin.invoices.destroy', $inv) }}" onsubmit="return confirm('Delete this invoice?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400">No invoices yet — <a href="{{ route('admin.invoices.create') }}" class="font-semibold text-[var(--color-primary)] hover:underline">create your first invoice</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
@endsection
