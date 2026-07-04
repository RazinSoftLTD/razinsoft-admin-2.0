@extends('admin.layouts.app')
@section('title', $invoice->invoice_number)

@php
    $statusBadge = [
        'draft' => 'bg-gray-100 text-gray-600', 'sent' => 'bg-blue-50 text-blue-700',
        'partially_paid' => 'bg-amber-50 text-amber-700', 'paid' => 'bg-emerald-50 text-emerald-700', 'overdue' => 'bg-red-50 text-red-600',
    ];
    $cur = ['USD' => '$', 'BDT' => '৳', 'EUR' => '€', 'GBP' => '£'][$invoice->currency] ?? '';
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.invoices.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Invoices
            </a>
            <h1 class="mt-2 text-xl font-bold text-[var(--color-heading)]">{{ $invoice->invoice_number }}
                <span class="ml-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge[$invoice->status] ?? '' }}">{{ \App\Models\ClientInvoice::STATUSES[$invoice->status] ?? $invoice->status }}</span>
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.invoices.pdf', $invoice) }}" target="_blank" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Download PDF</a>
            <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}">
                @csrf
                <button class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Save &amp; Send</button>
            </form>
            <a href="{{ route('admin.invoices.edit', $invoice) }}" class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Edit</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Invoice document --}}
        <div class="rounded-xl border border-gray-100 bg-white p-8 shadow-sm lg:col-span-2">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xl font-extrabold text-[var(--color-primary)]">RazinSoft</span>
                    <p class="mt-1 text-xs text-[var(--color-muted)]">RazinSoft Ltd. · support@razinsoft.com</p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-bold text-[var(--color-heading)]">{{ $invoice->invoice_number }}</p>
                    <p class="text-xs text-[var(--color-muted)]">Issue: {{ $invoice->invoice_date->format('d M Y') }}</p>
                    @if ($invoice->due_date)<p class="text-xs text-[var(--color-muted)]">Due: {{ $invoice->due_date->format('d M Y') }}</p>@endif
                </div>
            </div>

            <div class="mt-6 border-t border-gray-100 pt-4">
                <p class="text-xs font-semibold text-gray-400">BILL TO</p>
                <p class="mt-1 font-semibold text-[var(--color-heading)]">{{ $invoice->bill_to_name ?: '—' }}</p>
                @if ($invoice->bill_to_company)<p class="text-sm text-[var(--color-muted)]">{{ $invoice->bill_to_company }}</p>@endif
                @if ($invoice->bill_to_address)<p class="text-sm text-[var(--color-muted)]">{{ $invoice->bill_to_address }}</p>@endif
                @if ($invoice->bill_to_email)<p class="text-sm text-[var(--color-muted)]">{{ $invoice->bill_to_email }}</p>@endif
            </div>

            <table class="mt-6 w-full text-sm">
                <thead><tr class="border-b border-gray-200 text-xs uppercase text-gray-400">
                    <th class="py-2 text-left">Item</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Rate</th><th class="py-2 text-right">Tax</th><th class="py-2 text-right">Amount</th>
                </tr></thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr class="border-b border-gray-50">
                            <td class="py-2.5"><p class="font-medium text-[var(--color-heading)]">{{ $item->description }}</p>@if ($item->sub_description)<p class="text-xs text-[var(--color-muted)]">{{ $item->sub_description }}</p>@endif</td>
                            <td class="py-2.5 text-right text-[var(--color-muted)]">{{ rtrim(rtrim(number_format($item->qty, 2), '0'), '.') }}</td>
                            <td class="py-2.5 text-right text-[var(--color-muted)]">{{ $cur }}{{ number_format($item->unit_price, 2) }}</td>
                            <td class="py-2.5 text-right text-[var(--color-muted)]">{{ rtrim(rtrim(number_format($item->tax_percent, 2), '0'), '.') }}%</td>
                            <td class="py-2.5 text-right font-medium text-[var(--color-heading)]">{{ $cur }}{{ number_format($item->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4 flex justify-end">
                <div class="w-64 space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-[var(--color-muted)]">Subtotal</span><span>{{ $cur }}{{ number_format($invoice->subtotal, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-[var(--color-muted)]">Discount</span><span>-{{ $cur }}{{ number_format($invoice->discount_total, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-[var(--color-muted)]">Tax</span><span>{{ $cur }}{{ number_format($invoice->tax_total, 2) }}</span></div>
                    <div class="flex justify-between border-t border-gray-100 pt-1.5 font-semibold text-[var(--color-heading)]"><span>Total</span><span>{{ $cur }}{{ number_format($invoice->total, 2) }}</span></div>
                    @if ($invoice->amount_paid > 0)<div class="flex justify-between text-emerald-600"><span>Paid</span><span>-{{ $cur }}{{ number_format($invoice->amount_paid, 2) }}</span></div>@endif
                    <div class="flex justify-between rounded-lg bg-[var(--color-primary-soft)] px-3 py-2 font-bold text-[var(--color-primary)]"><span>Amount Due</span><span>{{ $cur }}{{ number_format($invoice->amountDue(), 2) }}</span></div>
                </div>
            </div>

            @if ($invoice->notes || $invoice->terms)
                <div class="mt-6 space-y-2 border-t border-gray-100 pt-4 text-xs text-[var(--color-muted)]">
                    @if ($invoice->notes)<p><span class="font-semibold text-[var(--color-heading)]">Notes:</span> {{ $invoice->notes }}</p>@endif
                    @if ($invoice->terms)<p><span class="font-semibold text-[var(--color-heading)]">Terms:</span> {{ $invoice->terms }}</p>@endif
                </div>
            @endif
        </div>

        {{-- Side: summary + payments (C5 fills this) --}}
        <div class="space-y-4">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Summary</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-gray-400">Total</span><span class="font-medium text-[var(--color-heading)]">{{ $cur }}{{ number_format($invoice->total, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Paid</span><span class="font-medium text-emerald-600">{{ $cur }}{{ number_format($invoice->amount_paid, 2) }}</span></div>
                    <div class="flex justify-between border-t border-gray-100 pt-3"><span class="text-gray-400">Amount Due</span><span class="font-bold text-red-600">{{ $cur }}{{ number_format($invoice->amountDue(), 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Payment Method</span><span class="text-[var(--color-heading)]">{{ $invoice->payment_method ?? '—' }}</span></div>
                    @if ($invoice->client)<div class="flex justify-between"><span class="text-gray-400">Client</span><a href="{{ route('admin.clients.edit', $invoice->client) }}" class="font-medium text-[var(--color-primary)] hover:underline">{{ $invoice->client->client_code }}</a></div>@endif
                </div>
            </div>
            {{-- Record a payment --}}
            @if ($invoice->amountDue() > 0)
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Record Payment</h2>
                    <form method="POST" action="{{ route('admin.invoices.payments.store', $invoice) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[var(--color-muted)]">Amount ({{ $cur }})</label>
                            <input type="number" step="0.01" min="0.01" max="{{ $invoice->amountDue() }}" name="amount" value="{{ number_format($invoice->amountDue(), 2, '.', '') }}" required class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                            <p class="mt-1 text-[11px] text-[var(--color-muted)]">Max due: {{ $cur }}{{ number_format($invoice->amountDue(), 2) }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-[var(--color-muted)]">Date</label>
                                <input type="date" name="paid_at" value="{{ now()->toDateString() }}" required class="h-10 w-full rounded-lg border border-gray-200 px-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-[var(--color-muted)]">Method</label>
                                <select name="method" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-2 text-sm">
                                    @foreach (\App\Models\ClientInvoice::PAYMENT_METHODS as $m)<option value="{{ $m }}" @selected($invoice->payment_method === $m)>{{ $m }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <input type="text" name="reference" placeholder="Reference / txn id (optional)" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                        <input type="text" name="note" placeholder="Note (optional)" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                        <button class="w-full rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Record Payment</button>
                    </form>
                </div>
            @else
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-center text-sm font-semibold text-emerald-700">
                    ✓ Fully paid
                </div>
            @endif

            {{-- Payment history --}}
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Payment History</h2>
                @forelse ($invoice->payments as $p)
                    <div class="flex items-start justify-between border-b border-gray-50 py-2.5 last:border-0">
                        <div>
                            <p class="text-sm font-semibold text-[var(--color-heading)]">{{ $cur }}{{ number_format($p->amount, 2) }}</p>
                            <p class="text-xs text-[var(--color-muted)]">{{ $p->paid_at->format('d M Y') }} · {{ $p->method ?? '—' }}@if ($p->reference) · {{ $p->reference }}@endif</p>
                        </div>
                        <form method="POST" action="{{ route('admin.invoices.payments.destroy', [$invoice, $p]) }}" onsubmit="return confirm('Remove this payment?')">
                            @csrf @method('DELETE')
                            <button class="rounded-lg p-1.5 text-gray-300 hover:bg-red-50 hover:text-red-600" title="Remove">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No payments recorded yet.</p>
                @endforelse
            </div>

            {{-- Pay link (client pays online via Stripe) --}}
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Client Pay Link</h2>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ route('pay.invoice.show', $invoice->public_token) }}" onclick="this.select()" class="h-9 flex-1 rounded-lg border border-gray-200 bg-gray-50 px-2 text-xs text-[var(--color-muted)]">
                    <a href="{{ route('pay.invoice.show', $invoice->public_token) }}" target="_blank" class="rounded-lg bg-[var(--color-primary-soft)] px-3 py-2 text-xs font-semibold text-[var(--color-primary)]">Open</a>
                </div>
                <p class="mt-2 text-xs text-[var(--color-muted)]">Share this link — the client pays online (Stripe). Payment is recorded automatically.</p>
            </div>

            {{-- Payment request --}}
            @if ($invoice->amountDue() > 0)
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Request a Payment</h2>
                    @if (! is_null($invoice->requested_amount))
                        <p class="mb-3 rounded-lg bg-indigo-50 px-3 py-2 text-xs text-indigo-700">Client is asked to pay <strong>{{ $cur }}{{ number_format($invoice->payableAmount(), 2) }}</strong> now.</p>
                    @endif
                    <form method="POST" action="{{ route('admin.invoices.request-payment', $invoice) }}" class="flex items-center gap-2">
                        @csrf
                        <input type="number" step="0.01" min="0.01" max="{{ $invoice->amountDue() }}" name="requested_amount" value="{{ $invoice->requested_amount ? number_format($invoice->requested_amount, 2, '.', '') : '' }}" placeholder="Amount to request" class="h-9 flex-1 rounded-lg border border-gray-200 px-2 text-sm">
                        <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white hover:bg-[var(--color-primary-hover)]">Set</button>
                    </form>
                    @if (! is_null($invoice->requested_amount))
                        <form method="POST" action="{{ route('admin.invoices.request-payment', $invoice) }}" class="mt-2">
                            @csrf <input type="hidden" name="requested_amount" value="">
                            <button class="text-xs text-[var(--color-muted)] hover:text-red-600">Clear request (allow full-due payment)</button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- Installments --}}
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Installment Plan</h2>
                </div>
                @if ($invoice->installments->count())
                    <div class="space-y-2">
                        @foreach ($invoice->installments as $ins)
                            <div class="flex items-center justify-between border-b border-gray-50 py-1.5 text-sm last:border-0">
                                <div><p class="text-[var(--color-heading)]">{{ $ins->label }}</p><p class="text-xs text-[var(--color-muted)]">Due {{ $ins->due_date?->format('d M Y') ?? '—' }}</p></div>
                                <span class="font-semibold text-[var(--color-heading)]">{{ $cur }}{{ number_format($ins->amount, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mb-3 text-xs text-gray-400">No installment plan. Split the total into equal parts:</p>
                @endif
                <form method="POST" action="{{ route('admin.invoices.installments', $invoice) }}" class="mt-3 flex items-center gap-2">
                    @csrf
                    <input type="number" min="1" max="24" name="parts" value="3" class="h-9 w-16 rounded-lg border border-gray-200 px-2 text-sm" title="Number of installments">
                    <span class="text-xs text-[var(--color-muted)]">parts, every</span>
                    <input type="number" min="1" max="365" name="interval_days" value="30" class="h-9 w-16 rounded-lg border border-gray-200 px-2 text-sm" title="Days between installments">
                    <span class="text-xs text-[var(--color-muted)]">days</span>
                    <button class="ml-auto rounded-lg bg-[var(--color-primary-soft)] px-3 py-2 text-xs font-semibold text-[var(--color-primary)]">Split</button>
                </form>
            </div>
        </div>
    </div>
@endsection
