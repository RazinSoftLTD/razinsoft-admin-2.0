@extends('admin.layouts.app')
@section('title', 'CodeCanyon')

@php
    $money = fn ($v) => '$'.number_format($v, 0);
    $canManage = auth()->user()->allows('codecanyon', 'manage');
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">CodeCanyon</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Activity &rsaquo; CodeCanyon — market &amp; competitor analysis from the official Envato API.</p>
        </div>
        @if ($canManage)
            <form method="POST" action="{{ route('admin.codecanyon.sync') }}">
                @csrf
                <button class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12a8 8 0 1 1-2.3-5.7M20 4v4h-4"/></svg>
                    Sync now
                </button>
            </form>
        @endif
    </div>

    @if (session('status'))<div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    @if (! $settings->isConfigured())
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            <p class="font-semibold">Envato API is not connected yet.</p>
            <p class="mt-1">Add your personal token under
                <a href="{{ route('admin.codecanyon-settings') }}" class="font-semibold underline">Settings → CodeCanyon Config</a> to start tracking.</p>
        </div>
    @else

    {{-- ===== Totals ===== --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Tracked Products', number_format($totals['products']), 'bg-indigo-50 text-indigo-600', 'M4 7h16M4 12h16M4 17h10'],
            ['Total Sales', number_format($totals['sales']), 'bg-emerald-50 text-emerald-600', 'M3 17l6-6 4 4 7-7M14 7h6v6'],
            ['Est. Revenue', $money($totals['revenue']), 'bg-amber-50 text-amber-600', 'M12 3v18M8 7h6a3 3 0 0 1 0 6H9a3 3 0 0 0 0 6h7'],
            ['Avg Rating', $totals['avgRating'] ?: '—', 'bg-violet-50 text-violet-600', 'm12 3 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5-5.8-3-5.8 3 1.1-6.5L2.6 9.8l6.5-.9L12 3Z'],
        ] as [$label, $value, $tint, $icon])
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <span class="grid shrink-0 place-items-center rounded-2xl {{ $tint }}" style="height:52px;width:52px">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm text-[var(--color-muted)]">{{ $label }}</p>
                        <p class="text-3xl font-bold text-[var(--color-heading)]" style="line-height:1.15">{{ $value }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <p class="mt-2 text-xs text-[var(--color-muted)]">Revenue is <strong>estimated</strong> (sales × current price) — the Envato API never exposes another author's real earnings.</p>

    {{-- ===== Watchlist forms ===== --}}
    @if ($canManage)
        <div class="mt-6 grid items-start gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-[var(--color-heading)]">Track an author</h3>
                <form method="POST" action="{{ route('admin.codecanyon.authors.store') }}" class="mt-3 space-y-2">
                    @csrf
                    <input type="text" name="username" required placeholder="CodeCanyon username" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                    <label class="flex items-center gap-2 text-xs font-medium text-[var(--color-muted)]">
                        <input type="checkbox" name="is_own" value="1" class="rounded border-gray-300 accent-[var(--color-primary)]"> This is our own account
                    </label>
                    <button class="w-full rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add &amp; sync portfolio</button>
                </form>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-[var(--color-heading)]">Track a single product</h3>
                <form method="POST" action="{{ route('admin.codecanyon.products.store') }}" class="mt-3 space-y-2">
                    @csrf
                    <input type="number" name="item_id" required placeholder="Envato item ID (e.g. 12345678)" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                    <select name="envato_niche_id" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                        <option value="">No niche</option>
                        @foreach ($niches as $n)<option value="{{ $n->id }}">{{ $n->name }}</option>@endforeach
                    </select>
                    <button class="w-full rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Track product</button>
                </form>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-[var(--color-heading)]">Niches</h3>
                <p class="mt-0.5 text-xs text-[var(--color-muted)]">Group comparable products, e.g. “eCommerce”.</p>
                <form method="POST" action="{{ route('admin.codecanyon.niches.store') }}" class="mt-3 flex items-center gap-2">
                    @csrf
                    <input type="text" name="name" required placeholder="Niche name" class="h-10 min-w-0 flex-1 rounded-lg border-gray-200 text-sm">
                    <input type="color" name="color" value="#6366f1" class="h-10 w-12 cursor-pointer rounded-lg border-gray-200 p-1">
                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                </form>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($niches as $n)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" style="background: {{ $n->color }}1a; color: {{ $n->color }}">
                            {{ $n->name }} ({{ $n->products_count }})
                            <form method="POST" action="{{ route('admin.codecanyon.niches.destroy', $n) }}" onsubmit="return confirm('Remove this niche?')">
                                @csrf @method('DELETE')<button class="opacity-60 hover:opacity-100">&times;</button>
                            </form>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Authors ===== --}}
    <div class="mt-6 rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-lg font-bold text-[var(--color-heading)]">Authors</h3>
            <p class="text-xs text-[var(--color-muted)]">Ours vs competitors — totals are lifetime figures from the API.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Author</th>
                        <th class="px-5 py-3 font-semibold">Products</th>
                        <th class="px-5 py-3 font-semibold">Lifetime sales</th>
                        <th class="px-5 py-3 font-semibold">Est. revenue</th>
                        <th class="px-5 py-3 font-semibold">Followers</th>
                        <th class="px-5 py-3 text-right font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($authors as $a)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.codecanyon.author', $a) }}" class="flex items-center gap-2">
                                    @if ($a->image)<img src="{{ $a->image }}" class="h-8 w-8 rounded-full object-cover" alt="">@else<span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]">{{ strtoupper(substr($a->username, 0, 1)) }}</span>@endif
                                    <span>
                                        <span class="block font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $a->username }}</span>
                                        <span class="text-xs text-[var(--color-muted)]">{{ $a->country ?: '—' }}</span>
                                    </span>
                                    @if ($a->is_own)<span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-600">Us</span>@endif
                                </a>
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $a->products->count() }}</td>
                            <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ number_format((int) $a->total_sales) }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $money($a->estimatedRevenue()) }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ number_format((int) $a->followers) }}</td>
                            <td class="px-5 py-3 text-right">
                                @if ($canManage)
                                    <form method="POST" action="{{ route('admin.codecanyon.authors.destroy', $a) }}" onsubmit="return confirm('Stop tracking {{ $a->username }}?')">
                                        @csrf @method('DELETE')<button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Remove</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">No authors tracked yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ===== Category popularity ===== --}}
    @if ($categories->isNotEmpty())
        <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-bold text-[var(--color-heading)]">Category Popularity</h3>
            <p class="text-xs text-[var(--color-muted)]">Across everything tracked, ranked by total sales.</p>
            @php $max = max(1, (int) $categories->max('sales')); @endphp
            <ul class="mt-4 space-y-3">
                @foreach ($categories as $c)
                    <li>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="truncate font-semibold text-[var(--color-heading)]">{{ $c['name'] }}</span>
                            <span class="shrink-0 text-[var(--color-muted)]">{{ number_format($c['sales']) }} sales · {{ $c['products'] }} products · {{ $money($c['revenue']) }}</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-2 rounded-full bg-[var(--color-primary)]" style="width: {{ round($c['sales'] / $max * 100) }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ===== Products ===== --}}
    <div class="mt-6 rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div>
                <h3 class="text-lg font-bold text-[var(--color-heading)]">Products</h3>
                <p class="text-xs text-[var(--color-muted)]">Sorted by sales. Pick a niche to compare like with like.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.codecanyon.index') }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $nicheId ? 'bg-gray-100 text-gray-500 hover:bg-gray-200' : 'bg-[var(--color-primary)] text-white' }}">All</a>
                @foreach ($niches as $n)
                    <a href="{{ route('admin.codecanyon.index') }}?niche={{ $n->id }}"
                       class="rounded-full px-3 py-1.5 text-xs font-semibold {{ (int) $nicheId === $n->id ? 'text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}"
                       @if ((int) $nicheId === $n->id) style="background: {{ $n->color }}" @endif>{{ $n->name }}</a>
                @endforeach
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Product</th>
                        <th class="px-5 py-3 font-semibold">Sales</th>
                        <th class="px-5 py-3 font-semibold">7d</th>
                        <th class="px-5 py-3 font-semibold">Rating</th>
                        <th class="px-5 py-3 font-semibold">Price</th>
                        <th class="px-5 py-3 font-semibold">Est. revenue</th>
                        <th class="px-5 py-3 font-semibold">Released</th>
                        <th class="px-5 py-3 font-semibold">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($products as $p)
                        @php $growth = $p->salesGrowth(7); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.codecanyon.product', $p) }}" class="flex items-center gap-2.5">
                                    @if ($p->thumbnail_url)<img src="{{ $p->thumbnail_url }}" class="h-9 w-9 rounded-lg object-cover" alt="">@endif
                                    <span class="min-w-0">
                                        <span class="block max-w-xs truncate font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $p->name }}</span>
                                        <span class="text-xs text-[var(--color-muted)]">
                                            {{ $p->author_username }}
                                            @if ($p->niche)· <span style="color: {{ $p->niche->color }}">{{ $p->niche->name }}</span>@endif
                                            @if ($p->trending)· <span class="font-semibold text-emerald-600">Trending</span>@endif
                                        </span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ number_format($p->number_of_sales) }}</td>
                            <td class="px-5 py-3">
                                @if ($growth === null)
                                    <span class="text-xs text-gray-300" title="Needs a few days of snapshots">—</span>
                                @else
                                    <span class="text-xs font-semibold {{ $growth > 0 ? 'text-emerald-600' : 'text-gray-400' }}">{{ $growth > 0 ? '+'.number_format($growth) : '0' }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->rating ? number_format((float) $p->rating, 2).' ('.$p->rating_count.')' : '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">${{ number_format($p->price(), 2) }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $money($p->estimatedRevenue()) }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->published_at?->format('d M Y') ?? '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->item_updated_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400">Nothing tracked yet — add an author or a product above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endsection
