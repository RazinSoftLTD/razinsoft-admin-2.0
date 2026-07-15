@extends('admin.layouts.app')
@section('title', 'Invoice Bin')

@php $cur = \App\Models\Currency::symbolMap(); @endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Bin</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Deleted invoices are kept here for {{ $retentionDays }} days, then permanently removed. You can restore or delete them now.</p>
    </div>

    @if (session('status'))<div class="mb-5 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>@endif

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Invoice #</th>
                        <th class="px-5 py-3 font-semibold">Client</th>
                        <th class="px-5 py-3 text-right font-semibold">Total</th>
                        <th class="px-5 py-3 font-semibold">Deleted</th>
                        <th class="px-5 py-3 font-semibold">Auto-purge</th>
                        <th class="px-5 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($invoices as $inv)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $inv->invoice_number }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $inv->bill_to_name ?: ($inv->client->name ?? '—') }}</td>
                            <td class="px-5 py-3 text-right text-[var(--color-heading)]">{{ $cur[$inv->currency] ?? '' }}{{ number_format($inv->total, 2) }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $inv->deleted_at->format('d M Y, h:i A') }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $inv->deleted_at->addDays($retentionDays)->format('d M Y') }} <span class="text-xs text-gray-400">({{ $inv->deleted_at->addDays($retentionDays)->diffForHumans() }})</span></td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.invoices.bin.restore', $inv->id) }}">
                                        @csrf
                                        <button class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-gray-50">Restore</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.invoices.bin.force-delete', $inv->id) }}" onsubmit="return confirm('Permanently delete {{ $inv->invoice_number }}? This cannot be undone.')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Delete forever</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">The Bin is empty.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
@endsection
