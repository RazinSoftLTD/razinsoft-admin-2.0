@extends('admin.layouts.app')
@section('title', $author->username)

@php $money = fn ($v) => '$'.number_format($v, 0); @endphp

@section('content')
    <nav class="mb-2 flex items-center gap-2 text-sm text-[var(--color-muted)]">
        <a href="{{ route('admin.codecanyon.index') }}" class="hover:text-[var(--color-heading)]">CodeCanyon</a>
        <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
        <span class="text-[var(--color-heading)]">{{ $author->username }}</span>
    </nav>

    <div class="mb-6 flex flex-wrap items-center gap-4">
        @if ($author->image)<img src="{{ $author->image }}" class="h-14 w-14 rounded-xl object-cover" alt="">@endif
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $author->username }}</h1>
            <p class="text-sm text-[var(--color-muted)]">
                {{ $author->country ?: '—' }} ·
                <a href="{{ $author->profileUrl() }}" target="_blank" rel="noopener" class="font-semibold text-[var(--color-primary)] hover:underline">View on CodeCanyon</a>
            </p>
        </div>
        @if ($author->is_own)<span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600">Our account</span>@endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Lifetime sales', number_format((int) $author->total_sales)],
            ['Products tracked', $author->products->count()],
            ['Est. revenue', $money($author->estimatedRevenue())],
            ['Followers', number_format((int) $author->followers)],
        ] as [$label, $value])
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <p class="text-sm text-[var(--color-muted)]">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold text-[var(--color-heading)]">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if ($author->badges)
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($author->badges as $b)
                <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-[var(--color-heading)] shadow-sm">
                    @if (!empty($b['image']))<img src="{{ $b['image'] }}" class="h-4 w-4" alt="">@endif{{ $b['label'] ?? $b['name'] ?? '' }}
                </span>
            @endforeach
        </div>
    @endif

    <div class="mt-6 rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-lg font-bold text-[var(--color-heading)]">Portfolio</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Product</th>
                        <th class="px-5 py-3 font-semibold">Sales</th>
                        <th class="px-5 py-3 font-semibold">Rating</th>
                        <th class="px-5 py-3 font-semibold">Price</th>
                        <th class="px-5 py-3 font-semibold">Est. revenue</th>
                        <th class="px-5 py-3 font-semibold">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($author->products as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.codecanyon.product', $p) }}" class="block max-w-md truncate font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $p->name }}</a>
                                <span class="text-xs text-[var(--color-muted)]">{{ $p->categoryLabel() }}</span>
                            </td>
                            <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ number_format($p->number_of_sales) }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->rating ? number_format((float) $p->rating, 2) : '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">${{ number_format($p->price(), 2) }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $money($p->estimatedRevenue()) }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->item_updated_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">No products synced yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
