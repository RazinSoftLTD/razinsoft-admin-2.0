@extends('admin.layouts.app')
@section('title', 'Installation Plans')

@php
    $me = auth()->user();
    $canCreate = $me->allows('installation_plans', 'create');
    $canUpdate = $me->allows('installation_plans', 'edit');
    $canDelete = $me->allows('installation_plans', 'delete') && $me->allows('products', 'delete');
    $statuses = \App\Models\InstallationPlan::STATUSES;
    $btnOn = [
        'draft' => 'border-amber-500 bg-amber-50 text-amber-700',
        'published' => 'border-emerald-500 bg-emerald-50 text-emerald-700',
        'unpublished' => 'border-gray-400 bg-gray-100 text-gray-600',
    ];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Installation Plans</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Pick a product to build its plans and comparison matrix. The publish state controls whether the public Installation page shows it.</p>
        </div>

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

    @if ($products->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-400">No products yet — add the first one above.</p>
        </div>
    @else
        <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Product</th>
                        <th class="px-5 py-3 font-semibold">Plans</th>
                        <th class="px-5 py-3 font-semibold">Features</th>
                        <th class="px-5 py-3 font-semibold">State</th>
                        <th class="px-5 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($products as $p)
                        @php $cur = $p->installation_status ?? 'published'; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.installation-plans.show', $p) }}" class="flex items-center gap-3">
                                    @if ($p->installation_icon)
                                        <img src="{{ \App\Http\Resources\ProductResource::media($p->installation_icon) }}" alt="" class="h-9 w-9 shrink-0 rounded-lg border border-gray-100 object-cover">
                                    @else
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-gray-100 text-xs font-bold text-gray-400">{{ strtoupper(substr($p->name, 0, 2)) }}</span>
                                    @endif
                                    <span>
                                        <span class="block font-semibold text-[var(--color-heading)]">{{ $p->name }}</span>
                                        <span class="block text-xs text-gray-400">{{ $p->currency ?: 'USD' }}@unless ($p->installation_icon) · no icon yet @endunless</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->installation_plans_count }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->installation_features_count }}</td>
                            <td class="px-5 py-3">
                                @if ($canUpdate)
                                    <div class="flex flex-wrap items-center gap-1">
                                        @foreach ($statuses as $k => $v)
                                            <form method="POST" action="{{ route('admin.installation-plans.status', $p) }}">
                                                @csrf
                                                <input type="hidden" name="installation_status" value="{{ $k }}">
                                                <button class="rounded-lg border px-2 py-1 text-[11px] font-semibold transition {{ $cur === $k ? $btnOn[$k] : 'border-gray-200 text-[var(--color-muted)] hover:bg-gray-50' }}">{{ $v }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="rounded-lg border px-2 py-1 text-[11px] font-semibold {{ $btnOn[$cur] }}">{{ $statuses[$cur] }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end">
                                    <div class="relative" x-data="{ open: false, edit: false }">
                                        <button type="button" @click="open = !open" @click.outside="open = false"
                                                class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Actions">
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                                        </button>
                                        <div x-show="open" x-cloak x-transition.opacity style="min-width:11rem"
                                             class="absolute right-0 top-10 z-20 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg">
                                            <a href="{{ route('admin.installation-plans.show', $p) }}" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-xs font-medium text-[var(--color-heading)] hover:bg-gray-50">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg>Plans &amp; matrix
                                            </a>
                                            @if ($canUpdate)
                                                <button type="button" @click="edit = true; open = false" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-xs font-medium text-[var(--color-heading)] hover:bg-gray-50">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Edit &amp; icon
                                                </button>
                                            @endif
                                            <a href="{{ route('admin.installation-plans.preview', $p) }}" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-xs font-medium text-[var(--color-heading)] hover:bg-gray-50">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>Preview
                                            </a>
                                            @if ($canDelete)
                                                <form method="POST" action="{{ route('admin.installation-plans.products.destroy', $p) }}"
                                                      onsubmit="return confirm('Remove “{{ $p->name }}” from the whole site (catalogue included), along with its installation plans? It can be restored by a developer.')">
                                                    @csrf @method('DELETE')
                                                    <button class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-xs font-medium text-red-600 hover:bg-gray-50">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>Delete product
                                                    </button>
                                                </form>
                                            @endif
                                        </div>

                                        {{-- Edit product: name, currency and the Installation page icon --}}
                                        @if ($canUpdate)
                                            <div x-show="edit" x-cloak @keydown.escape.window="edit = false">
                                                <div x-show="edit" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="edit = false"></div>
                                                <div x-show="edit" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-20" @click.self="edit = false">
                                                    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white text-left shadow-2xl">
                                                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                                                            <h3 class="text-base font-bold text-[var(--color-heading)]">Edit {{ $p->name }}</h3>
                                                            <button type="button" @click="edit = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100">
                                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                                            </button>
                                                        </div>
                                                        <form method="POST" action="{{ route('admin.installation-plans.products.update', $p) }}" enctype="multipart/form-data" class="space-y-4 p-5">
                                                            @csrf @method('PUT')
                                                            <div>
                                                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Product name <span class="text-red-500">*</span></label>
                                                                <input type="text" name="name" required maxlength="150" value="{{ $p->name }}"
                                                                       class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                                            </div>
                                                            <div>
                                                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Currency</label>
                                                                <select name="currency" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                                                                    @foreach (['USD', 'BDT', 'EUR', 'GBP'] as $c)<option value="{{ $c }}" @selected(($p->currency ?: 'USD') === $c)>{{ $c }}</option>@endforeach
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Installation page icon</label>
                                                                @if ($p->installation_icon)
                                                                    <img src="{{ \App\Http\Resources\ProductResource::media($p->installation_icon) }}" alt="" class="mb-2 h-16 w-16 rounded-xl border border-gray-100 object-cover">
                                                                    <label class="mb-2 flex items-center gap-2 text-xs text-[var(--color-muted)]">
                                                                        <input type="checkbox" name="remove_installation_icon" value="1" class="h-4 w-4 rounded accent-[var(--color-primary)]">
                                                                        Remove the icon (falls back to the product thumbnail)
                                                                    </label>
                                                                @endif
                                                                <input type="file" name="installation_icon" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                                                                <p class="mt-1 text-xs text-[var(--color-muted)]">{{ \App\Support\ImageSpecs::hint('installation_icon') }} Shown on the product buttons of the public Installation page.</p>
                                                            </div>
                                                            <div class="flex justify-end gap-2">
                                                                <button type="button" @click="edit = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                                                                <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
