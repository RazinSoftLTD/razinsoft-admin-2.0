@extends('admin.layouts.app')
@section('title', 'Promotion')

@section('content')
    @php $me = auth()->user(); @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-[var(--color-heading)]">Top Banner &amp; Popup</h1>
            <p class="mt-0.5 text-sm text-[var(--color-muted)]">Site-wide promos, shown while <span class="font-semibold text-emerald-600">Published</span> and inside their schedule.</p>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-400">Top Banner</h2>
        @if ($me->hasPermission('promotion.create'))
            <a href="{{ route('admin.promotions.create', ['type' => 'top_banner']) }}" class="inline-flex h-9 items-center gap-2 rounded-lg bg-[var(--color-primary)] px-3.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                Add top banner
            </a>
        @endif
    </div>
    <p class="mb-3 text-xs text-[var(--color-muted)]">A thin strip shown above the menu on every page, linking to All Products. Only one live banner shows at a time.</p>
    @include('admin.promotions._table', ['items' => $topBanners, 'emptyTitle' => 'No top banners yet', 'emptyHint' => 'Upload a 1920×120 banner, keep it as a draft to review, then publish when it\'s ready.'])

    <div class="mb-4 mt-8 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-400">Popup</h2>
        @if ($me->hasPermission('promotion.create'))
            <a href="{{ route('admin.promotions.create', ['type' => 'popup']) }}" class="inline-flex h-9 items-center gap-2 rounded-lg bg-[var(--color-primary)] px-3.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                Add popup
            </a>
        @endif
    </div>
    <p class="mb-3 text-xs text-[var(--color-muted)]">A modal shown once per page load. Once a visitor closes it, it won't show again until the site is reloaded.</p>
    @include('admin.promotions._table', ['items' => $popups, 'emptyTitle' => 'No popups yet', 'emptyHint' => 'Upload a promo graphic, keep it as a draft to review, then publish when it\'s ready.'])
@endsection
