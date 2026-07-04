@php
    $user = auth()->user();
    $isAdmin = $user?->isAdmin();

    // Visibility per item: 'admin' => super-admin only; 'perm' => needs that permission
    // (admins hold all); neither => any panel user (e.g. Dashboard).
    $canSee = function ($i) use ($user) {
        if (! empty($i['admin'])) {
            return (bool) $user?->isAdmin();
        }
        if (! empty($i['perm'])) {
            return (bool) $user?->hasPermission($i['perm']);
        }
        return true;
    };

    $menu = collect([
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
        ['label' => 'Employee', 'route' => 'admin.staff.index', 'active' => 'admin.staff.*', 'admin' => true, 'icon' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8'],
        ['label' => 'Products', 'route' => 'admin.products.index', 'active' => 'admin.products.*', 'perm' => 'products', 'icon' => 'M3 7l9-4 9 4-9 4-9-4Zm0 0v10l9 4 9-4V7M12 11v8'],
        ['label' => 'Orders', 'route' => 'admin.orders.index', 'active' => 'admin.orders.*', 'perm' => 'orders', 'icon' => 'M3 7h18l-1.4 12a2 2 0 0 1-2 1.8H6.4a2 2 0 0 1-2-1.8L3 7Z M8 7a4 4 0 1 1 8 0'],
        ['label' => 'Questions', 'route' => 'admin.questions.index', 'active' => 'admin.questions.*', 'perm' => 'questions', 'icon' => 'M21 11.5a8.4 8.4 0 0 1-9 8.4L3 21l1.1-3.3A8.4 8.4 0 1 1 21 11.5Z', 'badge' => \App\Models\ProductQuestion::whereDoesntHave('answers', fn ($a) => $a->where('is_admin', true))->count()],
        ['label' => 'Reviews', 'route' => 'admin.reviews.index', 'active' => 'admin.reviews.*', 'perm' => 'reviews', 'icon' => 'm12 17.3-6.2 3.7 1.6-7L2 9.2l7.1-.6L12 2l2.9 6.6 7.1.6-5.4 4.8 1.6 7z'],
        ['label' => 'Messages', 'route' => 'admin.messages.index', 'active' => 'admin.messages.*', 'perm' => 'messages', 'icon' => 'M4 5h16v12H7l-3 3V5Z M8 9h8M8 13h5', 'badge' => \App\Models\ContactMessage::where('is_read', false)->count()],
        ['label' => 'Searches', 'route' => 'admin.searches.index', 'active' => 'admin.searches.*', 'perm' => 'searches', 'icon' => 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Z M21 21l-4.3-4.3'],
        ['label' => 'Coupons', 'route' => 'admin.coupons.index', 'active' => 'admin.coupons.*', 'perm' => 'coupons', 'icon' => 'M7 7h.01M3 5a2 2 0 0 1 2-2h6l9 9-8 8-9-9V5Z'],
        ['label' => 'Clients', 'route' => 'admin.clients.index', 'active' => 'admin.clients.*', 'perm' => 'clients', 'icon' => 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3 20v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1M16 15a4 4 0 0 1 4 4v1'],
    ])->filter($canSee)->all();

    // Collapsible groups — each item carries its own permission; a group shows if any item is visible.
    $groups = collect([
        [
            'label' => 'CRM',
            'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M19 8v6 M22 11h-6',
            'items' => [
                ['label' => 'Leads', 'route' => 'admin.leads.index', 'active' => ['admin.leads.index', 'admin.leads.show', 'admin.leads.edit', 'admin.leads.create', 'admin.leads.import.form'], 'perm' => 'leads', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M22 11h-6'],
                ['label' => 'Follow-up', 'route' => 'admin.leads.follow-up', 'active' => 'admin.leads.follow-up', 'perm' => 'leads', 'icon' => 'M12 8v4l3 2 M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                ['label' => 'Deals', 'route' => 'admin.deals.index', 'active' => 'admin.deals.*', 'perm' => 'deals', 'icon' => 'M3 3v18h18 M7 14l4-4 3 3 5-6'],
            ],
        ],
        [
            'label' => 'Invoices',
            'icon' => 'M7 3h7l5 5v13H7zM14 3v5h5 M9 13h6M9 17h4',
            'items' => [
                ['label' => 'All Invoices', 'route' => 'admin.invoices.index', 'active' => ['admin.invoices.index', 'admin.invoices.edit', 'admin.invoices.show'], 'perm' => 'invoices', 'icon' => 'M7 3h7l5 5v13H7zM14 3v5h5 M9 13h6M9 17h4'],
                ['label' => 'Create Invoice', 'route' => 'admin.invoices.create', 'active' => 'admin.invoices.create', 'perm' => 'invoices', 'icon' => 'M12 5v14M5 12h14'],
                ['label' => 'Recurring', 'route' => 'admin.recurring.index', 'active' => 'admin.recurring.*', 'perm' => 'invoices', 'icon' => 'M4 4v6h6 M20 20v-6h-6 M20 8a8 8 0 0 0-14-3M4 16a8 8 0 0 0 14 3'],
                ['label' => 'Currencies', 'route' => 'admin.currencies.index', 'active' => 'admin.currencies.*', 'perm' => 'invoices', 'icon' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20 M9.5 9a2.5 2.5 0 0 1 5 0M14.5 15a2.5 2.5 0 0 1-5 0M12 7v10'],
            ],
        ],
        [
            'label' => 'Blog',
            'icon' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20 M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z',
            'items' => [
                ['label' => 'Articles', 'route' => 'admin.articles.index', 'active' => 'admin.articles.*', 'perm' => 'blog', 'icon' => 'M7 3h7l5 5v13H7zM14 3v5h5 M9 13h6M9 17h6'],
                ['label' => 'Categories', 'route' => 'admin.article-categories.index', 'active' => 'admin.article-categories.*', 'perm' => 'blog', 'icon' => 'M7 7h.01M3 5a2 2 0 0 1 2-2h6l9 9-8 8-9-9V5Z'],
                ['label' => 'Authors', 'route' => 'admin.authors.index', 'active' => 'admin.authors.*', 'perm' => 'blog', 'icon' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M4 21a8 8 0 0 1 16 0'],
                ['label' => 'Subscribers', 'route' => 'admin.subscribers.index', 'active' => 'admin.subscribers.*', 'perm' => 'subscribers', 'icon' => 'M4 5h16v12H7l-3 3V5Z M8 9h8M8 13h4'],
            ],
        ],
    ])->map(function ($g) use ($canSee) {
        $g['items'] = array_values(array_filter($g['items'], $canSee));
        return $g;
    })->filter(fn ($g) => count($g['items']))->all();
@endphp

<div class="flex h-16 items-center gap-2 px-6">
    <span class="grid h-9 w-9 place-items-center rounded-lg bg-[var(--color-primary)] font-bold text-white">R</span>
    <span class="text-lg font-extrabold text-[var(--color-heading)]">RazinSoft</span>
</div>

<nav class="mt-2 space-y-1 px-3 pb-6">
    <p class="px-3 pb-2 pt-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Menu</p>
    @foreach ($menu as $item)
        @php $isActive = request()->routeIs(...(array) ($item['active'] ?? $item['route'])); @endphp
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

    {{-- Collapsible groups (admin only) --}}
    @foreach ($groups as $group)
        @php $groupActive = collect($group['items'])->contains(fn ($i) => request()->routeIs(...(array) ($i['active'] ?? $i['route']))); @endphp
        <div x-data="{ open: {{ $groupActive ? 'true' : 'false' }} }" class="pt-1">
            <button type="button" @click="open = !open"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $groupActive ? 'text-[var(--color-heading)]' : 'text-[var(--color-muted)] hover:bg-gray-50 hover:text-[var(--color-heading)]' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                    @foreach (explode(' ', $group['icon']) as $d)<path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}"/>@endforeach
                </svg>
                <span class="flex-1 text-left">{{ $group['label'] }}</span>
                <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
            </button>

            <div class="grid overflow-hidden transition-all duration-200 ease-out" :class="open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
                <div class="min-h-0 overflow-hidden">
                    <div class="mt-1 space-y-1 border-l border-gray-100 pl-3 ml-4">
                        @foreach ($group['items'] as $item)
                            @php $isActive = request()->routeIs(...(array) ($item['active'] ?? $item['route'])); @endphp
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $isActive ? 'bg-[var(--color-primary)] text-white shadow-sm shadow-indigo-300' : 'text-[var(--color-muted)] hover:bg-gray-50 hover:text-[var(--color-heading)]' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                    @foreach (explode(' ', $item['icon']) as $d)<path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}"/>@endforeach
                                </svg>
                                <span class="flex-1">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</nav>
