@extends('admin.layouts.app')
@section('title', 'Currency Conversion')

@php
    $canCreate = auth()->user()->allows('finance', 'create');
    $canDelete = auth()->user()->allows('finance', 'delete');
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Currency Conversion</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">USD → BDT, GBP → BDT and the like. Recorded when the two accounts hold different currencies.</p>
        </div>
        @if ($canCreate)
            <button type="button" @click="$dispatch('open-conversion')" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> New Conversion
            </button>
        @endif
    </div>

    @include('admin.finance._nav')

    <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm" style="min-width:900px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Date</th>
                    <th class="px-5 py-3 font-semibold">From</th>
                    <th class="px-5 py-3 font-semibold">To</th>
                    <th class="px-5 py-3 text-right font-semibold">Original</th>
                    <th class="px-5 py-3 text-right font-semibold">Converted</th>
                    <th class="px-5 py-3 text-right font-semibold">Rate</th>
                    <th class="px-5 py-3 text-right font-semibold">Fee / Charge</th>
                    <th class="px-5 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $t)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $t->occurred_on?->format('d M Y') }}</td>
                        <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ $t->account?->name ?? '—' }} <span class="text-xs text-gray-400">{{ $t->currency }}</span></td>
                        <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ $t->counterAccount?->name ?? '—' }} <span class="text-xs text-gray-400">{{ $t->counterAccount?->currency }}</span></td>
                        <td class="px-5 py-3 text-right font-semibold text-red-600">{{ $t->symbol() }}{{ number_format((float) $t->amount, 2) }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-emerald-600">{{ $t->counterAccount?->symbol() }}{{ number_format((float) $t->converted_amount, 2) }}</td>
                        <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ $t->exchange_rate ? rtrim(rtrim(number_format((float) $t->exchange_rate, 6), '0'), '.') : '—' }}</td>
                        <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ number_format((float) $t->fee, 2) }} / {{ number_format((float) $t->bank_charge, 2) }}</td>
                        <td class="px-5 py-3 text-right">
                            @if ($canDelete)
                                <form method="POST" action="{{ route('admin.finance.transactions.destroy', $t) }}" onsubmit="return confirm('Reverse this conversion (both sides)?')">
                                    @csrf @method('DELETE')
                                    <button class="rounded p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600" title="Reverse">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-gray-300">No conversions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>

    @if ($canCreate)
        @include('admin.finance._transfer-modal', ['accounts' => $accounts, 'event' => 'open-conversion', 'title' => 'New Conversion', 'conversion' => true])
    @endif
@endsection
