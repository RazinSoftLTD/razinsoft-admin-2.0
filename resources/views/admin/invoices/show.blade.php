@extends('admin.layouts.app')
@section('title', $invoice->invoice_number)

@php
    $statusBadge = [
        'draft' => 'bg-gray-100 text-gray-600', 'sent' => 'bg-blue-50 text-blue-700',
        'partially_paid' => 'bg-amber-50 text-amber-700', 'paid' => 'bg-emerald-50 text-emerald-700', 'overdue' => 'bg-red-50 text-red-600',
    ];
    $cur = $invoice->currencySymbol();
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
            @if (auth()->user()->allows('invoices', 'send'))
                <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}">
                    @csrf
                    <button class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Save &amp; Send</button>
                </form>
            @endif
            @if (auth()->user()->allows('invoices', 'edit'))
                <a href="{{ route('admin.invoices.edit', $invoice) }}" class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Edit</a>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Invoice document --}}
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm lg:col-span-2">
            {{-- Header --}}
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 p-8">
                <div>
                    <img src="{{ asset('images/razinsoft-logo.png') }}" alt="RazinSoft" class="h-9 w-auto">
                    <p class="mt-2 text-xs text-[var(--color-muted)]">RazinSoft Ltd. · support@razinsoft.com</p>
                </div>
                <div class="text-right">
                    <span class="inline-flex rounded-md bg-[var(--color-primary-soft)] px-3 py-1 text-xs font-bold uppercase tracking-wider text-[var(--color-primary)]">Invoice</span>
                    <p class="mt-2 text-lg font-bold text-[var(--color-heading)]">{{ $invoice->invoice_number }}</p>
                </div>
            </div>

            {{-- Meta: Bill To + dates --}}
            <div class="grid gap-6 p-8 sm:grid-cols-2">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Bill To</p>
                    @if ($invoice->client)
                        <a href="{{ route('admin.clients.show', $invoice->client_id) }}" class="mt-1.5 inline-block font-semibold text-[var(--color-primary)] hover:underline">{{ $invoice->bill_to_name ?: $invoice->client->name }}</a>
                    @else
                        <p class="mt-1.5 font-semibold text-[var(--color-heading)]">{{ $invoice->bill_to_name ?: '—' }}</p>
                    @endif
                    @if ($invoice->bill_to_company)<p class="text-sm text-[var(--color-muted)]">{{ $invoice->bill_to_company }}</p>@endif
                    @if ($invoice->bill_to_address)<p class="text-sm leading-relaxed text-[var(--color-muted)]">{{ $invoice->bill_to_address }}</p>@endif
                    @if ($invoice->bill_to_email)<p class="text-sm text-[var(--color-muted)]">{{ $invoice->bill_to_email }}</p>@endif
                    @if ($invoice->bill_to_phone)<p class="text-sm text-[var(--color-muted)]">{{ $invoice->bill_to_phone }}</p>@endif
                </div>
                <div class="sm:text-right">
                    <div class="inline-grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm sm:text-left">
                        <span class="text-gray-400">Issue Date</span>
                        <span class="font-medium text-[var(--color-heading)]">{{ $invoice->invoice_date->format('d M Y') }}</span>
                        @if ($invoice->due_date)
                            <span class="text-gray-400">Due Date</span>
                            <span class="font-medium text-[var(--color-heading)]">{{ $invoice->due_date->format('d M Y') }}</span>
                        @endif
                        <span class="text-gray-400">Status</span>
                        <span><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadge[$invoice->status] ?? '' }}">{{ \App\Models\ClientInvoice::STATUSES[$invoice->status] ?? $invoice->status }}</span></span>
                    </div>
                </div>
            </div>

            {{-- Items --}}
            <div class="px-8">
                <div class="overflow-x-auto">
                    <table class="w-full border border-gray-200 text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-[var(--color-heading)]">
                                <th class="px-4 py-2.5 text-left font-semibold">Description</th>
                                <th class="border-l border-gray-200 px-4 py-2.5 text-center font-semibold">Quantity</th>
                                <th class="border-l border-gray-200 px-4 py-2.5 text-right font-semibold">Unit Price</th>
                                <th class="border-l border-gray-200 px-4 py-2.5 text-right font-semibold">Tax</th>
                                <th class="border-l border-gray-200 px-4 py-2.5 text-right font-semibold">Amount ({{ $invoice->currency }})</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-[var(--color-heading)]">{{ $item->description }}</td>
                                    <td class="border-l border-gray-200 px-4 py-3 text-center text-[var(--color-muted)]">{{ rtrim(rtrim(number_format($item->qty, 2), '0'), '.') }}<span class="block text-[11px] text-gray-400">{{ $item->unit ?: 'Items' }}</span></td>
                                    <td class="border-l border-gray-200 px-4 py-3 text-right text-[var(--color-muted)]">{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="border-l border-gray-200 px-4 py-3 text-right text-[var(--color-muted)]">@if ($item->tax_percent > 0){{ rtrim(rtrim(number_format($item->tax_percent, 2), '0'), '.') }}%@endif</td>
                                    <td class="border-l border-gray-200 px-4 py-3 text-right font-medium text-[var(--color-heading)]">{{ number_format($item->amount, 2) }}</td>
                                </tr>
                            @endforeach
                            {{-- Sub-descriptions: full-width detail rows under the items, like the sample layout --}}
                            @foreach ($invoice->items as $item)
                                @if ($item->sub_description)
                                    <tr>
                                        <td colspan="5" class="invoice-subdesc px-4 py-3 text-xs leading-relaxed text-[var(--color-heading)]">{!! $item->formattedSubDescription() !!}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Totals --}}
            <div class="flex justify-end px-8 pt-5">
                <div class="w-full max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-[var(--color-muted)]">Sub Total</span><span class="text-[var(--color-heading)]">{{ $cur }}{{ number_format($invoice->subtotal, 2) }}</span></div>
                    @if ($invoice->discount_total > 0)
                        <div class="flex justify-between"><span class="text-[var(--color-muted)]">Discount{{ $invoice->discount_type === 'percent' && $invoice->discount_value > 0 ? ': '.rtrim(rtrim(number_format($invoice->discount_value, 2), '0'), '.').'%' : '' }}</span><span class="text-[var(--color-heading)]">−{{ $cur }}{{ number_format($invoice->discount_total, 2) }}</span></div>
                    @endif
                    @if ($invoice->tax_total > 0)
                        <div class="flex justify-between"><span class="text-[var(--color-muted)]">Tax</span><span class="text-[var(--color-heading)]">{{ $cur }}{{ number_format($invoice->tax_total, 2) }}</span></div>
                    @endif
                    <div class="flex justify-between border-t border-gray-100 pt-2 font-semibold text-[var(--color-heading)]"><span>Total</span><span>{{ $cur }}{{ number_format($invoice->total, 2) }}</span></div>
                    @if ($invoice->amount_paid > 0)<div class="flex justify-between text-emerald-600"><span>Paid</span><span>−{{ $cur }}{{ number_format($invoice->amount_paid, 2) }}</span></div>@endif
                    <div class="mt-1 flex justify-between rounded-lg bg-[var(--color-primary-soft)] px-4 py-3 text-base font-bold text-[var(--color-primary)]"><span>Total Due</span><span>{{ $cur }}{{ number_format($invoice->amountDue(), 2) }} {{ $invoice->currency }}</span></div>
                </div>
            </div>

            {{-- Notes / Terms --}}
            @if ($invoice->notes || $invoice->terms)
                <div class="mt-6 grid gap-6 border-t border-gray-100 p-8 text-xs leading-relaxed text-[var(--color-muted)] sm:grid-cols-2">
                    <div>
                        <p class="mb-1 text-[11px] font-bold uppercase tracking-wider text-gray-400">Notes</p>
                        <p>{{ $invoice->notes ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-[11px] font-bold uppercase tracking-wider text-gray-400">Terms</p>
                        <p>{{ $invoice->terms ?: '—' }}</p>
                    </div>
                </div>
            @else
                <div class="pb-8"></div>
            @endif
        </div>

        {{-- Side: summary + pay link + payments + activity --}}
        <div class="space-y-4" x-data="{ payOpen: false }" x-init="if (location.hash === '#add-payment' && {{ $invoice->amountDue() > 0 ? 'true' : 'false' }}) payOpen = true">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Summary</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-gray-400">Total</span><span class="font-medium text-[var(--color-heading)]">{{ $cur }}{{ number_format($invoice->total, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Paid</span><span class="font-medium text-emerald-600">{{ $cur }}{{ number_format($invoice->amount_paid, 2) }}</span></div>
                    <div class="flex justify-between border-t border-gray-100 pt-3"><span class="text-gray-400">Amount Due</span><span class="font-bold {{ $invoice->amountDue() > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $cur }}{{ number_format($invoice->amountDue(), 2) }}</span></div>
                    @if ($invoice->client)<div class="flex justify-between"><span class="text-gray-400">Client</span><a href="{{ route('admin.clients.edit', $invoice->client) }}" class="font-medium text-[var(--color-primary)] hover:underline">{{ $invoice->client->client_code }}</a></div>@endif
                </div>
                @if ($invoice->amountDue() <= 0)
                    <p class="mt-4 rounded-lg bg-emerald-50 py-2.5 text-center text-sm font-semibold text-emerald-700">✓ Fully paid</p>
                @endif
            </div>

            {{-- Payment Options — only while payment is NOT complete --}}
            @if ($invoice->amountDue() > 0 && ! in_array($invoice->status, ['paid', 'cancelled'], true))
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Payment Options</h2>
                    <div class="flex items-center gap-2" x-data="{ link: @js($invoice->payUrl()), copied: false, async copy() { try { await navigator.clipboard.writeText(this.link); } catch (e) { const i = this.$refs.input; i.select(); document.execCommand('copy'); } this.copied = true; setTimeout(() => this.copied = false, 1500); } }">
                        <input x-ref="input" type="text" readonly :value="link" @click="copy()" class="h-9 flex-1 cursor-pointer rounded-lg border border-gray-200 bg-gray-50 px-2 text-xs text-[var(--color-muted)]">
                        <button type="button" @click="copy()" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[var(--color-primary-soft)] px-3 text-xs font-semibold text-[var(--color-primary)]">
                            <span x-show="!copied" class="inline-flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                Copy
                            </span>
                            <span x-show="copied" x-cloak class="inline-flex items-center gap-1.5 text-emerald-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>
                                Copied
                            </span>
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-[var(--color-muted)]">Share this link — the client pays online. Payment is recorded automatically.</p>

                    {{-- Gateways + partial payment --}}
                    @php $methods = $invoice->payMethods(); @endphp
                    <form method="POST" action="{{ route('admin.invoices.pay-options', $invoice) }}" class="mt-4 space-y-3 border-t border-gray-100 pt-4"
                          x-data="{ partial: {{ is_null($invoice->requested_amount) ? 'false' : 'true' }} }">
                        @csrf
                        <div>
                            <p class="mb-1.5 text-xs font-semibold text-[var(--color-muted)]">Client can pay with</p>
                            <div class="flex items-center gap-4">
                                <label class="inline-flex items-center gap-2 text-sm text-[var(--color-heading)]">
                                    <input type="checkbox" name="pay_methods[]" value="stripe" @checked(in_array('stripe', $methods)) class="rounded accent-[var(--color-primary)]">
                                    Stripe <span class="text-[10px] text-gray-400">(card)</span>
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-[var(--color-heading)]">
                                    <input type="checkbox" name="pay_methods[]" value="paypal" @checked(in_array('paypal', $methods)) class="rounded accent-[var(--color-primary)]">
                                    PayPal
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="inline-flex items-center gap-2 text-sm text-[var(--color-heading)]">
                                <input type="checkbox" name="partial_enabled" value="1" x-model="partial" class="rounded accent-[var(--color-primary)]">
                                Allow partial payment
                            </label>
                            <div class="mt-2 space-y-2" x-show="partial" x-cloak>
                                <input type="number" name="partial_amount" step="0.01" min="0.01" max="{{ $invoice->amountDue() }}"
                                       value="{{ old('partial_amount', $invoice->requested_amount) }}" placeholder="Amount the client will pay now"
                                       class="h-9 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                <input type="text" name="partial_note" maxlength="255"
                                       value="{{ old('partial_note', $invoice->requested_note) }}" placeholder="Short description (optional) — e.g. 50% advance"
                                       class="h-9 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                <p class="text-[11px] text-[var(--color-muted)]">The pay link will charge exactly this amount (due: {{ $cur }}{{ number_format($invoice->amountDue(), 2) }}). The description shows on the pay link and is saved as the payment's remark.</p>
                            </div>
                        </div>
                        <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save Payment Options</button>
                    </form>
                </div>
            @endif

            {{-- Payment history (detailed) — finance only --}}
            @if (auth()->user()->allows('invoices', 'finance'))
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Payment History</h2>
                        @if ($invoice->amountDue() > 0)<button type="button" @click="payOpen = true" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">+ Add</button>@endif
                    </div>
                    @forelse ($invoice->payments as $p)
                        <div class="border-b border-gray-100 py-3 last:border-0">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm font-bold text-[var(--color-heading)]">{{ $cur }}{{ number_format($p->amount, 2) }}
                                        @if ($p->currency && $p->currency !== $invoice->currency)<span class="text-xs font-normal text-gray-400">({{ $p->currency }}@if ($p->exchange_rate) @ {{ rtrim(rtrim(number_format($p->exchange_rate, 4), '0'), '.') }}@endif)</span>@endif
                                    </p>
                                    <p class="text-xs text-[var(--color-muted)]">{{ $p->paid_at->format('d M Y') }} · {{ $p->method ?? '—' }}</p>
                                </div>
                                <form method="POST" action="{{ route('admin.invoices.payments.destroy', [$invoice, $p]) }}" onsubmit="return confirm('Remove this payment?')">
                                    @csrf @method('DELETE')
                                    <button class="rounded-lg p-1.5 text-gray-300 hover:bg-red-50 hover:text-red-600" title="Remove">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                    </button>
                                </form>
                            </div>
                            <dl class="mt-1.5 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-xs text-[var(--color-muted)]">
                                @if ($p->reference)<dt class="text-gray-400">Txn ID</dt><dd class="text-[var(--color-heading)]">{{ $p->reference }}</dd>@endif
                                @if ($p->bank_account)<dt class="text-gray-400">Bank</dt><dd>{{ $p->bank_account }}</dd>@endif
                                @if ($p->project)<dt class="text-gray-400">Project</dt><dd>{{ $p->project->name }}</dd>@endif
                                @if ($p->note)<dt class="text-gray-400">Remark</dt><dd>{{ $p->note }}</dd>@endif
                                <dt class="text-gray-400">Recorded by</dt><dd>{{ $p->recorder->name ?? 'Client (online)' }}</dd>
                                @if ($p->receipt_url)<dt class="text-gray-400">Receipt</dt><dd><a href="{{ $p->receipt_url }}" target="_blank" class="text-[var(--color-primary)] hover:underline">View file</a></dd>@endif
                            </dl>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No payments recorded yet.</p>
                    @endforelse
                </div>
            @endif

            {{-- Activity log --}}
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Activity</h2>
                @forelse ($invoice->activities as $a)
                    <div class="flex gap-3 pb-3 last:pb-0">
                        <div class="mt-1 flex flex-col items-center">
                            <span class="h-2 w-2 shrink-0 rounded-full bg-[var(--color-primary)]"></span>
                            @if (! $loop->last)<span class="mt-0.5 w-px flex-1 bg-gray-100"></span>@endif
                        </div>
                        <div class="-mt-0.5">
                            <p class="text-sm text-[var(--color-heading)]">{{ $a->description }}</p>
                            <p class="text-xs text-[var(--color-muted)]">{{ $a->actorLabel() }} · {{ $a->created_at->format('d M Y, h:i A') }} <span class="text-gray-300">({{ $a->created_at->diffForHumans() }})</span></p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No activity yet.</p>
                @endforelse
            </div>

            {{-- ===== Add Payment modal ===== --}}
            @if (auth()->user()->allows('invoices', 'finance'))
                <div x-show="payOpen" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 py-10" @click.self="payOpen = false">
                    <form method="POST" action="{{ route('admin.invoices.payments.store', $invoice) }}" enctype="multipart/form-data" class="w-full max-w-3xl rounded-xl bg-white shadow-xl">
                        @csrf
                        <div class="border-b border-gray-100 px-6 py-4"><h3 class="text-base font-bold text-[var(--color-heading)]">Add Payment</h3></div>
                        <div class="space-y-5 p-6">
                            <p class="text-sm font-semibold text-[var(--color-heading)]">Payment details</p>
                            <div class="grid gap-5 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Invoice</label>
                                    <input type="text" value="{{ $invoice->invoice_number }}" readonly class="h-11 w-full rounded-lg border border-gray-200 bg-gray-100 px-3 text-sm text-[var(--color-muted)]">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Paid On <span class="text-red-500">*</span></label>
                                    <input type="date" name="paid_at" value="{{ now()->toDateString() }}" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Amount <span class="text-red-500">*</span></label>
                                    <input type="number" step="0.01" min="0.01" max="{{ $invoice->amountDue() }}" name="amount" value="{{ number_format($invoice->amountDue(), 2, '.', '') }}" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                                </div>
                            </div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Transaction Id</label>
                                    <input type="text" name="reference" placeholder="Enter transaction ID of the payment" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Payment Gateway</label>
                                    <select name="method" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                        @foreach (\App\Models\ClientInvoice::PAYMENT_METHODS as $m)<option value="{{ $m }}" @selected($invoice->payment_method === $m)>{{ $m }}</option>@endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Bank Information</label>
                                <textarea name="bank_account" rows="3" placeholder="Bank name, account number, branch… (optional)" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none"></textarea>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Receipt</label>
                                <input type="file" name="receipt" accept="image/*,.pdf" class="block w-full text-sm text-[var(--color-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-[var(--color-heading)] hover:file:bg-gray-200">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Remark</label>
                                <textarea name="note" rows="3" placeholder="Enter a summary of the payment." class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none"></textarea>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                            <button type="button" @click="payOpen = false" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save Payment</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <style>[x-cloak]{display:none!important}</style>
@endsection
