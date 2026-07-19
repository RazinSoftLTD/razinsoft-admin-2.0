@extends('admin.layouts.app')
@section('title', $invoice->exists ? 'Edit Invoice' : 'Create Invoice')

@php
    $clientsJson = $clients->keyBy('id')->map(fn ($c) => [
        'id' => $c->id, 'name' => $c->name, 'company' => $c->company, 'email' => $c->email, 'phone' => $c->phone,
        'address' => collect([$c->address, $c->city, $c->state, $c->country, $c->zip])->filter()->join(', '),
    ]);
    $unitNames = collect($units)->pluck('name')->values();
    $taxesJson = collect($taxes)->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'rate' => (float) $t->rate, 'label' => $t->label]);
    $initialItems = count($items) ? $items : [[
        'description' => '', 'sub_description' => '', 'qty' => 1, 'unit' => $defaultUnit,
        'unit_price' => 0, 'discount_percent' => 0, 'taxIds' => [], 'attachment' => null,
    ]];
@endphp

@section('content')
<div x-data="invoiceForm({
        clients: {{ Illuminate\Support\Js::from($clientsJson) }},
        items: {{ Illuminate\Support\Js::from($initialItems) }},
        units: {{ Illuminate\Support\Js::from($unitNames) }},
        taxes: {{ Illuminate\Support\Js::from($taxesJson) }},
        defaultUnit: {{ Illuminate\Support\Js::from($defaultUnit) }},
        clientId: '{{ old('client_id', $invoice->client_id) }}',
        currency: '{{ old('currency', $invoice->currency ?? 'USD') }}',
        invoiceNumber: '{{ $invoice->invoice_number }}',
        invoiceDate: '{{ old('invoice_date', optional($invoice->invoice_date)->toDateString() ?? now()->toDateString()) }}',
        dueDate: '{{ old('due_date', optional($invoice->due_date)->toDateString()) }}',
        amountPaid: {{ (float) ($invoice->amount_paid ?? 0) }},
        status: '{{ $invoice->status === 'draft' || ! $invoice->exists ? 'draft' : 'sent' }}',
        discountType: '{{ old('discount_type', $invoice->discount_type ?? '') }}',
        discountValue: {{ (float) old('discount_value', $invoice->discount_value ?? 0) }},
        notes: {{ Illuminate\Support\Js::from(old('notes', $invoice->notes ?? '')) }},
        terms: {{ Illuminate\Support\Js::from(old('terms', $invoice->terms ?? '')) }},
    })">

    <form method="POST" action="{{ $invoice->exists ? route('admin.invoices.update', $invoice) : route('admin.invoices.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($invoice->exists) @method('PUT') @endif
        <input type="hidden" name="status" :value="status">

        {{-- Top bar: title + all actions on one line --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $invoice->exists ? 'Edit Invoice' : 'Create Invoice' }}</h1>
                <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; Invoices &rsaquo; {{ $invoice->exists ? 'Edit' : 'Create' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.invoices.index') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Back to Invoices</a>
                <button type="submit" @click="status='draft'" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Save Draft</button>
                <button type="submit" @click="status='sent'" class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save Invoice</button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_400px]">
            {{-- ============ LEFT: form ============ --}}
            <div class="space-y-6">
                {{-- 1. Invoice & Client --}}
                <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]">1. Invoice &amp; Client Details</h2>
                    {{-- First line: Invoice Number + Client --}}
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Invoice Number</label>
                            <input type="text" value="{{ $invoice->invoice_number }}" readonly class="h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm text-[var(--color-muted)]">
                            <p class="mt-1 text-xs text-[var(--color-muted)]">Auto generated</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Client <span class="text-red-500">*</span></label>
                            <div class="flex gap-2">
                                <select name="client_id" x-model="clientId" x-ref="clientSelect" @change="pickClient()" required class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                    <option value="" disabled>Select a client…</option>
                                    @foreach ($clients as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}{{ $c->company ? ' — '.$c->company : '' }}</option>
                                    @endforeach
                                </select>
                                <button type="button" @click="qa.open = true" class="h-11 shrink-0 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Add</button>
                            </div>
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
                    <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">2. Invoice Items</h2>

                    {{-- Column headers (desktop) --}}
                    <div class="mb-2 hidden grid-cols-12 gap-2 px-3 text-[11px] font-semibold uppercase tracking-wide text-gray-400 md:grid">
                        <div class="col-span-5">Description</div>
                        <div class="col-span-2">Quantity</div>
                        <div class="col-span-2 text-right">Unit Price</div>
                        <div class="col-span-2">Tax</div>
                        <div class="col-span-1 text-right">Amount</div>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(item, idx) in items" :key="idx">
                            <div class="relative rounded-lg border border-gray-100 bg-gray-50/40 p-3 pr-9"
                                 @dragover.prevent @drop="drop(idx)">
                                {{-- delete (top) + drag handle (below), small, far right --}}
                                <div class="absolute right-1.5 top-2 flex flex-col items-center gap-1.5">
                                    <button type="button" @click="removeItem(idx)" class="text-gray-300 hover:text-red-600" x-show="items.length > 1" title="Remove">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9.17 9.17 12 12m0 0 2.83 2.83M12 12l2.83-2.83M12 12l-2.83 2.83M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    </button>
                                    <span draggable="true" @dragstart="dragFrom = idx" class="cursor-move text-gray-300 hover:text-gray-500" title="Drag to reorder">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M8 15l4 4 4-4"/></svg>
                                    </span>
                                </div>

                                <div class="grid grid-cols-12 items-start gap-2">
                                    {{-- description (far left, wide) --}}
                                    <div class="col-span-12 md:col-span-5">
                                        <input type="text" :name="`items[${idx}][description]`" x-model="item.description" placeholder="Description" required class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                    </div>
                                    {{-- quantity + unit --}}
                                    <div class="col-span-3 md:col-span-2">
                                        <input type="number" step="0.01" min="0" :name="`items[${idx}][qty]`" x-model.number="item.qty" class="h-10 w-full rounded-lg border border-gray-200 px-2 text-right text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="1">
                                        <select :name="`items[${idx}][unit]`" x-model="item.unit" class="mt-1 h-7 w-full rounded-lg border border-gray-100 bg-white px-1 text-xs text-[var(--color-muted)]">
                                            <template x-for="u in units" :key="u"><option :value="u" x-text="u"></option></template>
                                        </select>
                                    </div>
                                    {{-- unit price --}}
                                    <div class="col-span-3 md:col-span-2">
                                        <input type="number" step="0.01" min="0" :name="`items[${idx}][unit_price]`" x-model.number="item.unit_price" class="h-10 w-full rounded-lg border border-gray-200 px-2 text-right text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="0">
                                    </div>
                                    {{-- tax multi-select --}}
                                    <div class="col-span-4 md:col-span-2">
                                        <div class="relative" x-data="{ openTax: false }" @click.outside="openTax = false">
                                            <button type="button" @click="openTax = !openTax" class="flex h-10 w-full items-center justify-between gap-1 rounded-lg border border-gray-200 bg-white px-2 text-left text-sm text-[var(--color-heading)]">
                                                <span class="truncate text-xs" :class="!item.taxIds.length && 'text-gray-400'" x-text="taxLabel(item)"></span>
                                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                                            </button>
                                            <div x-show="openTax" x-cloak class="absolute z-30 mt-1 max-h-56 w-64 overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                                <template x-if="!taxes.length"><p class="px-3 py-2 text-xs text-gray-400">No taxes configured.</p></template>
                                                <template x-for="t in taxes" :key="t.id">
                                                    <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50">
                                                        <input type="checkbox" :value="t.id" :checked="item.taxIds.includes(t.id)" @change="toggleTax(item, t.id)" class="accent-[var(--color-primary)]">
                                                        <span x-text="t.label"></span>
                                                    </label>
                                                </template>
                                            </div>
                                            <template x-for="tid in item.taxIds" :key="tid">
                                                <input type="hidden" :name="`items[${idx}][taxes][]`" :value="tid">
                                            </template>
                                        </div>
                                    </div>
                                    {{-- amount --}}
                                    <div class="col-span-2 flex items-center pt-2.5 md:col-span-1 md:justify-end">
                                        <span class="text-sm font-semibold text-[var(--color-heading)]" x-text="money(lineAmount(item))"></span>
                                    </div>
                                </div>

                                {{-- sub-description (rich, resizable) --}}
                                <div class="mt-2">
                                    <div x-data="{}" class="rounded-lg border border-gray-100 bg-white">
                                        <div class="flex items-center gap-1 border-b border-gray-100 px-2 py-1">
                                            <button type="button" @mousedown.prevent="window.RF.exec($refs.editor, 'bold')" class="grid h-6 w-6 place-items-center rounded text-xs font-bold text-[var(--color-muted)] hover:bg-gray-100" title="Bold">B</button>
                                            <button type="button" @mousedown.prevent="window.RF.exec($refs.editor, 'italic')" class="grid h-6 w-6 place-items-center rounded text-xs italic text-[var(--color-muted)] hover:bg-gray-100" title="Italic">I</button>
                                            <button type="button" @mousedown.prevent="window.RF.exec($refs.editor, 'underline')" class="grid h-6 w-6 place-items-center rounded text-xs underline text-[var(--color-muted)] hover:bg-gray-100" title="Underline">U</button>
                                            <button type="button" @mousedown.prevent="window.RF.exec($refs.editor, 'insertUnorderedList')" class="grid h-6 w-6 place-items-center rounded text-[var(--color-muted)] hover:bg-gray-100" title="Bullet list">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                                            </button>
                                            <span class="ml-auto text-[10px] text-gray-300">Sub-description</span>
                                        </div>
                                        <div x-ref="editor" contenteditable="true"
                                             x-init="$el.innerHTML = window.RF.toHtml(item.sub_description)"
                                             @input="item.sub_description = $refs.editor.innerHTML"
                                             @keydown="window.RF.enter($event, $refs.editor)"
                                             @paste="window.RF.paste($event, $refs.editor)"
                                             data-ph="Enter Description (optional)"
                                             class="rich-editor min-h-[56px] resize-y overflow-auto px-3 py-2 text-sm text-[var(--color-heading)] focus:outline-none"></div>
                                    </div>
                                    <input type="hidden" :name="`items[${idx}][sub_description]`" :value="item.sub_description">
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
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Notes</label>
                            <div x-data="{}" class="rounded-lg border border-gray-200 bg-white">
                                <div class="flex items-center gap-1 border-b border-gray-100 px-2 py-1">
                                    <button type="button" @mousedown.prevent="window.RF.exec($refs.noteEditor, 'bold')" class="grid h-6 w-6 place-items-center rounded text-xs font-bold text-[var(--color-muted)] hover:bg-gray-100" title="Bold">B</button>
                                    <button type="button" @mousedown.prevent="window.RF.exec($refs.noteEditor, 'italic')" class="grid h-6 w-6 place-items-center rounded text-xs italic text-[var(--color-muted)] hover:bg-gray-100" title="Italic">I</button>
                                    <button type="button" @mousedown.prevent="window.RF.exec($refs.noteEditor, 'underline')" class="grid h-6 w-6 place-items-center rounded text-xs underline text-[var(--color-muted)] hover:bg-gray-100" title="Underline">U</button>
                                    <button type="button" @mousedown.prevent="window.RF.exec($refs.noteEditor, 'insertUnorderedList')" class="grid h-6 w-6 place-items-center rounded text-[var(--color-muted)] hover:bg-gray-100" title="Bullet list">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                                    </button>
                                </div>
                                <div x-ref="noteEditor" contenteditable="true"
                                     x-init="$el.innerHTML = window.RF.toHtml(notes)"
                                     @input="notes = $refs.noteEditor.innerHTML"
                                     @keydown="window.RF.enter($event, $refs.noteEditor)"
                                     @paste="window.RF.paste($event, $refs.noteEditor)"
                                     data-ph="Thank you note to the client…"
                                     class="rich-editor min-h-[76px] resize-y overflow-auto px-3 py-2 text-sm text-[var(--color-heading)] focus:outline-none"></div>
                            </div>
                            <input type="hidden" name="notes" :value="notes">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Terms &amp; Conditions</label>
                            <textarea name="terms" rows="3" x-model="terms" placeholder="Payment terms…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none"></textarea>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Attachment</label>
                            <input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.png" class="text-sm text-[var(--color-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                            <p class="mt-1 text-xs text-[var(--color-muted)]">PDF, DOC, JPG, PNG (max 5MB).</p>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ============ RIGHT: live preview ============ --}}
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        @if ($branding->logo_url)
                            <img src="{{ $branding->logo_url }}" alt="{{ $branding->brand_name }}" class="h-8 max-w-[140px] object-contain">
                        @else
                            <span class="text-lg font-extrabold text-[var(--color-primary)]">{{ $branding->brand_name ?? 'RazinSoft' }}</span>
                        @endif
                        <span class="rounded bg-[var(--color-primary-soft)] px-3 py-1 text-xs font-bold text-[var(--color-primary)]">INVOICE</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <div class="text-[var(--color-muted)]">{{ $branding->brand_name ?? 'RazinSoft' }}<br>support@razinsoft.com</div>
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
                                    <td class="py-1.5 text-right text-[var(--color-muted)]"><span x-text="item.qty || 0"></span> <span class="text-gray-300" x-text="item.unit"></span></td>
                                    <td class="py-1.5 text-right text-[var(--color-heading)]" x-text="money(lineAmount(item))"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div class="mt-3 space-y-1.5 border-t border-gray-100 pt-3 text-xs">
                        <div class="flex justify-between"><span class="text-[var(--color-muted)]">Subtotal</span><span x-text="money(totals.subtotal)"></span></div>
                        {{-- Discount: right after Subtotal, with its own type + amount inputs --}}
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[var(--color-muted)]">Discount</span>
                            <div class="flex items-center gap-1.5">
                                <select name="discount_type" x-model="discountType" class="h-7 rounded-md border border-gray-200 bg-white px-1.5 text-[11px] text-[var(--color-heading)] focus:border-[var(--color-primary)] focus:outline-none">
                                    <option value="">None</option>
                                    <option value="flat">Flat</option>
                                    <option value="percent">%</option>
                                </select>
                                <input type="number" name="discount_value" x-model="discountValue" x-show="discountType" x-cloak min="0" :max="discountType === 'percent' ? 100 : null" step="0.01" :placeholder="discountType === 'percent' ? '%' : currency" class="h-7 w-20 rounded-md border border-gray-200 px-1.5 text-right text-[11px] focus:border-[var(--color-primary)] focus:outline-none">
                                <span class="font-semibold text-emerald-600" x-show="totals.discount > 0" x-cloak x-text="'-' + money(totals.discount)"></span>
                            </div>
                        </div>
                        <div class="flex justify-between"><span class="text-[var(--color-muted)]">Tax</span><span x-text="money(totals.tax)"></span></div>
                        <div class="mt-1 flex justify-between border-t border-gray-100 pt-2 text-sm font-bold text-[var(--color-heading)]"><span>Total Due</span><span x-text="money(totals.due) + ' ' + currency"></span></div>
                        <div class="flex justify-between text-[var(--color-muted)]" x-show="amountPaid > 0"><span>Paid</span><span x-text="money(amountPaid)"></span></div>
                    </div>
                    {{-- Notes & Terms live preview --}}
                    <div class="mt-4 space-y-3 border-t border-gray-100 pt-3 text-xs" x-show="notes || terms" x-cloak>
                        <div x-show="notes">
                            <p class="mb-0.5 font-semibold text-gray-400">Notes</p>
                            <div class="rich-html text-[var(--color-muted)]" x-html="notes"></div>
                        </div>
                        <div x-show="terms">
                            <p class="mb-0.5 font-semibold text-gray-400">Terms &amp; Conditions</p>
                            <p class="whitespace-pre-line text-[var(--color-muted)]" x-text="terms"></p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        @if ($errors->any())
            <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
    </form>

    {{-- Quick "Add new client" modal --}}
    <div x-show="qa.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="qa.open = false">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 class="text-base font-bold text-[var(--color-heading)]">Add new client</h3>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Create a client without leaving this invoice.</p>
            <div class="mt-4 space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Client Name <span class="text-red-500">*</span></label>
                    <input type="text" x-model="qa.name" placeholder="e.g. John Doe" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Email <span class="text-red-500">*</span></label>
                    <input type="email" x-model="qa.email" placeholder="e.g. john@example.com" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Company Name</label>
                    <input type="text" x-model="qa.company" placeholder="e.g. Acme Corporation" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
                <p x-show="qa.error" x-cloak class="text-sm text-red-600" x-text="qa.error"></p>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="qa.open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                    <button type="button" @click="quickAddSave()" :disabled="qa.saving" class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)] disabled:opacity-60">
                        <span x-text="qa.saving ? 'Saving…' : 'Add client'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak]{display:none!important}
    .rich-editor:empty:before{content:attr(data-ph);color:#9ca3af}
    .rich-editor ul,.rich-html ul{list-style:disc;margin-left:1.25rem}
    .rich-html b,.rich-html strong{font-weight:600}
</style>

<script>
// Shared inline rich-text editor for sub-descriptions & notes.
// Enter inserts a <br> (not a <div>) and bold uses <b> (styleWithCSS off) so the
// stored markup stays clean inline HTML — this is what stopped "bold breaks the line".
window.RF = {
    _ready: false,
    _prep() { if (this._ready) return; try { document.execCommand('styleWithCSS', false, false); } catch (e) {} this._ready = true; },
    exec(editor, cmd) { this._prep(); editor.focus(); document.execCommand(cmd, false, null); editor.dispatchEvent(new Event('input')); },
    enter(e, editor) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this._prep(); document.execCommand('insertLineBreak'); editor.dispatchEvent(new Event('input')); } },
    paste(e, editor) {
        e.preventDefault();
        const t = ((e.clipboardData || window.clipboardData).getData('text/plain') || '');
        const html = t.replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])).replace(/\r?\n/g, '<br>');
        document.execCommand('insertHTML', false, html);
        editor.dispatchEvent(new Event('input'));
    },
    // Existing values may be plain text (old invoices) — turn newlines into <br> so they render.
    toHtml(v) {
        v = v || '';
        return v.indexOf('<') === -1
            ? v.replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])).replace(/\n/g, '<br>')
            : v;
    },
};
function invoiceForm(cfg) {
    return {
        clients: cfg.clients, items: cfg.items, units: cfg.units, taxes: cfg.taxes, defaultUnit: cfg.defaultUnit,
        clientId: cfg.clientId, currency: cfg.currency,
        invoiceNumber: cfg.invoiceNumber, invoiceDate: cfg.invoiceDate, dueDate: cfg.dueDate, amountPaid: cfg.amountPaid, status: cfg.status,
        discountType: cfg.discountType || '', discountValue: cfg.discountValue || 0,
        notes: cfg.notes || '', terms: cfg.terms || '',
        bill: { name: '', company: '', email: '', phone: '', address: '' },
        dragFrom: null,
        qa: { open: false, name: '', email: '', company: '', saving: false, error: '' },
        init() { this.pickClient(); },
        pickClient() {
            const c = this.clients[this.clientId];
            this.bill = c ? { name: c.name, company: c.company, email: c.email, phone: c.phone, address: c.address } : { name: '', company: '', email: '', phone: '', address: '' };
        },
        // ---- items ----
        addItem() { this.items.push({ description: '', sub_description: '', qty: 1, unit: this.defaultUnit, unit_price: 0, discount_percent: 0, taxIds: [], attachment: null }); },
        removeItem(i) { this.items.splice(i, 1); },
        drop(to) { if (this.dragFrom === null || this.dragFrom === to) return; const [m] = this.items.splice(this.dragFrom, 1); this.items.splice(to, 0, m); this.dragFrom = null; },
        toggleTax(item, id) { const i = item.taxIds.indexOf(id); if (i === -1) item.taxIds.push(id); else item.taxIds.splice(i, 1); },
        taxLabel(item) {
            if (!item.taxIds.length) return 'Nothing selected';
            const names = item.taxIds.map(id => (this.taxes.find(t => t.id === id) || {}).name).filter(Boolean);
            return names.length > 1 ? names.length + ' selected' : names[0];
        },
        lineRate(item) { return item.taxIds.reduce((s, id) => s + ((this.taxes.find(t => t.id === id) || {}).rate || 0), 0); },
        lineAmount(item) {
            const gross = (parseFloat(item.qty) || 0) * (parseFloat(item.unit_price) || 0);
            return gross - gross * ((parseFloat(item.discount_percent) || 0) / 100);
        },
        // Invoice-level discount (flat or % of the item net), mirroring the server-side computation.
        invoiceDiscount() {
            let subtotal = 0, lineDiscount = 0;
            for (const it of this.items) {
                const gross = (parseFloat(it.qty) || 0) * (parseFloat(it.unit_price) || 0);
                subtotal += gross;
                lineDiscount += gross * ((parseFloat(it.discount_percent) || 0) / 100);
            }
            const net = subtotal - lineDiscount;
            const v = Math.max(0, parseFloat(this.discountValue) || 0);
            if (this.discountType === 'percent') return net * Math.min(100, v) / 100;
            if (this.discountType === 'flat') return Math.min(v, net);
            return 0;
        },
        get totals() {
            let subtotal = 0, discount = 0, tax = 0;
            for (const it of this.items) {
                const gross = (parseFloat(it.qty) || 0) * (parseFloat(it.unit_price) || 0);
                const d = gross * ((parseFloat(it.discount_percent) || 0) / 100);
                const net = gross - d;
                subtotal += gross; discount += d; tax += net * (this.lineRate(it) / 100);
            }
            discount += this.invoiceDiscount();
            const total = subtotal - discount + tax;
            return { subtotal, discount, tax, total, due: total - (this.amountPaid || 0) };
        },
        money(v) { return (parseFloat(v) || 0).toFixed(2); },
        // ---- quick add ----
        async quickAddSave() {
            if (!this.qa.name.trim()) { this.qa.error = 'Client name is required.'; return; }
            if (!this.qa.email.trim()) { this.qa.error = 'Email is required.'; return; }
            this.qa.error = ''; this.qa.saving = true;
            try {
                const res = await fetch('{{ route('admin.clients.quick') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ name: this.qa.name, email: this.qa.email, company: this.qa.company, login_allowed: 1 }),
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    this.qa.error = err.errors ? Object.values(err.errors).flat().join(' ') : (err.message || 'Could not add the client.');
                    this.qa.saving = false; return;
                }
                const c = await res.json();
                this.clients[c.id] = c;
                const opt = document.createElement('option');
                opt.value = c.id; opt.textContent = c.name + (c.company ? ' — ' + c.company : '');
                this.$refs.clientSelect.appendChild(opt);
                this.clientId = String(c.id); this.pickClient();
                this.qa = { open: false, name: '', email: '', company: '', saving: false, error: '' };
            } catch (e) { this.qa.error = 'Something went wrong. Please try again.'; this.qa.saving = false; }
        },
    };
}
</script>
@endsection
