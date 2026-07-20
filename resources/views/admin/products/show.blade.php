@extends('admin.layouts.app')
@section('title', $product->name)

@php
    $sections = [
        ['key' => 'plans', 'title' => 'Plans', 'count' => $product->plans_count, 'icon' => 'M3 7l9-4 9 4-9 4-9-4Zm0 0v10l9 4 9-4V7M12 11v8'],
        ['key' => 'features', 'title' => 'Features', 'count' => $product->features_count, 'icon' => 'M5 3l1.5 4L11 8.5 6.5 10 5 14l-1.5-4L-1 8.5 3.5 7Z M17 10l1 3 3 1-3 1-1 3-1-3-3-1 3-1Z'],
        ['key' => 'gallery', 'title' => 'Gallery', 'count' => $product->gallery_groups_count, 'icon' => 'M3 5h18v14H3zM3 15l5-5 4 4 3-3 6 6'],
        ['key' => 'demos', 'title' => 'Demos & Downloads', 'count' => $product->demos_count, 'icon' => 'M12 3v12m0 0 4-4m-4 4-4-4M5 19h14'],
        ['key' => 'tech', 'title' => 'Tech Stack', 'count' => $product->tech_count, 'icon' => 'm8 16-4-4 4-4m8 0 4 4-4 4M14 4l-4 16'],
        ['key' => 'suitable', 'title' => 'Suitable For', 'count' => $product->suitable_for_count, 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        ['key' => 'docs', 'title' => 'Documentation & Resources', 'count' => $product->docs_count, 'icon' => 'M7 3h7l5 5v13H7zM14 3v5h5'],
        ['key' => 'faqs', 'title' => 'FAQs', 'count' => $product->faqs_count, 'icon' => 'M9.1 9a3 3 0 1 1 4 2.8c-.9.4-1.6 1.2-1.6 2.2M12 17h.01'],
        ['key' => 'files', 'title' => 'Source Files', 'count' => $product->files_count, 'icon' => 'M21 8v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6l3 3h5a2 2 0 0 1 2 2Z'],
    ];
    $isPublished = $product->status === 'published';
@endphp

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <a href="{{ route('admin.products.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to products
        </a>
        @if ($product->slug && $isPublished)
            <a href="{{ rtrim(config('services.frontend_url'), '/') }}/products/{{ $product->slug }}" target="_blank" class="text-sm font-semibold text-[var(--color-primary)] hover:underline">Preview on site ↗</a>
        @endif
    </div>

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="flex items-start gap-4">
            @if ($product->thumbnail)
                <img src="{{ \App\Http\Resources\ProductResource::media($product->thumbnail) }}" class="h-16 w-16 rounded-xl border border-gray-100 object-cover">
            @endif
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $product->name }}</h1>
                    <x-admin.status :status="$product->status" />
                </div>
                <p class="mt-1 text-sm text-[var(--color-muted)]">{{ $product->tagline }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ $product->category ?? 'Uncategorised' }} · v{{ $product->version ?? '—' }} · /{{ $product->slug }}</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (auth()->user()->allows('products', 'publish'))
            <form method="POST" action="{{ route('admin.products.publish', $product) }}">
                @csrf
                <button class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white {{ $isPublished ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-500 hover:bg-emerald-600' }}">
                    @if ($isPublished)
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.5 10.5 0 0 0 1.93 12s3.6 7.5 10.07 7.5a9.7 9.7 0 0 0 5.07-1.42M6.6 6.6A9.7 9.7 0 0 1 12 4.5c6.47 0 10.07 7.5 10.07 7.5a10.6 10.6 0 0 1-2.78 3.72M9.9 9.9a3 3 0 1 0 4.2 4.2M3 3l18 18"/></svg>
                        Unpublish
                    @else
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.6-7.5 10-7.5S22 12 22 12s-3.6 7.5-10 7.5S2 12 2 12Z M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>
                        Publish
                    @endif
                </button>
            </form>
            @endif
            <a href="{{ route('admin.products.edit', $product) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                Edit general &amp; media
            </a>
            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Delete this product?')">
                @csrf @method('DELETE')
                <button class="rounded-lg border border-gray-200 p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                </button>
            </form>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- General --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">General</h3>
            <dl class="space-y-3 text-sm">
                @foreach ([
                    'Category' => $product->category ?? '—',
                    'Badge' => $product->badge ? \Illuminate\Support\Str::headline($product->badge) : '—',
                    'Version' => $product->version ?? '—',
                    'Currency' => $product->currency ?? 'USD',
                    'Featured' => $product->is_featured ? 'Yes' : 'No',
                    'Sort order' => $product->sort_order ?? 0,
                ] as $label => $val)
                    <div class="flex justify-between gap-4 border-b border-gray-50 pb-2">
                        <dt class="text-[var(--color-muted)]">{{ $label }}</dt>
                        <dd class="font-semibold text-[var(--color-heading)]">{{ $val }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($product->overview)
                <div class="mt-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Overview</p>
                    <p class="text-sm text-gray-600">{{ \Illuminate\Support\Str::limit(strip_tags($product->overview), 280) }}</p>
                </div>
            @endif
        </div>

        {{-- Stats & media --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">Stats &amp; media</h3>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-gray-50 p-3"><p class="text-lg font-bold text-[var(--color-heading)]">{{ number_format((float) $product->rating, 1) }}</p><p class="text-xs text-gray-400">Rating</p></div>
                <div class="rounded-lg bg-gray-50 p-3"><p class="text-lg font-bold text-[var(--color-heading)]">{{ $product->reviews_count }}</p><p class="text-xs text-gray-400">Reviews</p></div>
                <div class="rounded-lg bg-gray-50 p-3"><p class="text-lg font-bold text-[var(--color-heading)]">{{ $product->sales_count }}</p><p class="text-xs text-gray-400">Sales</p></div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Thumbnail</p>
                    @if ($product->thumbnail)<img src="{{ \App\Http\Resources\ProductResource::media($product->thumbnail) }}" class="h-24 w-full rounded-lg border border-gray-100 object-cover">@else<div class="grid h-24 place-items-center rounded-lg border border-dashed border-gray-200 text-xs text-gray-400">None</div>@endif
                </div>
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Hero image</p>
                    @if ($product->hero_image)<img src="{{ \App\Http\Resources\ProductResource::media($product->hero_image) }}" class="h-24 w-full rounded-lg border border-gray-100 object-cover">@else<div class="grid h-24 place-items-center rounded-lg border border-dashed border-gray-200 text-xs text-gray-400">None</div>@endif
                </div>
            </div>
        </div>
    </div>

    {{-- Section tabs/cards --}}
    <h3 class="mb-3 mt-8 text-sm font-bold uppercase tracking-wide text-gray-400">Manage sections</h3>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @if (auth()->user()->allows('products', 'relations'))
        @foreach ($sections as $s)
            <a href="{{ route('admin.products.relation.edit', [$product, $s['key']]) }}" class="group flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition hover:border-[var(--color-primary)] hover:shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">@foreach (explode(' ', $s['icon']) as $d)<path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}"/>@endforeach</svg>
                    </span>
                    <div>
                        <p class="font-semibold text-[var(--color-heading)]">{{ $s['title'] }}</p>
                        <p class="text-xs text-gray-400">{{ $s['count'] }} item{{ $s['count'] === 1 ? '' : 's' }}</p>
                    </div>
                </div>
                <svg class="h-5 w-5 text-gray-300 transition group-hover:text-[var(--color-primary)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg>
            </a>
        @endforeach
        @endif
    </div>
@endsection
