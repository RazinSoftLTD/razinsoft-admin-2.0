@extends('admin.layouts.app')
@section('title', $template->exists ? 'Edit Template' : 'New Template')

@section('content')
<div x-data="{ items: {{ Illuminate\Support\Js::from($items) }} }">
    <a href="{{ route('admin.invoice-templates.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to templates
    </a>

    <form method="POST" action="{{ $template->exists ? route('admin.invoice-templates.update', $template) : route('admin.invoice-templates.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($template->exists) @method('PUT') @endif

        <div class="grid gap-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm sm:grid-cols-2">
            <x-admin.field label="Template Name" name="name" :value="$template->name" required placeholder="e.g. Website + Hosting bundle" />
            <x-admin.field label="Currency" name="currency" type="select" :value="$template->currency ?? 'USD'" :options="['USD' => 'USD ($)', 'BDT' => 'BDT (৳)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)']" required />
        </div>

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Line Items</h2>
            <div class="space-y-2">
                <template x-for="(item, idx) in items" :key="idx">
                    <div class="grid grid-cols-12 gap-2 rounded-lg border border-gray-100 p-3">
                        <input type="text" :name="`items[${idx}][description]`" x-model="item.description" placeholder="Description" required class="col-span-12 h-9 rounded-lg border border-gray-200 px-2 text-sm sm:col-span-5">
                        <input type="number" step="0.01" :name="`items[${idx}][qty]`" x-model="item.qty" placeholder="Qty" class="col-span-3 h-9 rounded-lg border border-gray-200 px-2 text-sm sm:col-span-2">
                        <input type="number" step="0.01" :name="`items[${idx}][unit_price]`" x-model="item.unit_price" placeholder="Price" class="col-span-4 h-9 rounded-lg border border-gray-200 px-2 text-sm sm:col-span-2">
                        <input type="number" step="0.01" :name="`items[${idx}][discount_percent]`" x-model="item.discount_percent" placeholder="Disc%" class="col-span-2 h-9 rounded-lg border border-gray-200 px-2 text-sm sm:col-span-1">
                        <input type="number" step="0.01" :name="`items[${idx}][tax_percent]`" x-model="item.tax_percent" placeholder="Tax%" class="col-span-2 h-9 rounded-lg border border-gray-200 px-2 text-sm sm:col-span-1">
                        <button type="button" @click="items.splice(idx,1)" x-show="items.length>1" class="col-span-1 rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg></button>
                    </div>
                </template>
            </div>
            <button type="button" @click="items.push({description:'',qty:1,unit_price:0,discount_percent:0,tax_percent:0})" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-semibold text-[var(--color-primary)] hover:bg-[var(--color-primary-soft)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Item
            </button>
        </div>

        <div class="grid gap-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm sm:grid-cols-2">
            <x-admin.field label="Notes" name="notes" type="textarea" rows="2" :value="$template->notes" />
            <x-admin.field label="Terms" name="terms" type="textarea" rows="2" :value="$template->terms" />
            <x-admin.field label="Payment Method" name="payment_method" type="select" :value="$template->payment_method ?? 'Bank Transfer'" :options="array_combine(\App\Models\ClientInvoice::PAYMENT_METHODS, \App\Models\ClientInvoice::PAYMENT_METHODS)" />
        </div>

        @if ($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <div class="flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $template->exists ? 'Save changes' : 'Save template' }}</button>
            <a href="{{ route('admin.invoice-templates.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
</div>
@endsection
