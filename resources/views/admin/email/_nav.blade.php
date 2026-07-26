{{-- Email Management sub-navigation. Only the pages the user may open are shown, so the tab bar
     never offers something that 403s. Pages still to come are marked so the menu reads honestly. --}}
@php
    $u = auth()->user();
    $tabs = [
        ['label' => 'Configuration', 'route' => 'admin.email.configs', 'active' => 'admin.email.configs*', 'perm' => 'email.configure', 'ready' => true],
        ['label' => 'Templates', 'route' => 'admin.email-settings', 'active' => 'admin.email-settings*', 'perm' => 'email.templates', 'ready' => true],
        ['label' => 'Queue', 'route' => null, 'perm' => 'email.queue', 'ready' => false],
        ['label' => 'Logs', 'route' => null, 'perm' => 'email.logs', 'ready' => false],
        ['label' => 'Analytics', 'route' => null, 'perm' => 'email.analytics', 'ready' => false],
        ['label' => 'Notification Rules', 'route' => null, 'perm' => 'email.rules', 'ready' => false],
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
