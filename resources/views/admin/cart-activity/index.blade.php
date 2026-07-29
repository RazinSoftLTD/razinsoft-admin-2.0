@extends('admin.layouts.app')
@section('title', 'Cart Activity')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Cart Activity</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Who put products in their cart on the website — signed-in clients and anonymous visitors. One row per shopper, newest first.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <select name="date_range" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                <option value="">All time</option>
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $dv => $dl)
                    <option value="{{ $dv }}" @selected(request('date_range') === $dv)>{{ $dl }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="h-10 rounded-lg border border-gray-200 px-2 text-sm">
            <input type="date" name="to" value="{{ request('to') }}" class="h-10 rounded-lg border border-gray-200 px-2 text-sm">
            <button class="h-10 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply</button>
            <a href="{{ route('admin.cart-activity') }}" class="h-10 rounded-lg border border-gray-200 px-4 text-sm font-semibold leading-10 text-[var(--color-muted)] hover:bg-gray-50">Clear</a>
        </form>
    </div>

    {{-- Stats --}}
    <div class="mb-6 flex flex-wrap gap-4">
        <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 3h2l2.6 12.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L20 7H6M9 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg>
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Shoppers</p>
                <p class="text-lg font-bold text-[var(--color-heading)]">{{ number_format($totalShoppers) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Items added</p>
                <p class="text-lg font-bold text-[var(--color-heading)]">{{ number_format($totalAdds) }}</p>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Shoppers --}}
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Shoppers</h2>
                <p class="text-xs text-[var(--color-muted)]">Everyone who added something to a cart. "Not ordered" is who to follow up with.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Shopper</th>
                            <th class="px-5 py-3 font-semibold">Last added</th>
                            <th class="px-5 py-3 text-right font-semibold">Items</th>
                            <th class="px-5 py-3 font-semibold">Ordered?</th>
                            <th class="px-5 py-3 font-semibold">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($shoppers as $s)
                            @php
                                $client = $s->client_id ? ($clients[$s->client_id] ?? null) : null;
                                $last = $lastRows[$s->last_id] ?? null;
                                $hasOrdered = $s->client_id && isset($ordered[$s->client_id]);
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    @if ($client)
                                        <a href="{{ route('admin.client-activity.details', ['client' => $client->id]) }}" class="flex items-center gap-2 hover:opacity-80">
                                            @if ($client->photo)
                                                <img src="{{ asset('storage/'.$client->photo) }}" alt="" class="h-8 w-8 rounded-full border border-gray-200 object-cover">
                                            @else
                                                <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[11px] font-bold text-[var(--color-primary)]">{{ strtoupper(substr($client->name, 0, 1)) }}</span>
                                            @endif
                                            <span>
                                                <span class="block font-medium text-[var(--color-heading)]">{{ $client->name }}</span>
                                                <span class="block text-xs text-[var(--color-muted)]">{{ $client->email }}</span>
                                            </span>
                                        </a>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <span class="grid h-8 w-8 place-items-center rounded-full bg-gray-100 text-gray-400">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12a9.5 9.5 0 1 0 19 0 9.5 9.5 0 0 0-19 0Zm0 0h19M12 2.5c2.5 2.6 2.5 16.4 0 19M12 2.5c-2.5 2.6-2.5 16.4 0 19"/></svg>
                                            </span>
                                            <span>
                                                <span class="block font-medium italic text-[var(--color-muted)]">Not signed in</span>
                                                <span class="block text-xs text-gray-400">{{ $last->ip ?? '—' }}{{ $last?->country ? ' · '.$last->country : '' }}</span>
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="font-medium text-[var(--color-heading)]">{{ $last->product_name ?? '—' }}</span>
                                    <span class="block text-xs text-[var(--color-muted)]">{{ $last->label ?? '' }}</span>
                                    @if ($s->products > 1)
                                        <span class="block text-[11px] text-gray-400">+{{ $s->products - 1 }} other product(s)</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span class="inline-flex rounded-full bg-[var(--color-primary-soft)] px-2.5 py-0.5 text-xs font-bold text-[var(--color-primary)]">{{ number_format($s->items) }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($hasOrdered)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600">Ordered</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-600">Not ordered</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">
                                    {{ \Illuminate\Support\Carbon::parse($s->last_added)->format('d M Y, h:i A') }}
                                    <span class="block text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($s->last_added)->diffForHumans() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-12 text-center text-gray-400">
                                No cart activity recorded yet.
                                <span class="mt-1 block text-xs">Adds are recorded from the website as they happen — nothing before this feature went live.</span>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4">{{ $shoppers->links() }}</div>
        </div>

        {{-- Most added products --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-1 text-sm font-bold text-[var(--color-heading)]">Most Added Products</h2>
            <p class="mb-4 text-xs text-[var(--color-muted)]">What people put in their carts most.</p>
            @php $maxAdds = max(1, (int) ($topProducts->max('adds') ?? 1)); @endphp
            <div class="space-y-3">
                @forelse ($topProducts as $p)
                    <div>
                        <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                            <span class="min-w-0 truncate font-medium text-[var(--color-heading)]">{{ $p->product_name }}</span>
                            <span class="shrink-0 text-right">
                                <span class="font-bold text-[var(--color-heading)]">{{ number_format($p->adds) }}</span>
                                <span class="block text-[11px] text-[var(--color-muted)]">{{ number_format($p->shoppers) }} shopper(s)</span>
                            </span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-[var(--color-primary)]" style="width: {{ round($p->adds / $maxAdds * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Nothing added yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
