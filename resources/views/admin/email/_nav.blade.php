{{-- Email Manager sub-navigation. Only the pages the user may open are shown, so the tab bar
     never offers something that 403s. Pages still to come are marked so the menu reads honestly.
     Configuration is NOT a tab here — the SMTP accounts are their own sidebar entry (Email
     Config), kept apart from the day-to-day sending tools. --}}
@php
    $u = auth()->user();
    $tabs = [
        ['label' => 'Templates', 'route' => 'admin.email.templates', 'active' => 'admin.email.templates*', 'perm' => 'email.templates', 'ready' => true],
        ['label' => 'Queue', 'route' => 'admin.email.queue', 'active' => 'admin.email.queue*', 'perm' => 'email.queue', 'ready' => true],
        ['label' => 'Logs', 'route' => 'admin.email.logs', 'active' => 'admin.email.logs*', 'perm' => 'email.logs', 'ready' => true],
        ['label' => 'Analytics', 'route' => 'admin.email.analytics', 'active' => 'admin.email.analytics*', 'perm' => 'email.analytics', 'ready' => true],
        ['label' => 'Notification Rules', 'route' => 'admin.email.rules', 'active' => 'admin.email.rules*', 'perm' => 'email.rules', 'ready' => true],
        ['label' => 'Manual Email', 'route' => 'admin.email.campaigns', 'active' => 'admin.email.campaigns*', 'perm' => 'email.send', 'ready' => true],
    ];
@endphp

<div class="mb-5 flex gap-1 overflow-x-auto border-b border-gray-100">
    @foreach ($tabs as $tab)
        @continue (! $u->hasPermission($tab['perm']))
        @if ($tab['ready'])
            <a href="{{ route($tab['route']) }}"
               class="shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ request()->routeIs($tab['active']) ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
                {{ $tab['label'] }}
            </a>
        @else
            <span class="shrink-0 cursor-not-allowed border-b-2 border-transparent px-4 py-2.5 text-sm font-semibold text-gray-300" title="Coming in a later step">
                {{ $tab['label'] }}
            </span>
        @endif
    @endforeach
</div>
