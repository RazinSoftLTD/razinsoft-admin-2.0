@php
    $nav = [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
        ['label' => 'Products', 'route' => 'admin.products.index', 'active' => 'admin.products.*', 'icon' => 'M3 7l9-4 9 4-9 4-9-4Zm0 0v10l9 4 9-4V7M12 11v8'],
        ['label' => 'Orders', 'route' => 'admin.orders.index', 'active' => 'admin.orders.*', 'icon' => 'M3 7h18l-1.4 12a2 2 0 0 1-2 1.8H6.4a2 2 0 0 1-2-1.8L3 7Z M8 7a4 4 0 1 1 8 0'],
        ['label' => 'Questions', 'route' => 'admin.questions.index', 'active' => 'admin.questions.*', 'icon' => 'M21 11.5a8.4 8.4 0 0 1-9 8.4L3 21l1.1-3.3A8.4 8.4 0 1 1 21 11.5Z', 'badge' => \App\Models\ProductQuestion::whereDoesntHave('answers', fn ($a) => $a->where('is_admin', true))->count()],
        ['label' => 'Reviews', 'route' => 'admin.reviews.index', 'active' => 'admin.reviews.*', 'icon' => 'm12 17.3-6.2 3.7 1.6-7L2 9.2l7.1-.6L12 2l2.9 6.6 7.1.6-5.4 4.8 1.6 7z'],
        ['label' => 'Coupons', 'route' => 'admin.coupons.index', 'active' => 'admin.coupons.*', 'icon' => 'M7 7h.01M3 5a2 2 0 0 1 2-2h6l9 9-8 8-9-9V5Z'],
        ['label' => 'Users', 'route' => 'admin.users.index', 'active' => 'admin.users.*', 'icon' => 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3 20v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1M16 15a4 4 0 0 1 4 4v1'],
    ];
@endphp

<div class="flex h-16 items-center gap-2 px-6">
    <span class="grid h-9 w-9 place-items-center rounded-lg bg-[var(--color-primary)] font-bold text-white">R</span>
    <span class="text-lg font-extrabold text-[var(--color-heading)]">RazinSoft</span>
</div>

<nav class="mt-2 space-y-1 px-3 pb-6">
    <p class="px-3 pb-2 pt-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Menu</p>
    @foreach ($nav as $item)
        @php $isActive = request()->routeIs($item['active'] ?? $item['route']); @endphp
        <a href="{{ route($item['route']) }}"
           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $isActive ? 'bg-[var(--color-primary)] text-white shadow-sm shadow-indigo-300' : 'text-[var(--color-muted)] hover:bg-gray-50 hover:text-[var(--color-heading)]' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                @foreach (explode(' ', $item['icon']) as $d)<path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}"/>@endforeach
            </svg>
            <span class="flex-1">{{ $item['label'] }}</span>
            @if (! empty($item['badge']))
                <span class="grid h-5 min-w-5 place-items-center rounded-full bg-red-500 px-1.5 text-[11px] font-bold text-white">{{ $item['badge'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
