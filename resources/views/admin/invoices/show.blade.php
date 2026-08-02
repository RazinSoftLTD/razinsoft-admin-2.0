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
            {{-- ?download=1 sends it as an attachment. Without it the PDF opened inline and the browser
                 named the saved file after the URL, not the invoice. --}}
            <a href="{{ route('admin.invoices.pdf', $invoice) }}?download=1" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Download PDF</a>
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
        {{-- Invoice document. Styled with plain CSS rather than utility classes: the admin ships a
             pre-built stylesheet and does not recompile on deploy, so a class that is not already
             in it would simply do nothing. It also has to match the PDF, and this way one glance
             at either file shows the same structure. --}}
        <div class="inv-doc rounded-xl border border-gray-100 bg-white shadow-sm lg:col-span-2">
            <style>
                /* --inv-head sits a shade lighter than --inv-navy: as a filled band it reads much heavier than
                   the same colour does as text, so matching them made the head dominate the page. */
                .inv-doc { --inv-navy: #16305c; --inv-head: #2e5288; --inv-blue: #2b5aa0; --inv-line: #e4e8ef; color: #1f2937; overflow: hidden; }
                .inv-doc .inv-pad { padding: 28px 32px; }
                .inv-label { font-size: 10px; font-weight: 700; color: var(--inv-blue); text-transform: uppercase; letter-spacing: .06em; }
                .inv-head { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 24px; }
                /* Bases small enough that all three columns fit one row inside the card; they
                   grow into the space. At 220px+ bases the meta box wrapped onto its own row. */
                .inv-head > div:first-child { flex: 1 1 150px; }
                .inv-head > .inv-billto { flex: 1 1 150px; text-align: center; padding-top: 4px; }
                .inv-head > div:last-child { flex: 0 0 auto; margin-left: auto; }
                .inv-contact { margin-top: 12px; font-size: 12.5px; color: #4b5563; }
                .inv-contact > div { display: flex; align-items: flex-start; gap: 8px; margin-top: 5px; }
                .inv-contact svg { width: 15px; height: 15px; flex: 0 0 15px; margin-top: 2px; color: var(--inv-blue); }
                .inv-title { font-size: 32px; font-weight: 800; letter-spacing: .02em; color: var(--inv-navy); line-height: 1; }
                .inv-rule { height: 3px; width: 120px; background: var(--inv-blue); margin: 6px 0 0 auto; }
                .inv-meta { border-collapse: collapse; margin: 16px 0 0 auto; font-size: 12px; }
                .inv-meta td { border: 1px solid var(--inv-line); padding: 8px 14px; }
                .inv-meta td.k { font-weight: 700; color: var(--inv-navy); }
                .inv-meta td.v { font-weight: 700; color: var(--inv-blue); }
                .inv-who { font-weight: 700; color: var(--inv-navy); margin-top: 6px; }

                .inv-table { border: 1px solid var(--inv-line); border-radius: 12px; overflow: hidden; }
                .inv-items { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: fixed; }
                .inv-items th { background: var(--inv-head); color: #fff; padding: 11px 8px; font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; border: none; }
                .inv-items th + th { border-left: 1px solid rgba(255, 255, 255, .14); }
                .inv-items td { border-top: 1px solid var(--inv-line); border-left: 1px solid var(--inv-line); padding: 11px 8px; vertical-align: middle; text-align: center; }
                .inv-items td:first-child { border-left: none; }
                .inv-items td.desc { text-align: left; vertical-align: top; }
                .inv-items .t { font-weight: 700; color: var(--inv-navy); }
                .inv-items .amt { font-weight: 700; color: var(--inv-navy); }
                .inv-detail { margin-top: 6px; font-size: 11.5px; line-height: 1.6; color: #4b5563; }
                .inv-list { margin: 8px 0 0; padding-left: 22px; list-style: decimal outside; font-size: 12px; line-height: 1.7; color: #4b5563; }
                .inv-list li { padding-left: 2px; }

                .inv-totals { width: 320px; margin-left: auto; border-collapse: collapse; font-size: 13px; }
                .inv-totals td { padding: 7px 12px; }
                .inv-totals td.r { text-align: right; }
                .inv-totals tr.line td { border-top: 1px solid var(--inv-line); font-weight: 700; color: var(--inv-navy); }
                .inv-totals tr.due td { background: #eef2f8; border-radius: 0; color: var(--inv-navy); font-weight: 700; font-size: 15px; padding: 11px 12px; }
                .inv-totals tr.due td:first-child { text-transform: uppercase; letter-spacing: .04em; }

                .inv-foot { display: flex; flex-wrap: wrap; gap: 28px; border-top: 1px solid #f3f4f6; font-size: 12px; line-height: 1.7; color: #4b5563; }
                .inv-foot > div { flex: 1 1 180px; }
                .inv-bank { border-collapse: collapse; margin-top: 4px; font-size: 12px; }
                .inv-bank td { padding: 1px 12px 1px 0; }
                .inv-thanks { border-top: 1px solid var(--inv-line); margin: 0 32px; padding: 14px 0 24px; text-align: center; color: var(--inv-blue); font-weight: 700; font-size: 13px; }
            </style>

            @php
                // Kept in step with the PDF by hand — both read from the same shape, so a change to
                // one is obvious in the other.
                $us = [
                    'name' => 'RazinSoft',
                    'email' => 'info@razinsoft.com',
                    'phone' => '+8801711257498',
                    'address' => ['RMR Center 1/1 (A&B) Shyamoli', 'Ring Road, Dhaka - 1207.', 'Bangladesh'],
                ];
                // Blank entries are dropped, so a detail we do not have never shows as an empty row.
                $bankName = 'Razinsoft Limited';
                $bank = array_filter([
                    'A/C:' => '1111070003744',
                    'Bank:' => 'Eastern Bank PLC',
                    'Branch:' => 'Shyamoli',
                    'Routing #:' => '095264301',
                    'Address:' => 'Shyamoli Ring Road',
                ], fn ($v) => filled($v));
                // Off for an invoice paid by card. The remaining columns just share the row.
                $showBank = $invoice->show_bank_details && ($bank || filled($bankName));
                $due = $invoice->amountDue();
                $paidOff = $due <= 0;
                $statusText = $paidOff ? 'PAID' : ($invoice->amount_paid > 0 ? 'PARTIALLY PAID' : 'UNPAID');
                $statusColour = $paidOff || $invoice->amount_paid > 0 ? '#047857' : '#dc2626';
                $num = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
            @endphp

            {{-- Header --}}
            <div class="inv-pad inv-head">
                <div>
                    <img src="{{ asset('images/razinsoft-logo.png') }}" alt="{{ $us['name'] }}" class="h-9 w-auto">
                    <div class="inv-contact">
                        <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/></svg>{{ $us['email'] }}</div>
                        <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1Z"/></svg>{{ $us['phone'] }}</div>
                        <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>{{ implode(', ', $us['address']) }}</div>
                    </div>
                </div>
                {{-- The client rides in the header's centre: from, to and what, one line of sight. --}}
                <div class="inv-billto">
                    <div class="inv-label">Billed To</div>
                    @if ($invoice->client)
                        <a href="{{ route('admin.clients.show', $invoice->client_id) }}" class="inv-who" style="display:block;font-size:14px;text-decoration:none">{{ $invoice->bill_to_name ?: $invoice->client->name }}</a>
                    @else
                        <div class="inv-who" style="font-size:14px">{{ $invoice->bill_to_name ?: '—' }}</div>
                    @endif
                    <div style="margin-top:2px;font-size:12.5px;line-height:1.65;color:#4b5563">
                        @if ($invoice->bill_to_company){{ $invoice->bill_to_company }}<br>@endif
                        @if ($invoice->bill_to_email){{ $invoice->bill_to_email }}<br>@endif
                        @if ($invoice->bill_to_phone){{ $invoice->bill_to_phone }}<br>@endif
                        @if ($invoice->bill_to_address){{ $invoice->bill_to_address }}@endif
                    </div>
                    @if ($invoice->due_date)
                        <div style="margin-top:7px;font-size:12px;color:var(--color-muted)">Due Date: <span style="font-weight:700;color:var(--inv-blue)">{{ $invoice->due_date->format('d F, Y') }}</span></div>
                    @endif
                </div>

                <div class="text-right">
                    <div class="inv-title">INVOICE</div>
                    <div class="inv-rule"></div>
                    <table class="inv-meta">
                        <tr><td class="k">Invoice Number</td><td class="v">{{ $invoice->invoice_number }}</td></tr>
                        <tr><td class="k">Invoice Date</td><td>{{ $invoice->invoice_date->format('d F, Y') }}</td></tr>
                        @if ($invoice->due_date)
                            <tr><td class="k">Due Date</td><td class="v">{{ $invoice->due_date->format('d F, Y') }}</td></tr>
                        @endif
                        <tr><td class="k">Payment Status</td><td style="font-weight:700;color:{{ $statusColour }}">{{ $statusText }}</td></tr>
                    </table>
                </div>
            </div>

            {{-- Items --}}
            <div style="padding:0 32px">
                <div class="inv-table overflow-x-auto">
                    <table class="inv-items">
                        <thead>
                            <tr>
                                <th style="width:43%;text-align:left">Description</th>
                                <th style="width:7%">Qty</th>
                                <th style="width:15%">Unit</th>
                                <th style="width:12%">Unit Price<br>({{ $invoice->currency }})</th>
                                <th style="width:10%">Tax<br>({{ $invoice->currency }})</th>
                                <th style="width:13%">Amount<br>({{ $invoice->currency }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                @php
                                    // Numbered once a list is long enough that a reader needs to keep
                                    // their place in it. The PDF sets the same list in two columns;
                                    // that is a page-height problem, and this page scrolls.
                                    $listItems = $item->subDescriptionItems();
                                    $numbered = count($listItems) > 8;
                                @endphp
                                <tr>
                                    <td class="desc">
                                        <div class="t">{{ $item->description }}</div>
                                        @if ($numbered)
                                            <ol class="inv-list">@foreach ($listItems as $line)<li>{!! $line !!}</li>@endforeach</ol>
                                        @elseif ($item->sub_description)
                                            <div class="invoice-subdesc inv-detail">{!! $item->formattedSubDescription() !!}</div>
                                        @endif
                                    </td>
                                    <td>{{ $num($item->qty) }}</td>
                                    <td>{{ $item->unit ?: '—' }}</td>
                                    <td>{{ number_format($item->unit_price, 2) }}</td>
                                    <td>{{ $item->tax_percent > 0 ? $num($item->tax_percent).'%' : '—' }}</td>
                                    <td class="amt">{{ number_format($item->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Totals --}}
            <div style="padding:16px 32px 0">
                <table class="inv-totals">
                    <tr><td style="color:var(--color-muted)">Sub Total</td><td class="r">{{ $cur }}{{ number_format($invoice->subtotal, 2) }}</td></tr>
                    @if ($invoice->discount_total > 0)
                        <tr><td style="color:var(--color-muted)">Discount{{ $invoice->discount_type === 'percent' && $invoice->discount_value > 0 ? ' ('.$num($invoice->discount_value).'%)' : '' }}</td><td class="r" style="color:#dc2626">−{{ $cur }}{{ number_format($invoice->discount_total, 2) }}</td></tr>
                    @endif
                    @if ($invoice->tax_total > 0)
                        <tr><td style="color:var(--color-muted)">Tax</td><td class="r">{{ $cur }}{{ number_format($invoice->tax_total, 2) }}</td></tr>
                    @endif
                    <tr class="line"><td>Total</td><td class="r">{{ $cur }}{{ number_format($invoice->total, 2) }}</td></tr>
                    @if ($invoice->amount_paid > 0)
                        <tr><td style="color:#047857;font-weight:700">Paid</td><td class="r" style="color:#047857;font-weight:700">−{{ $cur }}{{ number_format($invoice->amount_paid, 2) }}</td></tr>
                    @endif
                    <tr class="due"><td>Total Due</td><td class="r">{{ $cur }}{{ number_format($due, 2) }} {{ $invoice->currency }}</td></tr>
                </table>
            </div>

            {{-- Notes / Bank / Terms --}}
            <div class="inv-pad inv-foot" style="margin-top:24px">
                <div>
                    <div class="inv-label">Notes</div>
                    <div class="invoice-notes" style="margin-top:5px">{!! $invoice->notes ? $invoice->formattedNotes() : '—' !!}</div>
                </div>
                @if ($showBank)
                    <div>
                        <div class="inv-label">Bank Info</div>
                        @if (filled($bankName))
                            <div style="font-weight:700;color:var(--inv-navy);margin-top:5px">{{ $bankName }}</div>
                        @endif
                        <table class="inv-bank">
                            @foreach ($bank as $key => $value)
                                <tr><td style="color:var(--color-muted)">{{ $key }}</td><td>{{ $value }}</td></tr>
                            @endforeach
                        </table>
                    </div>
                @endif
                <div>
                    <div class="inv-label">Terms</div>
                    <div style="margin-top:5px">{!! $invoice->terms ? $invoice->formattedTerms() : '—' !!}</div>
                </div>
            </div>

            <div class="inv-thanks">Thank you for your business!</div>
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
