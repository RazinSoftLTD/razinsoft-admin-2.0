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

    {{-- Product picker --}}
    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($products as $p)
            <a href="{{ route('admin.installation-plans', ['product' => $p->id]) }}"
               class="inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-semibold transition {{ $product && $product->id === $p->id ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)] text-[var(--color-primary)]' : 'border-gray-200 text-[var(--color-muted)] hover:bg-gray-50' }}">
                {{ $p->name }}
                <span class="rounded-full bg-white/70 px-1.5 text-[10px] font-bold text-gray-500">{{ $p->installation_plans_count }}</span>
            </a>
        @endforeach
    </div>

    @if (! $product)
        <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center"><p class="text-sm text-gray-400">No products yet. Create a product first.</p></div>
    @else
        {{-- Copy from another product --}}
        @if ($canCopy && $products->where('id', '!=', $product->id)->where('installation_plans_count', '>', 0)->isNotEmpty())
            <div class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white p-4" x-data="{ open: false }">
                <div class="flex-1">
                    <p class="text-sm font-semibold text-[var(--color-heading)]">Copy plans from another product</p>
                    <p class="text-xs text-[var(--color-muted)]">Duplicate all features, plans and the matrix into <strong>{{ $product->name }}</strong>, then just update prices.</p>
                </div>
                <form method="POST" action="{{ route('admin.installation-plans.copy-from', $product) }}" class="flex items-center gap-2"
                      onsubmit="return confirm('This replaces {{ $product->name }}\'s current installation plans with a copy. Continue?')">
                    @csrf
                    <select name="source_id" required class="h-10 rounded-lg border-gray-200 text-sm">
                        <option value="">Choose source product…</option>
                        @foreach ($products->where('id', '!=', $product->id)->where('installation_plans_count', '>', 0) as $src)
                            <option value="{{ $src->id }}">{{ $src->name }} ({{ $src->installation_plans_count }} plans)</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg border border-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-[var(--color-primary)] hover:bg-[var(--color-primary-soft)]">Copy</button>
                </form>
            </div>
        @endif

        @php $features = $product->installationFeatures; $plans = $product->installationPlans; @endphp

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Plans --}}
            <section class="rounded-xl border border-gray-100 bg-white shadow-sm lg:col-span-1">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Plans</h2>
                    <span class="text-xs text-gray-400">{{ $plans->count() }} plan(s)</span>
                </div>
                <div class="space-y-3 p-5">
                    @foreach ($plans as $plan)
                        <div class="rounded-xl border border-gray-100 p-4" x-data="{ edit: false }">
                            <div class="flex items-start justify-between gap-2" x-show="!edit">
                                <div>
                                    <p class="flex items-center gap-2 font-bold text-[var(--color-heading)]">{{ $plan->name }}
                                        @if ($plan->is_popular)<span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-600">POPULAR</span>@endif
                                    </p>
                                    <p class="text-xs text-gray-400">{{ $plan->tagline }}</p>
                                    <p class="mt-1 text-sm"><span class="font-bold text-[var(--color-heading)]">${{ rtrim(rtrim(number_format($plan->sale_price ?? $plan->price, 2), '0'), '.') }}</span>@if ($plan->sale_price)<span class="ml-1 text-xs text-gray-400 line-through">${{ rtrim(rtrim(number_format($plan->price, 2), '0'), '.') }}</span>@endif</p>
                                    @if ($plan->note)<p class="mt-0.5 text-[11px] text-gray-400">{{ $plan->note }}</p>@endif
                                </div>
                                @if ($canEdit)
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="edit = true" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg></button>
                                        <form method="POST" action="{{ route('admin.installation-plans.plans.destroy', [$product, $plan]) }}" onsubmit="return confirm('Delete this plan?')">@csrf @method('DELETE')
                                            <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
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
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($features as $feature)
                                        <tr>
                                            <td class="py-2.5 pr-4">
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
        </script>
    @endif
@endsection
