@extends('admin.layouts.app')
@section('title', 'Deal — '.$deal->title)

@php
    $stageBadge = [
        'new' => 'bg-gray-100 text-gray-600', 'qualified' => 'bg-indigo-50 text-indigo-700',
        'proposal' => 'bg-orange-50 text-orange-700', 'negotiation' => 'bg-amber-50 text-amber-700',
        'won' => 'bg-emerald-50 text-emerald-700', 'lost' => 'bg-red-50 text-red-600',
    ];
    $statusBadge = [
        'draft' => 'bg-gray-100 text-gray-600', 'sent' => 'bg-blue-50 text-blue-700',
        'partially_paid' => 'bg-amber-50 text-amber-700', 'paid' => 'bg-emerald-50 text-emerald-700', 'overdue' => 'bg-red-50 text-red-600',
    ];
    $sym = \App\Models\Currency::symbolMap();
    $cur = $sym[$deal->currency] ?? '';
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.deals.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Deals
            </a>
            <h1 class="mt-2 flex items-center gap-3 text-xl font-bold text-[var(--color-heading)]">
                {{ $deal->title }}
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $stageBadge[$deal->stage] ?? '' }}">{{ \App\Models\Deal::STAGES[$deal->stage] ?? $deal->stage }}</span>
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.deals.edit', $deal) }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Edit</a>
            @if ($deal->isWon())
                <form method="POST" action="{{ route('admin.deals.invoice', $deal) }}">@csrf
                    <button class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M7 3h7l5 5v13H7zM14 3v5h5"/></svg> Create Invoice
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Details --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-400">Deal Value</p>
                        <p class="mt-1 text-3xl font-extrabold text-[var(--color-heading)]">{{ $cur }}{{ number_format($deal->value, 2) }} <span class="text-base font-medium text-gray-400">{{ $deal->currency }}</span></p>
                    </div>
                    {{-- Quick stage change --}}
                    <form method="POST" action="{{ route('admin.deals.stage', $deal) }}">
                        @csrf
                        <label class="mb-1 block text-right text-[11px] uppercase tracking-wide text-gray-400">Move stage</label>
                        <select name="stage" onchange="this.form.submit()" class="h-10 rounded-lg border-gray-200 text-sm">
                            @foreach (\App\Models\Deal::STAGES as $sk => $sl)<option value="{{ $sk }}" @selected($deal->stage === $sk)>{{ $sl }}</option>@endforeach
                        </select>
                    </form>
                </div>

                <dl class="mt-6 grid gap-x-6 gap-y-4 border-t border-gray-100 pt-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Expected Close</dt>
                        <dd class="mt-0.5 text-sm font-medium {{ $deal->isOverdue() ? 'text-red-600' : 'text-[var(--color-heading)]' }}">
                            {{ $deal->expected_close_date?->format('d M Y') ?? '—' }}
                            @if ($deal->isOverdue())<span class="ml-1 text-xs font-semibold">(overdue)</span>@endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Owner</dt>
                        <dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $deal->assignee?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Created</dt>
                        <dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $deal->created_at->format('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Last Updated</dt>
                        <dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $deal->updated_at->diffForHumans() }}</dd>
                    </div>
                </dl>

                @if ($deal->notes)
                    <div class="mt-5 border-t border-gray-100 pt-4">
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Notes</dt>
                        <dd class="mt-1 text-sm leading-relaxed text-[var(--color-muted)]">{{ $deal->notes }}</dd>
                    </div>
                @endif
            </div>

            {{-- Invoices raised from this deal --}}
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Invoices</h2>
                    <span class="text-xs text-[var(--color-muted)]">{{ $deal->invoices->count() }} total</span>
                </div>
                @if ($deal->invoices->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-[var(--color-muted)]">
                        No invoices yet.@if ($deal->isWon()) Use “Create Invoice” to raise one.@endif
                    </div>
                @else
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                            <tr><th class="px-5 py-3 font-semibold">Invoice #</th><th class="px-5 py-3 text-right font-semibold">Total</th><th class="px-5 py-3 text-right font-semibold">Due</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3 text-right font-semibold">Action</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($deal->invoices as $inv)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $inv->invoice_number }}</td>
                                    <td class="px-5 py-3 text-right">{{ $sym[$inv->currency] ?? '' }}{{ number_format($inv->total, 2) }}</td>
                                    <td class="px-5 py-3 text-right font-semibold {{ $inv->amountDue() > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $sym[$inv->currency] ?? '' }}{{ number_format($inv->amountDue(), 2) }}</td>
                                    <td class="px-5 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge[$inv->status] ?? '' }}">{{ \App\Models\ClientInvoice::STATUSES[$inv->status] ?? $inv->status }}</span></td>
                                    <td class="px-5 py-3 text-right"><a href="{{ route('admin.invoices.show', $inv) }}" class="font-semibold text-[var(--color-primary)] hover:underline">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Related records --}}
        <div class="space-y-4">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Client</h2>
                @if ($deal->client)
                    <a href="{{ route('admin.clients.show', $deal->client_id) }}" class="flex items-center gap-3 hover:opacity-80">
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]">{{ strtoupper(substr($deal->client->name, 0, 1)) }}</span>
                        <div>
                            <p class="font-semibold text-[var(--color-primary)] hover:underline">{{ $deal->client->name }}</p>
                            <p class="text-xs text-[var(--color-muted)]">{{ $deal->client->email }}</p>
                        </div>
                    </a>
                @else
                    <p class="text-sm text-[var(--color-muted)]">No client linked.</p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Source Lead</h2>
                @if ($deal->lead)
                    <a href="{{ route('admin.leads.show', $deal->lead_id) }}" class="block hover:opacity-80">
                        <p class="font-semibold text-[var(--color-primary)] hover:underline">{{ $deal->lead->full_name }}</p>
                        <p class="text-xs text-[var(--color-muted)]">{{ $deal->lead->lead_code }} · {{ \App\Models\Lead::STATUSES[$deal->lead->lead_status] ?? $deal->lead->lead_status }}</p>
                    </a>
                @else
                    <p class="text-sm text-[var(--color-muted)]">Not created from a lead.</p>
                @endif
            </div>
        </div>
    </div>
@endsection
