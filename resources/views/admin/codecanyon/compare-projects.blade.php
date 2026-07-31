@extends('admin.layouts.app')
@section('title', 'Product Compare')

@php
    $money = fn ($v) => '$'.number_format($v, 0);
    $canManage = auth()->user()->allows('codecanyon', 'manage');
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Product Compare</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                Name a project, line up the competing items from any author, and watch them race.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($projects->isNotEmpty())
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="days" value="{{ $days }}">
                    <select name="project" onchange="this.form.submit()" class="h-10 rounded-lg border-gray-200 text-sm">
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}" @selected($current && $current->id === $p->id)>
                                {{ $p->name }} ({{ $p->products_count }})
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
            <div class="flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
                @foreach ($ranges as $value => $label)
                    <a href="{{ route('admin.codecanyon.compare-projects', array_filter(['days' => $value, 'project' => $current?->id])) }}"
                       class="rounded-md px-3 py-1.5 font-semibold transition {{ $days === $value ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    @if (session('status'))<div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    @if ($available->isEmpty())
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            <p class="font-semibold">Nothing to compare yet.</p>
            <p class="mt-1">A project is built from products already on the watchlist. Add authors or items on
                <a href="{{ route('admin.codecanyon.index') }}" class="font-semibold underline">CodeCanyon &rsaquo; Overview</a> first.</p>
        </div>
    @endif

    {{-- ===== New project ===== --}}
    @if ($canManage)
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-bold text-[var(--color-heading)]">Create a project</h3>
            <p class="mt-0.5 text-xs text-[var(--color-muted)]">e.g. "Restaurant POS", "School Management" — then add the rival items to it.</p>
            <form method="POST" action="{{ route('admin.codecanyon.projects.store') }}" class="mt-3 flex flex-wrap items-center gap-2">
                @csrf
                <input type="text" name="name" required placeholder="Project name" class="h-10 min-w-[220px] flex-1 rounded-lg border-gray-200 text-sm">
                <input type="text" name="notes" placeholder="Notes (optional)" class="h-10 min-w-[220px] flex-1 rounded-lg border-gray-200 text-sm">
                <button class="h-10 rounded-lg bg-[var(--color-primary)] px-5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Create</button>
            </form>
        </div>
    @endif

    @if ($current)
        {{-- ===== Line-up ===== --}}
        @if ($canManage)
            <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm" x-data="{ open: {{ $current->products->isEmpty() ? 'true' : 'false' }}, q: '' }">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-[var(--color-heading)]">{{ $current->name }}</h3>
                        @if ($current->notes)<p class="mt-0.5 text-xs text-[var(--color-muted)]">{{ $current->notes }}</p>@endif
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="open = !open" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                            <span x-text="open ? 'Close' : 'Edit line-up'">Edit line-up</span>
                        </button>
                        <form method="POST" action="{{ route('admin.codecanyon.projects.destroy', $current) }}"
                              onsubmit="return confirm('Delete the project &quot;{{ $current->name }}&quot;? The products stay on the watchlist.')">
                            @csrf @method('DELETE')
                            <button class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Delete</button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.codecanyon.projects.update', $current) }}" x-show="open" x-cloak class="mt-4 border-t border-gray-100 pt-4">
                    @csrf @method('PUT')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-semibold text-[var(--color-muted)]">Project name</span>
                            <input type="text" name="name" required value="{{ $current->name }}" class="mt-1 h-10 w-full rounded-lg border-gray-200 text-sm">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-[var(--color-muted)]">Our product in this race</span>
                            <select name="own_product_id" class="mt-1 h-10 w-full rounded-lg border-gray-200 text-sm">
                                <option value="">— none —</option>
                                @foreach ($available as $p)
                                    <option value="{{ $p->id }}" @selected($current->own_product_id === $p->id)>{{ $p->name }} · {{ $p->author?->username }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="mt-3 block">
                        <span class="text-xs font-semibold text-[var(--color-muted)]">Notes</span>
                        <input type="text" name="notes" value="{{ $current->notes }}" class="mt-1 h-10 w-full rounded-lg border-gray-200 text-sm">
                    </label>

                    <div class="mt-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-[var(--color-muted)]">Products in this comparison</span>
                            <input type="search" x-model="q" placeholder="Filter products…" class="h-9 w-56 rounded-lg border-gray-200 text-sm">
                        </div>
                        <div class="mt-2 max-h-72 space-y-1 overflow-y-auto rounded-lg border border-gray-100 p-2">
                            @foreach ($available as $p)
                                @php $haystack = strtolower($p->name.' '.$p->author?->username); @endphp
                                <label class="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-gray-50"
                                       x-show="!q || '{{ addslashes($haystack) }}'.includes(q.toLowerCase())">
                                    <input type="checkbox" name="products[]" value="{{ $p->id }}"
                                           @checked($current->products->contains($p->id))
                                           class="rounded border-gray-300 accent-[var(--color-primary)]">
                                    <span class="min-w-0 flex-1 truncate text-sm text-[var(--color-heading)]">{{ $p->name }}</span>
                                    <span class="shrink-0 text-xs text-[var(--color-muted)]">{{ $p->author?->username ?: '—' }}</span>
                                    <span class="shrink-0 text-xs font-semibold text-[var(--color-muted)]">{{ number_format((int) $p->number_of_sales) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <button class="mt-4 rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save project</button>
                </form>
            </div>
        @endif

        @if (! $hasHistory && $rows->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
                <p class="font-semibold">Only one day of history so far.</p>
                <p class="mt-1">Daily sales need two syncs to subtract. Lifetime figures below are already accurate.</p>
            </div>
        @endif

        {{-- ===== The race ===== --}}
        <div class="mt-6 rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h3 class="text-lg font-bold text-[var(--color-heading)]">{{ $current->name }} — standings</h3>
                <p class="text-xs text-[var(--color-muted)]">Ranked by sales in the last {{ $days }} days.</p>
            </div>
            @if ($rows->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-[var(--color-muted)]">
                    No products in this project yet. @if ($canManage) Use <strong>Edit line-up</strong> above. @endif
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold">#</th>
                                <th class="px-5 py-3 font-semibold">Product</th>
                                <th class="px-5 py-3 font-semibold">Author</th>
                                <th class="px-5 py-3 text-right font-semibold">Sold ({{ $days }}d)</th>
                                <th class="px-5 py-3 text-right font-semibold">Lifetime</th>
                                <th class="px-5 py-3 text-right font-semibold">Price</th>
                                <th class="px-5 py-3 text-right font-semibold">Rating</th>
                                <th class="px-5 py-3 text-right font-semibold">Est. revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows as $i => $row)
                                @php $p = $row['product']; @endphp
                                <tr class="{{ $row['is_ours'] ? 'bg-indigo-50/40' : '' }}">
                                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $i + 1 }}</td>
                                    <td class="px-5 py-3">
                                        <a href="{{ route('admin.codecanyon.product', $p) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $p->name }}</a>
                                        @if ($row['is_ours'])
                                            <span class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-600">Ours</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($p->author)
                                            <a href="{{ route('admin.codecanyon.author', $p->author) }}" class="text-[var(--color-muted)] hover:text-[var(--color-heading)]">{{ $p->author->username }}</a>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right text-lg font-bold text-[var(--color-heading)]">{{ number_format($row['sold']) }}</td>
                                    <td class="px-5 py-3 text-right">{{ number_format((int) $p->number_of_sales) }}</td>
                                    <td class="px-5 py-3 text-right">${{ number_format($p->price(), 0) }}</td>
                                    <td class="px-5 py-3 text-right">{{ $p->rating ? number_format($p->rating, 2) : '—' }}</td>
                                    <td class="px-5 py-3 text-right">{{ $money($p->estimatedRevenue()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($rows->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="text-lg font-bold text-[var(--color-heading)]">Sales by date</h3>
                    <p class="text-xs text-[var(--color-muted)]">A dot means no sync ran that day — not a day without sales.</p>
                </div>
                @include('admin.codecanyon.partials.matrix', [
                    'dates' => $dates,
                    'empty' => 'No daily history yet. Come back after the next sync.',
                    'series' => $rows->map(fn ($r) => [
                        'label' => $r['product']->name,
                        'sub' => ($r['product']->author?->username ?: '—').($r['is_ours'] ? ' · ours' : ''),
                        'href' => route('admin.codecanyon.product', $r['product']),
                        'daily' => $r['daily'],
                    ])->all(),
                ])
            </div>
        @endif
    @elseif ($projects->isEmpty() && $available->isNotEmpty())
        <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-8 text-center shadow-sm">
            <p class="text-sm text-[var(--color-muted)]">No projects yet. Create one above to start comparing.</p>
        </div>
    @endif
@endsection
