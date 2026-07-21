@extends('admin.layouts.app')
@section('title', 'Installation Plans')

@php
    $me = auth()->user();
    $canCreate = $me->allows('installation_plans', 'create');
    $canUpdate = $me->allows('installation_plans', 'edit');
    $canDelete = $me->allows('installation_plans', 'delete');
    $canCopy = $me->allows('installation_plans', 'copy');
    // Anything that changes the page at all.
    $canEdit = $canCreate || $canUpdate || $canDelete;
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Installation Plans</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Products &rsaquo; Installation Plans — build the package comparison shown on the public Installation page.</p>
    </div>

    {{-- Product row: picker on the left, add + copy on the right --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div class="flex flex-1 flex-wrap gap-2">
            @foreach ($products as $p)
                <a href="{{ route('admin.installation-plans', ['product' => $p->id]) }}"
                   class="inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-semibold transition {{ $product && $product->id === $p->id ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)] text-[var(--color-primary)]' : 'border-gray-200 text-[var(--color-muted)] hover:bg-gray-50' }}">
                    {{ $p->name }}
                    <span class="rounded-full bg-white/70 px-1.5 text-[10px] font-bold text-gray-500">{{ $p->installation_plans_count }}</span>
                </a>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Copy plans from another product --}}
            @if ($product && $canCopy && $products->where('id', '!=', $product->id)->where('installation_plans_count', '>', 0)->isNotEmpty())
                <form method="POST" action="{{ route('admin.installation-plans.copy-from', $product) }}" class="flex items-center gap-2"
                      onsubmit="return confirm('This replaces {{ $product->name }}\'s current installation plans with a copy. Continue?')">
                    @csrf
                    <select name="source_id" required class="h-10 rounded-lg border-gray-200 text-sm">
                        <option value="">Copy plans from…</option>
                        @foreach ($products as $src)
                            @continue($src->id === $product->id || ! $src->installation_plans_count)
                            <option value="{{ $src->id }}">{{ $src->name }} ({{ $src->installation_plans_count }})</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] transition hover:bg-gray-50">Copy</button>
                </form>
            @endif

            {{-- Add a product without leaving this page --}}
            @if ($canCreate)
                <div x-data="{ open: false }">
                    <button type="button" @click="open = true"
                            class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Product
                    </button>

                    <div x-show="open" x-cloak @keydown.escape.window="open = false">
                        <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="open = false"></div>
                        <div x-show="open" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-20" @click.self="open = false">
                            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                                    <div>
                                        <h3 class="text-base font-bold text-[var(--color-heading)]">Add Product</h3>
                                        <p class="text-xs text-[var(--color-muted)]">Creates a draft product you can build plans for</p>
                                    </div>
                                    <button type="button" @click="open = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                    </button>
                                </div>
                                <form method="POST" action="{{ route('admin.installation-plans.products.store') }}" class="space-y-4 p-5">
                                    @csrf
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Product name <span class="text-red-500">*</span></label>
                                        <input type="text" name="name" required maxlength="150" placeholder="e.g. Ready eCommerce"
                                               class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Currency</label>
                                        <select name="currency" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                                            @foreach (['USD', 'BDT', 'EUR', 'GBP'] as $cur)<option value="{{ $cur }}">{{ $cur }}</option>@endforeach
                                        </select>
                                    </div>
                                    <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-[var(--color-muted)]">
                                        The product is created as a <strong>draft</strong>. Fill in its full details later under Products.
                                    </p>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                                        <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Create &amp; add plans</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if (! $product)
        <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center"><p class="text-sm text-gray-400">No products yet. Create a product first.</p></div>
    @else
        @php $features = $product->installationFeatures; $plans = $product->installationPlans; @endphp
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Plans --}}
            <section class="rounded-xl border border-gray-100 bg-white shadow-sm lg:col-span-1">
                <div class="border-b border-gray-100 px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Plans</h2>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400">{{ $plans->count() }} plan(s)</span>
                            <a href="{{ route('admin.installation-plans.preview', $product) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-heading)] transition hover:bg-gray-50">
                                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                Preview
                            </a>
                        </div>
                    </div>

                    {{-- One publish state for this product's whole plan block --}}
                    @if ($canUpdate)
                        @php
                            $cur = $product->installation_status ?? 'published';
                            $btnOn = ['draft' => 'border-amber-500 bg-amber-50 text-amber-700',
                                      'published' => 'border-emerald-500 bg-emerald-50 text-emerald-700',
                                      'unpublished' => 'border-gray-400 bg-gray-100 text-gray-600'];
                            $dot = ['draft' => 'bg-amber-400', 'published' => 'bg-emerald-400', 'unpublished' => 'bg-gray-300'];
                            $hintFor = ['draft' => 'Work in progress — the website does not show these plans',
                                        'published' => 'Live — visitors see these plans',
                                        'unpublished' => 'Hidden from the website, kept here'];
                        @endphp
                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                            @foreach (\App\Models\InstallationPlan::STATUSES as $k => $v)
                                <form method="POST" action="{{ route('admin.installation-plans.status', $product) }}">
                                    @csrf
                                    <input type="hidden" name="installation_status" value="{{ $k }}">
                                    <button title="{{ $hintFor[$k] }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition {{ $cur === $k ? $btnOn[$k] : 'border-gray-200 text-[var(--color-muted)] hover:bg-gray-50' }}">
                                        @if ($cur === $k)
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                        @else
                                            <span class="h-2 w-2 rounded-full {{ $dot[$k] }}"></span>
                                        @endif
                                        {{ $v }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                        <p class="mt-1.5 text-[11px] text-[var(--color-muted)]">{{ $hintFor[$cur] }}</p>
                    @endif
                </div>
                <div class="space-y-3 p-5">
                    @foreach ($plans as $plan)
                        <div class="rounded-xl border border-gray-100 p-4" x-data="{ edit: false }">
                            <div class="flex items-start justify-between gap-2" x-show="!edit">
                                <div>
                                    <p class="flex flex-wrap items-center gap-2 font-bold text-[var(--color-heading)]">{{ $plan->name }}
                                        @if ($plan->is_popular)<span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-600">POPULAR</span>@endif
                                    </p>
                                    <p class="text-xs text-gray-400">{{ $plan->tagline }}</p>
                                    <p class="mt-1 text-sm"><span class="font-bold text-[var(--color-heading)]">${{ rtrim(rtrim(number_format($plan->sale_price ?? $plan->price, 2), '0'), '.') }}</span>@if ($plan->sale_price)<span class="ml-1 text-xs text-gray-400 line-through">${{ rtrim(rtrim(number_format($plan->price, 2), '0'), '.') }}</span>@endif</p>
                                    @if ($plan->note)<p class="mt-0.5 text-[11px] text-gray-400">{{ $plan->note }}</p>@endif
                                </div>
                                @if ($canEdit)
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button type="button" @click="edit = true" title="Edit plan"
                                                class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg>
                                        </button>
                                        <form method="POST" action="{{ route('admin.installation-plans.plans.destroy', [$product, $plan]) }}" onsubmit="return confirm('Delete “{{ $plan->name }}” permanently? Use Unpublished instead if you only want to hide it.')">
                                            @csrf @method('DELETE')
                                            <button title="Delete plan" class="grid h-8 w-8 place-items-center rounded-lg text-red-400 transition hover:bg-red-50 hover:text-red-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m3 0v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>

                            @if ($canEdit)
                                <form x-show="edit" x-cloak method="POST" action="{{ route('admin.installation-plans.plans.update', [$product, $plan]) }}" class="space-y-2">
                                    @csrf @method('PUT')
                                    @include('admin.installation-plans._plan-fields', ['plan' => $plan])
                                    <div class="flex gap-2"><button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button><button type="button" @click="edit = false" class="text-xs text-gray-400">Cancel</button></div>
                                </form>
                            @endif
                        </div>
                    @endforeach

                    @if ($canEdit)
                        <details class="rounded-xl border border-dashed border-gray-200 p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-[var(--color-primary)]">+ Add Plan</summary>
                            <form method="POST" action="{{ route('admin.installation-plans.plans.store', $product) }}" class="mt-3 space-y-2">
                                @csrf
                                @include('admin.installation-plans._plan-fields', ['plan' => null])
                                <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-white">Add Plan</button>
                            </form>
                        </details>
                    @endif
                </div>
            </section>

            {{-- Features + matrix --}}
            <section class="rounded-xl border border-gray-100 bg-white shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Features &amp; Comparison Matrix</h2>
                    <span class="text-xs text-gray-400">Tick which plan includes each feature</span>
                </div>
                <div class="p-5">
                    @if ($canEdit)
                        <form method="POST" action="{{ route('admin.installation-plans.features.store', $product) }}" class="mb-4 flex items-center gap-2">
                            @csrf
                            <input type="text" name="label" required placeholder="New feature (e.g. SMTP Email Setup)" class="h-10 flex-1 rounded-lg border-gray-200 text-sm">
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white">Add Feature</button>
                        </form>
                    @endif

                    @if ($features->isEmpty())
                        <p class="py-8 text-center text-sm text-gray-300">No features yet — add the first one above.</p>
                    @elseif ($plans->isEmpty())
                        <p class="py-8 text-center text-sm text-gray-300">Add at least one plan to build the matrix.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-left text-[11px] uppercase tracking-wide text-gray-400">
                                        <th class="py-2 pr-4 font-semibold">Feature</th>
                                        @foreach ($plans as $plan)<th class="px-3 py-2 text-center font-semibold">{{ $plan->name }}</th>@endforeach
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="feature-rows" class="divide-y divide-gray-50" @if ($canEdit) data-reorder-url="{{ route('admin.installation-plans.features.reorder', $product) }}" @endif>
                                    @foreach ($features as $feature)
                                        <tr class="feature-row" data-feature-id="{{ $feature->id }}">
                                            <td class="py-2.5 pr-4">
                                                <span class="flex items-center gap-2">
                                                    @if ($canEdit)
                                                        <span data-drag-handle class="shrink-0 cursor-move text-gray-300 hover:text-gray-500" title="Drag to reorder">
                                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg>
                                                        </span>
                                                    @endif
                                                <span x-data="{ e: false }">
                                                    <span x-show="!e" class="flex items-center gap-1.5">
                                                        <span class="text-[var(--color-heading)]">{{ $feature->label }}</span>
                                                        @if ($canEdit)<button type="button" @click="e = true" class="text-gray-300 hover:text-[var(--color-heading)]"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg></button>@endif
                                                    </span>
                                                    @if ($canEdit)
                                                        <form x-show="e" x-cloak method="POST" action="{{ route('admin.installation-plans.features.update', [$product, $feature]) }}" class="flex items-center gap-1">
                                                            @csrf @method('PUT')
                                                            <input type="text" name="label" value="{{ $feature->label }}" class="h-8 w-48 rounded border-gray-200 text-xs">
                                                            <button class="rounded bg-[var(--color-primary)] px-2 py-1 text-xs font-semibold text-white">Save</button>
                                                        </form>
                                                    @endif
                                                </span>
                                                </span>
                                            </td>
                                            @foreach ($plans as $plan)
                                                <td class="px-3 py-2.5 text-center">
                                                    <input type="checkbox" @checked($plan->includes($feature->id)) @disabled(! $canEdit)
                                                           class="h-4 w-4 rounded accent-[var(--color-primary)] matrix-cb"
                                                           data-url="{{ route('admin.installation-plans.toggle', [$product, $plan]) }}" data-feature="{{ $feature->id }}">
                                                </td>
                                            @endforeach
                                            <td class="py-2.5 text-right">
                                                @if ($canEdit)
                                                    <form method="POST" action="{{ route('admin.installation-plans.features.destroy', [$product, $feature]) }}" onsubmit="return confirm('Remove this feature?')">@csrf @method('DELETE')
                                                        <button class="text-gray-300 hover:text-red-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    @endif

    @if ($canEdit)
        <script>
            // Ajax-toggle the comparison matrix checkboxes.
            document.addEventListener('change', (e) => {
                const cb = e.target.closest('.matrix-cb');
                if (!cb) return;
                fetch(cb.dataset.url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ feature_id: cb.dataset.feature, included: cb.checked ? 1 : 0 }),
                }).catch(() => { cb.checked = !cb.checked; alert('Could not save — try again.'); });
            });

            // Drag-and-drop reordering of feature rows (drag by the grip handle).
            (function () {
                const tbody = document.getElementById('feature-rows');
                if (!tbody || !tbody.dataset.reorderUrl) return;
                const csrf = document.querySelector('meta[name=csrf-token]').content;
                let dragRow = null;

                // Only start a drag when the grip handle is the origin.
                tbody.querySelectorAll('tr.feature-row').forEach((row) => {
                    const handle = row.querySelector('[data-drag-handle]');
                    if (!handle) return;
                    handle.addEventListener('mousedown', () => { row.setAttribute('draggable', 'true'); });
                    row.addEventListener('dragstart', (e) => { dragRow = row; row.classList.add('opacity-40'); e.dataTransfer.effectAllowed = 'move'; });
                    row.addEventListener('dragend', () => {
                        row.classList.remove('opacity-40'); row.removeAttribute('draggable');
                        if (dragRow) { dragRow = null; persist(); }
                    });
                });

                tbody.addEventListener('dragover', (e) => {
                    if (!dragRow) return;
                    e.preventDefault();
                    const after = rowAfter(e.clientY);
                    if (after == null) tbody.appendChild(dragRow);
                    else tbody.insertBefore(dragRow, after);
                });

                function rowAfter(y) {
                    const rows = [...tbody.querySelectorAll('tr.feature-row:not(.opacity-40)')];
                    return rows.reduce((closest, child) => {
                        const box = child.getBoundingClientRect();
                        const offset = y - box.top - box.height / 2;
                        return (offset < 0 && offset > closest.offset) ? { offset, element: child } : closest;
                    }, { offset: -Infinity }).element;
                }

                function persist() {
                    const order = [...tbody.querySelectorAll('tr.feature-row')].map((r) => r.dataset.featureId);
                    fetch(tbody.dataset.reorderUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ order }),
                    }).catch(() => alert('Could not save the new order — refresh and try again.'));
                }
            })();
        </script>
    @endif
@endsection
