{{-- Email Manager shell: page heading + the section's own side menu, with the page's content in
     the column beside it. Every Email Manager page wraps its body in this.

     The layout is plain CSS, not Tailwind utilities: admin deploys do not run a Tailwind build, and
     lg:flex-col / lg:w-56 / lg:top-4 are not in the compiled stylesheet, so the sidebar would have
     collapsed in production. Below 1024px the menu falls back to a scrollable strip on top, which
     is the only thing that fits a phone. --}}
@php
    $u = auth()->user();
    $items = [
        ['label' => 'Email', 'route' => 'admin.email.campaigns', 'active' => 'admin.email.campaigns*', 'perm' => 'email.send'],
        ['label' => 'Template', 'route' => 'admin.email.templates', 'active' => 'admin.email.templates*', 'perm' => 'email.templates'],
        ['label' => 'Queue', 'route' => 'admin.email.queue', 'active' => 'admin.email.queue*', 'perm' => 'email.queue'],
        ['label' => 'Logs', 'route' => 'admin.email.logs', 'active' => 'admin.email.logs*', 'perm' => 'email.logs'],
        ['label' => 'Notification Rules', 'route' => 'admin.email.rules', 'active' => 'admin.email.rules*', 'perm' => 'email.rules'],
        ['label' => 'Analytics', 'route' => 'admin.email.analytics', 'active' => 'admin.email.analytics*', 'perm' => 'email.analytics'],
    ];
@endphp

<style>
    .email-shell{display:flex;flex-direction:column;gap:1.25rem}
    .email-shell__nav{display:flex;gap:.25rem;overflow-x:auto;padding-bottom:.25rem}
    .email-shell__link{display:block;flex:none;white-space:nowrap;border-radius:.5rem;padding:.55rem .85rem;
        font-size:.875rem;font-weight:600;color:var(--color-muted);text-decoration:none;transition:background-color .15s ease,color .15s ease}
    .email-shell__link:hover{background:#f9fafb;color:var(--color-heading)}
    .email-shell__link.is-active{background:var(--color-primary-soft);color:var(--color-primary)}
    .email-shell__body{min-width:0;flex:1}
    @media (min-width:1024px){
        .email-shell{flex-direction:row;gap:1.5rem}
        .email-shell__nav{flex-direction:column;width:13.5rem;flex:none;overflow-x:visible;padding-bottom:0;
            position:sticky;top:1rem;align-self:flex-start}
        .email-shell__link{white-space:normal}
    }
</style>

<div class="mb-5">
    <h1 class="text-xl font-bold text-[var(--color-heading)]">Email Manager</h1>
    <p class="text-sm text-[var(--color-muted)]">How mail is templated, queued, sent and tracked. SMTP accounts live under Email Config.</p>
</div>

<div class="email-shell">
    {{-- Only the pages this user may open are listed, so the menu never offers something that 403s. --}}
    <nav class="email-shell__nav">
        @foreach ($items as $item)
            @continue (! $u->hasPermission($item['perm']))
            <a href="{{ route($item['route']) }}"
               class="email-shell__link {{ request()->routeIs($item['active']) ? 'is-active' : '' }}"
               @if (request()->routeIs($item['active'])) aria-current="page" @endif>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="email-shell__body">
        {{ $slot }}
    </div>
</div>
