@extends('admin.layouts.app')
@section('title', $client->name)

@php
    $sym = \App\Models\Currency::symbolMap();
    $statusBadge = [
        'draft' => 'bg-gray-100 text-gray-600', 'sent' => 'bg-blue-50 text-blue-700',
        'partially_paid' => 'bg-amber-50 text-amber-700', 'paid' => 'bg-emerald-50 text-emerald-700', 'overdue' => 'bg-red-50 text-red-600',
    ];
    // most invoices share one currency — use the first invoice's symbol for the summary
    $primary = $sym[$invoices->first()->currency ?? ''] ?? '';
@endphp

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <a href="{{ route('admin.clients.index') }}" class="inline-flex items-center gap-1.5 text-sm text-[var(--color-muted)] hover:text-[var(--color-heading)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg>
            Back to Clients
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.invoices.create', ['client_id' => $client->id]) }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                New Invoice
            </a>
            <a href="{{ route('admin.clients.edit', $client) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Edit</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Details --}}
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="grid h-12 w-12 place-items-center rounded-full bg-[var(--color-primary-soft)] text-lg font-bold text-[var(--color-primary)]">{{ strtoupper(substr($client->name, 0, 1)) }}</span>
                    <div>
                        <p class="font-bold text-[var(--color-heading)]">{{ $client->name }}</p>
                        @if ($client->company)<p class="text-sm text-[var(--color-muted)]">{{ $client->company }}</p>@endif
                    </div>
                </div>

                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-[var(--color-muted)]">Email</dt><dd class="text-right font-medium text-[var(--color-heading)]">{{ $client->email }}</dd></div>
                    @if ($client->phone)<div class="flex justify-between gap-4"><dt class="text-[var(--color-muted)]">Phone</dt><dd class="text-right font-medium text-[var(--color-heading)]">{{ $client->phone }}</dd></div>@endif
                    @php $addr = collect([$client->address, $client->city, $client->state, $client->zip, $client->country])->filter()->implode(', '); @endphp
                    @if ($addr)<div class="flex justify-between gap-4"><dt class="text-[var(--color-muted)]">Address</dt><dd class="text-right font-medium text-[var(--color-heading)]">{{ $addr }}</dd></div>@endif
                    <div class="flex justify-between gap-4"><dt class="text-[var(--color-muted)]">Client since</dt><dd class="text-right font-medium text-[var(--color-heading)]">{{ $client->created_at?->format('d M Y') }}</dd></div>
                </dl>
            </div>

            {{-- Totals --}}
            <div class="mt-6 grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs text-[var(--color-muted)]">Invoices</p>
                    <p class="mt-1 text-xl font-bold text-[var(--color-heading)]">{{ $stats['count'] }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs text-[var(--color-muted)]">Invoiced</p>
                    <p class="mt-1 text-xl font-bold text-[var(--color-heading)]">{{ $primary }}{{ number_format($stats['invoiced'], 2) }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs text-[var(--color-muted)]">Paid</p>
                    <p class="mt-1 text-xl font-bold text-emerald-600">{{ $primary }}{{ number_format($stats['paid'], 2) }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs text-[var(--color-muted)]">Due</p>
                    <p class="mt-1 text-xl font-bold {{ $stats['due'] > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $primary }}{{ number_format($stats['due'], 2) }}</p>
                </div>
            </div>
        </div>

        {{-- Invoices --}}
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Invoices</h2>
                    <span class="text-xs text-[var(--color-muted)]">{{ $stats['count'] }} total</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Invoice #</th>
                                <th class="px-5 py-3 font-semibold">Date</th>
                                <th class="px-5 py-3 text-right font-semibold">Total</th>
                                <th class="px-5 py-3 text-right font-semibold">Due</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 text-right font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($invoices as $inv)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $inv->invoice_number }}</td>
                                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $inv->invoice_date->format('d M Y') }}</td>
                                    <td class="px-5 py-3 text-right font-medium text-[var(--color-heading)]">{{ $sym[$inv->currency] ?? '' }}{{ number_format($inv->total, 2) }}</td>
                                    <td class="px-5 py-3 text-right font-semibold {{ $inv->amountDue() > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $sym[$inv->currency] ?? '' }}{{ number_format($inv->amountDue(), 2) }}</td>
                                    <td class="px-5 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge[$inv->status] ?? 'bg-gray-100 text-gray-600' }}">{{ \App\Models\ClientInvoice::STATUSES[$inv->status] ?? $inv->status }}</span></td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('admin.invoices.show', $inv) }}" class="text-sm font-semibold text-[var(--color-primary)] hover:underline">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-[var(--color-muted)]">No invoices for this client yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
