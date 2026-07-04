@extends('admin.layouts.app')
@section('title', $invoice->exists ? 'Edit Invoice' : 'Create Invoice')

@php
    $clientsJson = $clients->keyBy('id')->map(fn ($c) => [
        'id' => $c->id, 'name' => $c->name, 'company' => $c->company, 'email' => $c->email, 'phone' => $c->phone,
        'address' => collect([$c->address, $c->city, $c->state, $c->country, $c->zip])->filter()->join(', '),
    ]);
    $initialItems = count($items) ? $items : [['description' => '', 'sub_description' => '', 'qty' => 1, 'unit_price' => 0, 'discount_percent' => 0, 'tax_percent' => 0]];
@endphp

@section('content')
<div x-data="invoiceForm({
        clients: {{ Illuminate\Support\Js::from($clientsJson) }},
        items: {{ Illuminate\Support\Js::from($initialItems) }},
        clientId: '{{ old('client_id', $invoice->client_id) }}',
        currency: '{{ old('currency', $invoice->currency ?? 'USD') }}',
        invoiceNumber: '{{ $invoice->invoice_number }}',
        invoiceDate: '{{ old('invoice_date', optional($invoice->invoice_date)->toDateString() ?? now()->toDateString()) }}',
        dueDate: '{{ old('due_date', optional($invoice->due_date)->toDateString()) }}',
        amountPaid: {{ (float) ($invoice->amount_paid ?? 0) }},
        status: '{{ $invoice->status === 'draft' || ! $invoice->exists ? 'draft' : 'sent' }}',
    })">

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $invoice->exists ? 'Edit Invoice' : 'Create Invoice' }}</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; Invoices &rsaquo; {{ $invoice->exists ? 'Edit' : 'Create' }}</p>
        </div>
        <a href="{{ route('admin.invoices.index') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Back to Invoices</a>
    </div>

    <form method="POST" action="{{ $invoice->exists ? route('admin.invoices.update', $invoice) : route('admin.invoices.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($invoice->exists) @method('PUT') @endif
        <input type="hidden" name="status" :value="status">

        <div class="grid gap-6 lg:grid-cols-[1fr_400px]">
            {{-- ============ LEFT: form ============ --}}
            <div class="space-y-6">
                {{-- 1. Invoice & Customer --}}
                <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]">1. Invoice &amp; Customer Details</h2>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Customer</label>
                            <select name="client_id" x-model="clientId" @change="pickClient()" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                <option value="">Walk-in / no account</option>
                                @foreach ($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}{{ $c->company ? ' — '.$c->company : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Invoice Number</label>
                            <input type="text" value="{{ $invoice->invoice_number }}" readonly class="h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm text-[var(--color-muted)]">
                            <p class="mt-1 text-xs text-[var(--color-muted)]">Auto generated</p>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-5 sm:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Invoice Date <span class="text-red-500">*</span></label>
                            <input type="date" name="invoice_date" x-model="invoiceDate" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Due Date</label>
                            <input type="date" name="due_date" x-model="dueDate" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Currency <span class="text-red-500">*</span></label>
                            <select name="currency" x-model="currency" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                @foreach (\App\Models\Currency::options() as $c)
                                    <option value="{{ $c->code }}">{{ $c->code }} ({{ $c->symbol }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                {{-- 2. Items --}}
                <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]">2. Invoice Items</h2>
                    <div class="space-y-3">
                        <template x-for="(item, idx) in items" :key="idx">
                            <div class="grid grid-cols-12 gap-2 rounded-lg border border-gray-100 p-3">
                                <div class="col-span-12 sm:col-span-4">
                                    <input type="text" :name="`items[${idx}][description]`" x-model="item.description" placeholder="Item / Description" required class="mb-1 h-9 w-full rounded-lg border border-gray-200 px-2 text-sm">
                                    <input type="text" :name="`items[${idx}][sub_description]`" x-model="item.sub_description" placeholder="Sub-description (optional)" class="h-8 w-full rounded-lg border border-gray-100 px-2 text-xs text-[var(--color-muted)]">
                                </div>
                                <div class="col-span-3 sm:col-span-1"><label class="text-[10px] text-gray-400 sm:hidden">Qty</label><input type="number" step="0.01" min="0" :name="`items[${idx}][qty]`" x-model.number="item.qty" class="h-9 w-full rounded-lg border border-gray-200 px-2 text-sm" placeholder="Qty"></div>
                                <div class="col-span-4 sm:col-span-2"><label class="text-[10px] text-gray-400 sm:hidden">Unit Price</label><input type="number" step="0.01" min="0" :name="`items[${idx}][unit_price]`" x-model.number="item.unit_price" class="h-9 w-full rounded-lg border border-gray-200 px-2 text-sm" placeholder="Price"></div>
                                <div class="col-span-2 sm:col-span-1"><label class="text-[10px] text-gray-400 sm:hidden">Disc%</label><input type="number" step="0.01" min="0" max="100" :name="`items[${idx}][discount_percent]`" x-model.number="item.discount_percent" class="h-9 w-full rounded-lg border border-gray-200 px-2 text-sm" placeholder="0"></div>
                                <div class="col-span-3 sm:col-span-2"><label class="text-[10px] text-gray-400 sm:hidden">Tax%</label><input type="number" step="0.01" min="0" max="100" :name="`items[${idx}][tax_percent]`" x-model.number="item.tax_percent" class="h-9 w-full rounded-lg border border-gray-200 px-2 text-sm" placeholder="0"></div>
                                <div class="col-span-9 flex items-center sm:col-span-1"><span class="text-sm font-semibold text-[var(--color-heading)]" x-text="money(lineAmount(item))"></span></div>
                                <div class="col-span-3 flex items-center justify-end sm:col-span-1">
                                    <button type="button" @click="removeItem(idx)" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" x-show="items.length > 1">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addItem()" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-semibold text-[var(--color-primary)] hover:bg-[var(--color-primary-soft)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Item
                    </button>
                </section>

                {{-- 3. Additional --}}
                <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]">3. Additional Information</h2>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-admin.field label="Notes" name="notes" type="textarea" rows="3" :value="$invoice->notes" placeholder="Thank you note to the client…" />
                        <x-admin.field label="Terms &amp; Conditions" name="terms" type="textarea" rows="3" :value="$invoice->terms" placeholder="Payment terms…" />
                    </div>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Attachment</label>
                            <input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.png" class="text-sm text-[var(--color-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                            <p class="mt-1 text-xs text-[var(--color-muted)]">PDF, DOC, JPG, PNG (max 5MB).</p>
                        </div>
                        <x-admin.field label="Payment Method" name="payment_method" type="select" :value="$invoice->payment_method ?? 'Bank Transfer'" :options="array_combine(\App\Models\ClientInvoice::PAYMENT_METHODS, \App\Models\ClientInvoice::PAYMENT_METHODS)" />
                    </div>
                </section>
            </div>

            {{-- ============ RIGHT: live preview ============ --}}
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <span class="text-lg font-extrabold text-[var(--color-primary)]">RazinSoft</span>
                        <span class="rounded bg-[var(--color-primary-soft)] px-3 py-1 text-xs font-bold text-[var(--color-primary)]">INVOICE</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <div class="text-[var(--color-muted)]">RazinSoft Ltd.<br>support@razinsoft.com</div>
                        <div class="text-right">
                            <p class="font-bold text-[var(--color-heading)]" x-text="invoiceNumber"></p>
                            <p class="text-[var(--color-muted)]">Date: <span x-text="invoiceDate"></span></p>
                            <p class="text-[var(--color-muted)]" x-show="dueDate">Due: <span x-text="dueDate"></span></p>
                        </div>
                    </div>
                    <div class="mt-4 border-t border-gray-100 pt-3 text-xs">
                        <p class="mb-1 font-semibold text-gray-400">Bill To:</p>
                        <p class="font-semibold text-[var(--color-heading)]" x-text="bill.name || '—'"></p>
                        <p class="text-[var(--color-muted)]" x-show="bill.company" x-text="bill.company"></p>
                        <p class="text-[var(--color-muted)]" x-show="bill.email" x-text="bill.email"></p>
                        <p class="text-[var(--color-muted)]" x-show="bill.address" x-text="bill.address"></p>
                    </div>
                    <table class="mt-4 w-full text-xs">
                        <thead><tr class="border-b border-gray-100 text-gray-400">
                            <th class="py-1 text-left font-semibold">Item</th><th class="py-1 text-right font-semibold">Qty</th><th class="py-1 text-right font-semibold">Amount</th>
                        </tr></thead>
                        <tbody>
                            <template x-for="(item, idx) in items" :key="idx">
                                <tr class="border-b border-gray-50">
                                    <td class="py-1.5 text-[var(--color-heading)]" x-text="item.description || 'Item'"></td>
                                    <td class="py-1.5 text-right text-[var(--color-muted)]" x-text="item.qty || 0"></td>
                                    <td class="py-1.5 text-right text-[var(--color-heading)]" x-text="money(lineAmount(item))"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div class="mt-3 space-y-1 border-t border-gray-100 pt-3 text-xs">
                        <div class="flex justify-between"><span class="text-[var(--color-muted)]">Subtotal</span><span x-text="money(totals.subtotal)"></span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-muted)]">Discount</span><span x-text="'-' + money(totals.discount)"></span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-muted)]">Tax</span><span x-text="money(totals.tax)"></span></div>
                        <div class="mt-1 flex justify-between border-t border-gray-100 pt-2 text-sm font-bold text-[var(--color-heading)]"><span>Total Due</span><span x-text="money(totals.due) + ' ' + currency"></span></div>
                        <div class="flex justify-between text-[var(--color-muted)]" x-show="amountPaid > 0"><span>Paid</span><span x-text="money(amountPaid)"></span></div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="submit" @click="status='draft'" class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Save Draft</button>
                    <button type="submit" @click="status='sent'" class="flex-1 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save Invoice</button>
                </div>
            </aside>
        </div>

        @if ($errors->any())
            <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
    </form>
</div>

<script>
function invoiceForm(cfg) {
    return {
        clients: cfg.clients, items: cfg.items, clientId: cfg.clientId, currency: cfg.currency,
        invoiceNumber: cfg.invoiceNumber, invoiceDate: cfg.invoiceDate, dueDate: cfg.dueDate, amountPaid: cfg.amountPaid,
        bill: { name: '', company: '', email: '', phone: '', address: '' },
        init() { this.pickClient(); },
        pickClient() {
            const c = this.clients[this.clientId];
            this.bill = c ? { name: c.name, company: c.company, email: c.email, phone: c.phone, address: c.address } : { name: '', company: '', email: '', phone: '', address: '' };
        },
        addItem() { this.items.push({ description: '', sub_description: '', qty: 1, unit_price: 0, discount_percent: 0, tax_percent: 0 }); },
        removeItem(i) { this.items.splice(i, 1); },
        lineAmount(item) {
            const gross = (parseFloat(item.qty) || 0) * (parseFloat(item.unit_price) || 0);
            const net = gross - gross * ((parseFloat(item.discount_percent) || 0) / 100);
            return net;
        },
        get totals() {
            let subtotal = 0, discount = 0, tax = 0;
            for (const it of this.items) {
                const gross = (parseFloat(it.qty) || 0) * (parseFloat(it.unit_price) || 0);
                const d = gross * ((parseFloat(it.discount_percent) || 0) / 100);
                const net = gross - d;
                subtotal += gross; discount += d; tax += net * ((parseFloat(it.tax_percent) || 0) / 100);
            }
            const total = subtotal - discount + tax;
            return { subtotal, discount, tax, total, due: total - (this.amountPaid || 0) };
        },
        money(v) { return (parseFloat(v) || 0).toFixed(2); },
    };
}
</script>
@endsection
