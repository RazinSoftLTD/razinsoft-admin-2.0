@extends('admin.layouts.app')
@section('title', 'Transfers')

@php
    $canCreate = auth()->user()->allows('finance', 'create');
    $canDelete = auth()->user()->allows('finance', 'delete');
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Transfers</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Move money between wallets and bank accounts. Both balances update together.</p>
        </div>
        @if ($canCreate)
            <button type="button" @click="$dispatch('open-transfer')" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> New Transfer
            </button>
        @endif
    </div>

    @include('admin.finance._nav')

    <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm" style="min-width:820px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Date</th>
                    <th class="px-5 py-3 font-semibold">From</th>
                    <th class="px-5 py-3 font-semibold">To</th>
                    <th class="px-5 py-3 text-right font-semibold">Sent</th>
                    <th class="px-5 py-3 text-right font-semibold">Received</th>
                    <th class="px-5 py-3 text-right font-semibold">Fees</th>
                    <th class="px-5 py-3 font-semibold">Reference</th>
                    <th class="px-5 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $t)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $t->occurred_on?->format('d M Y') }}</td>
                        <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ $t->account?->name ?? '—' }}</td>
                        <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ $t->counterAccount?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-red-600">{{ $t->symbol() }}{{ number_format((float) $t->amount, 2) }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-emerald-600">{{ $t->counterAccount?->symbol() }}{{ number_format((float) $t->converted_amount, 2) }}</td>
                        <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ number_format((float) $t->fee + (float) $t->bank_charge, 2) }}</td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $t->reference ?: '—' }}</td>
                        <td class="px-5 py-3 text-right">
                            @if ($canDelete)
                                <form method="POST" action="{{ route('admin.finance.transactions.destroy', $t) }}" onsubmit="return confirm('Reverse this transfer (both sides)?')">
                                    @csrf @method('DELETE')
                                    <button class="rounded p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600" title="Reverse">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-gray-300">No transfers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>

    @if ($canCreate)
        @include('admin.finance._transfer-modal', ['accounts' => $accounts, 'event' => 'open-transfer', 'title' => 'New Transfer', 'conversion' => false])
    @endif
@endsection
