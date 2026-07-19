@extends('admin.layouts.app')
@section('title', 'Invoices')

@php
    $statusBadge = [
        'draft' => 'bg-gray-100 text-gray-600', 'sent' => 'bg-blue-50 text-blue-700',
        'partially_paid' => 'bg-amber-50 text-amber-700', 'paid' => 'bg-emerald-50 text-emerald-700', 'overdue' => 'bg-red-50 text-red-600',
        'cancelled' => 'bg-gray-100 text-gray-400 line-through',
    ];
    $cur = \App\Models\Currency::symbolMap();
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">All Invoices</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">{{ $invoices->total() }} invoice(s)</p>
        </div>
        @if (auth()->user()->allows('invoices', 'create'))
        <a href="{{ route('admin.invoices.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Create Invoice
        </a>
        @endif
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
                            <td class="px-5 py-3"><a href="{{ route('admin.invoices.show', $inv) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $inv->invoice_number }}</a></td>
                            <td class="px-5 py-3">
                                @if ($inv->client)
                                    <a href="{{ route('admin.clients.show', $inv->client_id) }}" class="font-medium text-[var(--color-primary)] hover:underline">{{ $inv->bill_to_name ?: $inv->client->name }}</a>
                                @else
                                    <p class="font-medium text-[var(--color-heading)]">{{ $inv->bill_to_name ?: '—' }}</p>
                                @endif
                                @if ($inv->bill_to_company)<p class="text-xs text-[var(--color-muted)]">{{ $inv->bill_to_company }}</p>@endif
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $inv->invoice_date->format('d M Y') }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $inv->due_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-5 py-3 text-right font-medium text-[var(--color-heading)]">{{ $cur[$inv->currency] ?? '' }}{{ number_format($inv->total, 2) }}</td>
                            <td class="px-5 py-3 text-right font-semibold {{ $inv->amountDue() > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $cur[$inv->currency] ?? '' }}{{ number_format($inv->amountDue(), 2) }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge[$inv->status] ?? 'bg-gray-100 text-gray-600' }}">{{ \App\Models\ClientInvoice::STATUSES[$inv->status] ?? $inv->status }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                @php
                                    $u = auth()->user();
                                    $mi = 'flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50';
                                    $miDanger = 'flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50';
                                @endphp
                                <div x-data="rowMenu()" class="relative inline-block">
                                    <button type="button" @click="toggle($event)" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Actions">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>

                                    <template x-teleport="body">
                                        <div x-show="open" x-cloak>
                                            <div class="fixed inset-0 z-50" @click="open = false"></div>
                                            <div x-ref="menu" :style="`position:fixed; top:${y}px; left:${x}px`" class="z-[60] max-h-[calc(100vh-1rem)] w-56 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-xl">
                                                <a href="{{ route('admin.invoices.show', $inv) }}" class="{{ $mi }}">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="2.5"/></svg> View
                                                </a>
                                                <a href="{{ route('admin.invoices.pdf', $inv) }}?download=1" class="{{ $mi }}">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 19h16"/></svg> Download
                                                </a>
                                                <a href="{{ route('admin.invoices.pdf', $inv) }}" target="_blank" class="{{ $mi }}">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="2.5"/></svg> View PDF
                                                </a>
                                                @if ($u->allows('invoices', 'send'))
                                                    <form method="POST" action="{{ route('admin.invoices.send', $inv) }}" onsubmit="return confirm('Email this invoice + payment link to the client?')">
                                                        @csrf
                                                        <button class="{{ $mi }}">
                                                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg> Send
                                                        </button>
                                                    </form>
                                                @endif
                                                @if ($u->allows('invoices', 'edit'))
                                                    <a href="{{ route('admin.invoices.edit', $inv) }}" class="{{ $mi }}">
                                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg> Edit
                                                    </a>
                                                @endif
                                                @if ($u->allows('invoices', 'finance'))
                                                    <a href="{{ route('admin.invoices.show', $inv) }}#add-payment" class="{{ $mi }}">
                                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Payment
                                                    </a>
                                                @endif
                                                @if ($u->allows('invoices', 'edit'))
                                                    <button type="button" @click="open = false; ship = true" class="{{ $mi }}">
                                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9h13v8H3zM16 12h3l2 3v2h-5M6.5 20a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm11 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg> Add Shipping Address
                                                    </button>
                                                @endif
                                                @if ($u->allows('invoices', 'cancel') && $inv->status !== 'cancelled')
                                                    <form method="POST" action="{{ route('admin.invoices.cancel', $inv) }}" onsubmit="return confirm('Cancel this invoice?')">
                                                        @csrf
                                                        <button class="{{ $mi }}">
                                                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg> Cancel
                                                        </button>
                                                    </form>
                                                @endif
                                                <div class="my-1 border-t border-gray-100"></div>
                                                <button type="button" @click="copyLink('{{ $inv->payUrl() }}')" class="{{ $mi }}">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1M10.5 13.5a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/></svg>
                                                    <span x-text="copied ? 'Copied!' : 'Copy Payment Link'"></span>
                                                </button>
                                                <a href="{{ $inv->payUrl() }}" target="_blank" class="{{ $mi }}">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3h7v7M21 3l-9 9M19 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"/></svg> View Payment Page
                                                </a>
                                                @if ($u->allows('invoices', 'send'))
                                                    <form method="POST" action="{{ route('admin.invoices.reminder', $inv) }}" onsubmit="return confirm('Send a payment reminder email to the client?')">
                                                        @csrf
                                                        <button class="{{ $mi }}">
                                                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg> Payment Reminder
                                                        </button>
                                                    </form>
                                                @endif
                                                @if ($u->allows('invoices', 'duplicate'))
                                                    <form method="POST" action="{{ route('admin.invoices.duplicate', $inv) }}">
                                                        @csrf
                                                        <button class="{{ $mi }}">
                                                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9h10v10H9zM5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg> Create Duplicate
                                                        </button>
                                                    </form>
                                                @endif
                                                @if ($u->allows('invoices', 'delete'))
                                                    <div class="my-1 border-t border-gray-100"></div>
                                                    <form method="POST" action="{{ route('admin.invoices.destroy', $inv) }}" onsubmit="return confirm('Delete this invoice permanently?')">
                                                        @csrf @method('DELETE')
                                                        <button class="{{ $miDanger }}">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg> Delete
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Add Shipping Address modal --}}
                                    <template x-teleport="body">
                                        <div x-show="ship" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4" @click.self="ship = false">
                                            <form method="POST" action="{{ route('admin.invoices.shipping-address', $inv) }}" class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl text-left">
                                                @csrf
                                                <h3 class="text-base font-bold text-[var(--color-heading)]">Shipping Address — {{ $inv->invoice_number }}</h3>
                                                <textarea name="shipping_address" rows="4" placeholder="Shipping address" class="mt-4 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">{{ $inv->shipping_address }}</textarea>
                                                <div class="mt-4 flex justify-end gap-2">
                                                    <button type="button" @click="ship = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                                                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </template>
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

    <style>[x-cloak]{display:none!important}</style>
    <script>
        function rowMenu() {
            return {
                open: false, ship: false, copied: false, x: 0, y: 0,
                toggle(e) {
                    if (this.open) { this.open = false; return; }
                    const r = e.currentTarget.getBoundingClientRect();
                    this.x = Math.max(8, r.right - 224);           // 224 = w-56
                    this.y = r.bottom + 4;                          // rough — refined below
                    this.open = true;
                    // After the menu renders, measure it and keep it inside the viewport:
                    // open upward (or clamp up) when there isn't enough room below.
                    this.$nextTick(() => {
                        const m = this.$refs.menu;
                        if (!m) return;
                        const h = m.offsetHeight, vh = window.innerHeight;
                        if (r.bottom + 4 + h > vh - 8) {
                            const above = r.top - 4 - h;
                            this.y = above >= 8 ? above : Math.max(8, vh - h - 8);
                        } else {
                            this.y = r.bottom + 4;
                        }
                    });
                },
                async copyLink(url) {
                    try { await navigator.clipboard.writeText(url); }
                    catch (e) { const t = document.createElement('textarea'); t.value = url; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); }
                    this.copied = true; setTimeout(() => { this.copied = false; this.open = false; }, 1000);
                },
            };
        }
    </script>

    @if (session('status') || session('error'))
        <div class="fixed bottom-5 right-5 z-[80] rounded-lg px-4 py-3 text-sm font-medium shadow-lg {{ session('error') ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white' }}"
             x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-cloak>
            {{ session('error') ?? session('status') }}
        </div>
    @endif
@endsection
