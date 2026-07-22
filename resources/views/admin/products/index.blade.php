@extends('admin.layouts.app')
@section('title', 'Products')

@section('content')
    <div x-data="{ tab: 'all' }">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            {{-- Filter tabs --}}
            <div class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white p-1">
                <button type="button" @click="tab = 'all'"
                        :class="tab === 'all' ? 'bg-[var(--color-primary)] text-white shadow-sm' : 'text-[var(--color-muted)] hover:bg-gray-50'"
                        class="rounded-md px-3.5 py-1.5 text-sm font-semibold transition">
                    All Products <span class="ml-1 text-xs opacity-70">{{ $products->count() }}</span>
                </button>
                <button type="button" @click="tab = 'home'"
                        :class="tab === 'home' ? 'bg-[var(--color-primary)] text-white shadow-sm' : 'text-[var(--color-muted)] hover:bg-gray-50'"
                        class="rounded-md px-3.5 py-1.5 text-sm font-semibold transition">
                    Homepage Featured <span class="ml-1 text-xs opacity-70">{{ $homeProducts->count() }}</span>
                </button>
            </div>
            <a href="{{ route('admin.products.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> New Product
            </a>
        </div>

        {{-- All products (order = catalogue serial / sort_order) --}}
        <div x-show="tab === 'all'">
            <p class="mb-3 text-xs text-[var(--color-muted)]">Drag a row by its handle to set the order products appear in on the website.</p>
            @include('admin.products._product-table', ['rows' => $products, 'reorderUrl' => route('admin.products.reorder'), 'tableId' => 'all-rows', 'emptyText' => 'No products yet.'])
        </div>

        {{-- Homepage featured (order = home_order) --}}
        <div x-show="tab === 'home'" x-cloak>
            <p class="mb-3 text-xs text-[var(--color-muted)]">Order of the featured products on the website <strong>homepage</strong>. Only products with “Show on homepage” enabled appear here — drag to arrange them.</p>
            @include('admin.products._product-table', ['rows' => $homeProducts, 'reorderUrl' => route('admin.products.reorder-home'), 'tableId' => 'home-rows', 'emptyText' => 'No homepage products yet — turn on “Show on homepage” when editing a product.'])
        </div>
    </div>

    <script>
        (function () {
            const csrf = document.querySelector('meta[name=csrf-token]').content;
            document.querySelectorAll('.product-tbody[data-reorder-url]').forEach(setup);

            function setup(tbody) {
                const url = tbody.dataset.reorderUrl;
                let dragRow = null;
                tbody.querySelectorAll('tr.product-row').forEach((row) => {
                    const handle = row.querySelector('[data-drag-handle]');
                    if (!handle) return;
                    handle.addEventListener('mousedown', () => row.setAttribute('draggable', 'true'));
                    row.addEventListener('dragstart', (e) => { dragRow = row; row.classList.add('opacity-40'); e.dataTransfer.effectAllowed = 'move'; });
                    row.addEventListener('dragend', () => {
                        row.classList.remove('opacity-40'); row.removeAttribute('draggable');
                        if (dragRow) { dragRow = null; renumber(tbody); persist(tbody, url); }
                    });
                });
                tbody.addEventListener('dragover', (e) => {
                    if (!dragRow || dragRow.parentElement !== tbody) return;   // stay within this table
                    e.preventDefault();
                    const after = rowAfter(tbody, e.clientY);
                    if (after == null) tbody.appendChild(dragRow); else tbody.insertBefore(dragRow, after);
                });
            }
            function rowAfter(tbody, y) {
                const rows = [...tbody.querySelectorAll('tr.product-row:not(.opacity-40)')];
                return rows.reduce((closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height / 2;
                    return (offset < 0 && offset > closest.offset) ? { offset, element: child } : closest;
                }, { offset: -Infinity }).element;
            }
            function renumber(tbody) {
                tbody.querySelectorAll('tr.product-row .row-serial').forEach((el, i) => { el.textContent = i + 1; });
            }
            function persist(tbody, url) {
                const order = [...tbody.querySelectorAll('tr.product-row')].map((r) => r.dataset.productId);
                fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ order }),
                }).catch(() => alert('Could not save the new order — refresh and try again.'));
            }
        })();
    </script>
@endsection
