@extends('admin.layouts.app')
@section('title', 'Create Manual Order')

@section('content')
    <a href="{{ route('admin.orders.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to orders
    </a>

    <form method="POST" action="{{ route('admin.orders.store') }}" class="max-w-3xl"
          x-data="orderForm(@js($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'plans' => $p->plans->map(fn($pl) => ['id' => $pl->id, 'name' => $pl->name, 'price' => (float) $pl->price])])->values()))">
        @csrf

        <div class="space-y-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Customer <span class="text-red-500">*</span></label>
                <select name="user_id" required class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                    <option value="">Select a customer…</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" @selected(old('user_id') == $c->id)>{{ $c->name }} ({{ $c->email }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <label class="text-sm font-medium text-[var(--color-heading)]">Products</label>
                    <button type="button" @click="addRow()" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-[var(--color-primary-soft)]">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add product
                    </button>
                </div>

                <div class="space-y-3">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid grid-cols-12 gap-2 rounded-lg border border-gray-100 bg-gray-50 p-3">
                            <div class="col-span-6">
                                <select :name="`items[${i}][product_id]`" x-model="row.product_id" @change="row.plan_id = ''" required class="h-10 w-full rounded-lg border border-gray-200 bg-white px-2 text-sm">
                                    <option value="">Product…</option>
                                    <template x-for="p in products" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
                                </select>
                            </div>
                            <div class="col-span-4">
                                <select :name="`items[${i}][plan_id]`" x-model="row.plan_id" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-2 text-sm">
                                    <option value="">Base price</option>
                                    <template x-for="pl in plansFor(row.product_id)" :key="pl.id"><option :value="pl.id" x-text="`${pl.name} — $${pl.price}`"></option></template>
                                </select>
                            </div>
                            <div class="col-span-1">
                                <input type="number" :name="`items[${i}][qty]`" x-model="row.qty" min="1" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-2 text-sm" />
                            </div>
                            <div class="col-span-1 flex items-center justify-center">
                                <button type="button" @click="removeRow(i)" x-show="rows.length > 1" class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field label="Coupon code" name="coupon" placeholder="Optional" />
                <div class="flex items-end pb-2">
                    <x-admin.field name="mark_paid" type="checkbox" label="Mark paid & fulfil now (issues invoice + license)" :value="true" />
                </div>
            </div>
        </div>

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Create order</button>
            <a href="{{ route('admin.orders.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>

    <script>
        function orderForm(products) {
            return {
                products,
                rows: [{ product_id: '', plan_id: '', qty: 1 }],
                plansFor(id) { return this.products.find(p => p.id == id)?.plans ?? []; },
                addRow() { this.rows.push({ product_id: '', plan_id: '', qty: 1 }); },
                removeRow(i) { this.rows.splice(i, 1); },
            };
        }
    </script>
@endsection
