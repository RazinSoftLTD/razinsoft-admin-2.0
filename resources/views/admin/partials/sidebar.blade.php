@php
    $user = auth()->user();

    // Visibility: 'soon'/'admin' => super-admin only; 'perm' => needs that permission (admins hold all);
    // otherwise visible to any panel user.
    $canSee = function ($i) use ($user) {
        if (! empty($i['soon']) || ! empty($i['admin'])) {
            return (bool) $user?->isAdmin();
        }
        if (! empty($i['perm'])) {
            return (bool) $user?->hasPermission($i['perm']);
        }
        return true;
    };

    // Icon paths (space-separated <path d="…">).
    $ic = [
        'dashboard' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
        'crm' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M19 8v6 M22 11h-6',
        'clients' => 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3 20v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1M16 15a4 4 0 0 1 4 4v1',
        'leads' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M22 11h-6',
        'followup' => 'M12 8v4l3 2 M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'deals' => 'M3 3v18h18 M7 14l4-4 3 3 5-6',
        'messaging' => 'M4 5h16v12H7l-3 3V5Z M8 9h8M8 13h5',
        'tickets' => 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7Z M15 5v14',
        'hr' => 'M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z M9 7h6 M12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z M8 17a4 4 0 0 1 8 0',
        'employees' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        'leave' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z',
        'attendance' => 'M12 8v4l2 1 M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'holiday' => 'M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z M12 12v9 M4 21h16 M5 5 3.5 3.5M19 5l1.5-1.5',
        'designation' => 'M12 2a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z M6 21a6 6 0 0 1 12 0',
        'separation' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M22 11h-6',
        'finance' => 'M3 7l2-4h14l2 4 M3 7h18v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z M16 13h3',
        'proposal' => 'M7 3h7l5 5v13H7zM14 3v5h5 M9 13h6M9 17h4',
        'estimation' => 'M6 2h12a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Z M8 6h8M8 10h2M12 10h2M16 10h.01M8 14h2M12 14h2',
        'invoice' => 'M7 3h7l5 5v13H7zM14 3v5h5 M9 13h6M9 17h4',
        'currency' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20 M9.5 9a2.5 2.5 0 0 1 5 0M14.5 15a2.5 2.5 0 0 1-5 0M12 7v10',
        'expense' => 'M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z M3 10h18 M7 15h3',
        'bank' => 'M3 10 12 4l9 6 M5 10v8M9 10v8M15 10v8M19 10v8 M3 21h18',
        'orders' => 'M3 7h18l-1.4 12a2 2 0 0 1-2 1.8H6.4a2 2 0 0 1-2-1.8L3 7Z M8 7a4 4 0 1 1 8 0',
        'products' => 'M3 7l9-4 9 4-9 4-9-4Zm0 0v10l9 4 9-4V7M12 11v8',
        'coupons' => 'M7 7h.01M3 5a2 2 0 0 1 2-2h6l9 9-8 8-9-9V5Z',
        'reviews' => 'm12 17.3-6.2 3.7 1.6-7L2 9.2l7.1-.6L12 2l2.9 6.6 7.1.6-5.4 4.8 1.6 7z',
        'questions' => 'M21 11.5a8.4 8.4 0 0 1-9 8.4L3 21l1.1-3.3A8.4 8.4 0 1 1 21 11.5Z',
        'marketing' => 'M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1Z M15 8a4 4 0 0 1 0 8',
        'searches' => 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Z M21 21l-4.3-4.3',
        'subscribers' => 'M4 5h16v12H7l-3 3V5Z M8 9h8M8 13h4',
        'reports' => 'M3 3v18h18 M7 14l3-3 3 3 4-5',
        'blog' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20 M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z',
        'article' => 'M7 3h7l5 5v13H7zM14 3v5h5 M9 13h6M9 17h6',
        'category' => 'M7 7h.01M3 5a2 2 0 0 1 2-2h6l9 9-8 8-9-9V5Z',
        'author' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M4 21a8 8 0 0 1 16 0',
        'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M19.4 13a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 0 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 0 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z',
        'roles' => 'M12 2a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z M4 21a8 8 0 0 1 16 0 M18 8l2 2 3-3',
        'users' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
        'chat' => 'M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v10Z M8 9h9M8 13h6',
    ];

    $nav = [
        ['type' => 'link', 'label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => $ic['dashboard']],

        ['type' => 'link', 'label' => 'Clients', 'route' => 'admin.clients.index', 'active' => 'admin.clients.*', 'perm' => 'clients.view', 'icon' => $ic['clients']],
        ['type' => 'link', 'label' => 'Tickets', 'route' => 'admin.tickets.index', 'active' => 'admin.tickets.*', 'perm' => 'tickets.view', 'icon' => $ic['tickets'], 'badge' => \App\Models\Ticket::where('unread_by_admin', true)->count() ?: null],
        ['type' => 'link', 'label' => 'Message', 'route' => 'admin.messages.index', 'active' => 'admin.messages.*', 'perm' => 'messages.view', 'icon' => $ic['messaging'], 'badge' => \App\Models\ContactMessage::where('is_read', false)->count()],
        ['type' => 'link', 'label' => 'Teams', 'route' => 'admin.chat.index', 'active' => 'admin.chat.*', 'icon' => $ic['chat'], 'badge' => \App\Http\Controllers\Admin\ChatController::unreadTotal($user) ?: null],

        ['type' => 'group', 'label' => 'CRM', 'icon' => $ic['crm'], 'items' => [
            ['label' => 'Leads', 'route' => 'admin.leads.index', 'active' => ['admin.leads.index', 'admin.leads.show', 'admin.leads.edit', 'admin.leads.create', 'admin.leads.import.form'], 'perm' => 'leads.view', 'icon' => $ic['leads']],
            ['label' => 'Follow-up', 'route' => 'admin.leads.follow-up', 'active' => 'admin.leads.follow-up', 'perm' => 'leads.view', 'icon' => $ic['followup']],
            ['label' => 'Deals', 'route' => 'admin.deals.index', 'active' => 'admin.deals.*', 'perm' => 'deals.view', 'icon' => $ic['deals']],
        ]],

        ['type' => 'group', 'label' => 'HR', 'icon' => $ic['hr'], 'items' => [
            ['label' => 'Employees', 'route' => 'admin.staff.index', 'active' => 'admin.staff.*', 'perm' => 'employees.view', 'icon' => $ic['employees']],
            ['label' => 'Designation', 'route' => 'admin.designations.index', 'active' => 'admin.designations.*', 'perm' => 'designations.view', 'icon' => $ic['designation']],
            ['label' => 'Department', 'route' => 'admin.departments.index', 'active' => 'admin.departments.*', 'perm' => 'departments.view', 'icon' => $ic['hr']],
            ['label' => 'Leave', 'route' => 'admin.leaves.index', 'active' => 'admin.leaves.*', 'perm' => 'leave.view', 'icon' => $ic['leave']],
            ['label' => 'Attendance', 'soon' => true, 'icon' => $ic['attendance']],
            ['label' => 'Holiday', 'soon' => true, 'icon' => $ic['holiday']],
            ['label' => 'Designation', 'soon' => true, 'icon' => $ic['designation']],
            ['label' => 'Separation', 'soon' => true, 'icon' => $ic['separation']],
        ]],

        ['type' => 'group', 'label' => 'Finance', 'icon' => $ic['finance'], 'items' => [
            ['label' => 'Proposal', 'soon' => true, 'icon' => $ic['proposal']],
            ['label' => 'Estimation', 'soon' => true, 'icon' => $ic['estimation']],
            ['label' => 'Invoices', 'route' => 'admin.invoices.index', 'active' => ['admin.invoices.*', 'admin.recurring.*', 'admin.invoice-templates.*'], 'perm' => 'invoices.view', 'icon' => $ic['invoice']],
            ['label' => 'Currencies', 'route' => 'admin.currencies.index', 'active' => 'admin.currencies.*', 'perm' => 'invoices.view', 'icon' => $ic['currency']],
            ['label' => 'Expense', 'soon' => true, 'icon' => $ic['expense']],
            ['label' => 'Bank', 'soon' => true, 'icon' => $ic['bank']],
        ]],

        ['type' => 'link', 'label' => 'Orders', 'route' => 'admin.orders.index', 'active' => 'admin.orders.*', 'perm' => 'orders.view', 'icon' => $ic['orders']],

        ['type' => 'group', 'label' => 'Products', 'icon' => $ic['products'], 'items' => [
            ['label' => 'All Products', 'route' => 'admin.products.index', 'active' => 'admin.products.*', 'perm' => 'products.view', 'icon' => $ic['products']],
            ['label' => 'Coupons', 'route' => 'admin.coupons.index', 'active' => 'admin.coupons.*', 'perm' => 'coupons.view', 'icon' => $ic['coupons']],
            ['label' => 'Reviews', 'route' => 'admin.reviews.index', 'active' => 'admin.reviews.*', 'perm' => 'reviews.view', 'icon' => $ic['reviews']],
            ['label' => 'Questions', 'route' => 'admin.questions.index', 'active' => 'admin.questions.*', 'perm' => 'questions.view', 'icon' => $ic['questions'], 'badge' => \App\Models\ProductQuestion::whereDoesntHave('answers', fn ($a) => $a->where('is_admin', true))->count()],
        ]],

        ['type' => 'group', 'label' => 'Marketing', 'icon' => $ic['marketing'], 'items' => [
            ['label' => 'Searches', 'route' => 'admin.searches.index', 'active' => 'admin.searches.*', 'perm' => 'searches.view', 'icon' => $ic['searches']],
            ['label' => 'Subscribers', 'route' => 'admin.subscribers.index', 'active' => 'admin.subscribers.*', 'perm' => 'subscribers.view', 'icon' => $ic['subscribers']],
        ]],

        ['type' => 'link', 'label' => 'Reports', 'soon' => true, 'icon' => $ic['reports']],

        ['type' => 'group', 'label' => 'Blog', 'icon' => $ic['blog'], 'items' => [
            ['label' => 'Articles', 'route' => 'admin.articles.index', 'active' => 'admin.articles.*', 'perm' => 'blog.view', 'icon' => $ic['article']],
            ['label' => 'Categories', 'route' => 'admin.article-categories.index', 'active' => 'admin.article-categories.*', 'perm' => 'blog.view', 'icon' => $ic['category']],
            ['label' => 'Authors', 'route' => 'admin.authors.index', 'active' => 'admin.authors.*', 'perm' => 'blog.view', 'icon' => $ic['author']],
        ]],

        ['type' => 'group', 'label' => 'Settings', 'icon' => $ic['settings'], 'items' => [
            ['label' => 'My Profile', 'route' => 'admin.my-profile.edit', 'active' => 'admin.my-profile.*', 'icon' => $ic['author']],
            ['label' => 'Roles & Permissions', 'route' => 'admin.roles.index', 'active' => 'admin.roles.*', 'admin' => true, 'icon' => $ic['roles']],
            ['label' => 'Ticket Settings', 'route' => 'admin.tickets.settings', 'active' => 'admin.tickets.settings', 'perm' => 'tickets.edit', 'icon' => $ic['tickets']],
        ]],
    ];

    // Filter each group's items and drop empty groups / hidden links.
    $nav = array_values(array_filter(array_map(function ($entry) use ($canSee) {
        if (($entry['type'] ?? 'link') === 'group') {
            $entry['items'] = array_values(array_filter($entry['items'], $canSee));
            return count($entry['items']) ? $entry : null;
        }
        return $canSee($entry) ? $entry : null;
    }, $nav)));

    $isItemActive = fn ($i) => ! empty($i['route']) && request()->routeIs(...(array) ($i['active'] ?? $i['route']));
@endphp

<div class="flex h-16 items-center gap-2 px-6">
    <span class="grid h-9 w-9 place-items-center rounded-lg bg-[var(--color-primary)] font-bold text-white">R</span>
    <span class="text-lg font-extrabold text-[var(--color-heading)]">RazinSoft</span>
</div>

<nav class="mt-2 space-y-1 px-3 pb-6">
    <p class="px-3 pb-2 pt-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Menu</p>

    @foreach ($nav as $entry)
        @if (($entry['type'] ?? 'link') === 'link')
            @php $active = $isItemActive($entry); $soon = ! empty($entry['soon']); @endphp
            <a href="{{ $soon ? '#' : route($entry['route']) }}" @if ($soon) @click.prevent aria-disabled="true" @endif
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $active ? 'bg-[var(--color-primary)] text-white shadow-sm shadow-indigo-300' : 'text-[var(--color-muted)] hover:bg-gray-50 hover:text-[var(--color-heading)]' }} {{ $soon ? 'cursor-default opacity-60' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $entry['icon'] }}"/>
                </svg>
                <span class="flex-1">{{ $entry['label'] }}</span>
                @if ($soon)<span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-400">Soon</span>@endif
                <span data-nav-badge="{{ \Illuminate\Support\Str::slug($entry['label']) }}" class="grid h-5 min-w-5 place-items-center rounded-full bg-red-500 px-1.5 text-[11px] font-bold text-white {{ empty($entry['badge'] ?? null) ? 'hidden' : '' }}">{{ $entry['badge'] ?? '' }}</span>
            </a>
        @else
            @php $groupActive = collect($entry['items'])->contains($isItemActive); @endphp
            <div x-data="{ open: {{ $groupActive ? 'true' : 'false' }} }" class="pt-1">
                <button type="button" @click="open = !open"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $groupActive ? 'text-[var(--color-heading)]' : 'text-[var(--color-muted)] hover:bg-gray-50 hover:text-[var(--color-heading)]' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $entry['icon'] }}"/>
                    </svg>
                    <span class="flex-1 text-left">{{ $entry['label'] }}</span>
                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>

                <div class="grid overflow-hidden transition-all duration-200 ease-out" :class="open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
                    <div class="min-h-0 overflow-hidden">
                        <div class="ml-4 mt-1 space-y-1 border-l border-gray-100 pl-3">
                            @foreach ($entry['items'] as $item)
                                @php $active = $isItemActive($item); $soon = ! empty($item['soon']); @endphp
                                <a href="{{ $soon ? '#' : route($item['route']) }}" @if ($soon) @click.prevent aria-disabled="true" @endif
                                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $active ? 'bg-[var(--color-primary)] text-white shadow-sm shadow-indigo-300' : 'text-[var(--color-muted)] hover:bg-gray-50 hover:text-[var(--color-heading)]' }} {{ $soon ? 'cursor-default opacity-60' : '' }}">
                                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                                    </svg>
                                    <span class="flex-1">{{ $item['label'] }}</span>
                                    @if ($soon)<span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-400">Soon</span>@endif
                                    @if (! empty($item['badge']))<span class="grid h-5 min-w-5 place-items-center rounded-full bg-red-500 px-1.5 text-[11px] font-bold text-white">{{ $item['badge'] }}</span>@endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</nav>
