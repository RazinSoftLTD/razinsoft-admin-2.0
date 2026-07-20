<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Don't cache snapshots — pages re-render fresh so Quill/Alpine/real-time widgets re-init cleanly. --}}
    <meta name="turbo-cache-control" content="no-cache">
    <title>@yield('title', 'Admin') · RazinSoft</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css'])
    <style>
        /* Disabled-field styling: the compiled stylesheet has no disabled:* variants. */
        .is-disabled:disabled { background-color: #f9fafb; color: #9ca3af; cursor: not-allowed; }
    </style>

    {{-- Hotwire Turbo: SPA-like navigation (menus swap without a full reload). --}}
    <script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.12/dist/turbo.es2017-umd.min.js"></script>
    <script>
        // Keep <form> submits as normal full-page requests (safest with Laravel validation,
        // redirects, flash messages and file uploads). Only link navigation is boosted.
        if (window.Turbo) window.Turbo.setFormMode('off');
        // When we land on a page that isn't a chat thread, clear the "open conversation" flag
        // so message badges resume updating.
        document.addEventListener('turbo:load', function () {
            window.Razin = window.Razin || {};
            if (!document.getElementById('thread-root')) window.Razin.openConversation = null;
        });
    </script>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('head')
</head>
<body class="h-full bg-[var(--color-body)] text-[var(--color-heading)] antialiased"
      x-data="{ sidebar: false, collapsed: localStorage.getItem('sidebarCollapsed') === '1', forceCollapse: false, lockScroll: false }"
      :class="{ 'overflow-hidden': lockScroll }"
      x-init="$watch('collapsed', v => localStorage.setItem('sidebarCollapsed', v ? '1' : '0'))">
    <!-- Sidebar -->
    <aside
        class="fixed inset-y-0 left-0 z-40 w-64 transform overflow-y-auto border-r border-gray-100 bg-white transition-transform duration-200"
        :class="{ 'translate-x-0': sidebar, '-translate-x-full': !sidebar, 'lg:translate-x-0': !(collapsed || forceCollapse), 'lg:-translate-x-full': (collapsed || forceCollapse) }"
    >
        @include('admin.partials.sidebar')
    </aside>

    <!-- Overlay (mobile) -->
    <div x-show="sidebar" @click="sidebar = false" class="fixed inset-0 z-30 bg-black/30 lg:hidden" x-cloak></div>

    <!-- Main -->
    <div class="transition-all duration-200" :class="(collapsed || forceCollapse) ? 'lg:pl-0' : 'lg:pl-64'">
        @include('admin.partials.topbar')

        <main class="p-4 sm:p-6">
            @if (session('status'))
                <div class="mb-5 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-5 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-semibold">Please fix the following:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    {{-- Global real-time: live Tickets badge on every panel page (Laravel Reverb). --}}
    @php $reverb = config('broadcasting.connections.reverb'); @endphp
    @if (! empty($reverb['key']) && auth()->check() && auth()->user()->isPanelUser())
        <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
        <script>
            window.Razin = window.Razin || {};
            (function () {
                // Persist across Turbo navigations — connect only once.
                if (window.__razinReverb) return;
                window.__razinReverb = true;
                try {
                    const pusher = new Pusher(@json($reverb['key']), {
                        wsHost: @json($reverb['options']['host'] ?? 'localhost'),
                        wsPort: {{ (int) ($reverb['options']['port'] ?? 8080) }},
                        wssPort: {{ (int) ($reverb['options']['port'] ?? 8080) }},
                        forceTLS: {{ ($reverb['options']['useTLS'] ?? false) ? 'true' : 'false' }},
                        enabledTransports: ['ws', 'wss'], cluster: '', disableStats: true,
                    });
                    window.Razin.pusher = pusher;
                    window.Razin.userId = {{ (int) auth()->id() }};

                    // Live Tickets badge (only staff with ticket access get pinged).
                    @if (auth()->user()->isAdmin() || auth()->user()->hasPermission('tickets.view'))
                    pusher.subscribe('tickets.admin').bind('unread', function (d) {
                        const badge = document.querySelector('[data-nav-badge="tickets"]');
                        if (!badge) return;
                        const n = d && d.count ? d.count : 0;
                        badge.textContent = n;
                        badge.classList.toggle('hidden', n === 0);
                    });
                    @endif

                    // Live WhatsApp badge (only users with access; count scoped to their numbers).
                    @if (auth()->user()->hasPermission('whatsapp.view'))
                    (function () {
                        const refreshWa = () => {
                            fetch(@js(route('admin.whatsapp.unread-count')), { headers: { 'Accept': 'application/json' } })
                                .then(r => r.ok ? r.json() : null)
                                .then(d => {
                                    if (!d) return;
                                    document.querySelectorAll('[data-nav-badge="whatsapp"]').forEach(b => {
                                        b.textContent = d.count > 99 ? '99+' : d.count;
                                        b.classList.toggle('hidden', !d.count);
                                    });
                                }).catch(() => {});
                        };
                        pusher.subscribe('whatsapp.inbox').bind('message', () => refreshWa());
                    })();
                    @endif

                    // Live Team-Chat badge — every panel user has a personal channel.
                    // The topbar bell owns both its own count and the sidebar "Teams" badge.
                    pusher.subscribe('chat.user.' + window.Razin.userId).bind('message.posted', function (d) {
                        // If that conversation is already open on screen, it's being read — skip the badge.
                        if (window.Razin.openConversation && Number(window.Razin.openConversation) === Number(d.conversation_id)) return;
                        if (typeof window.Razin.onChatPing === 'function') window.Razin.onChatPing(d);
                    });
                } catch (e) { /* Reverb unreachable — ignore */ }
            })();
        </script>
    @endif

    {{-- Team message notification sound (custom MP3), primed on first user interaction. --}}
    @if (auth()->check() && auth()->user()->isPanelUser())
        <script>
            (function () {
                window.Razin = window.Razin || {};
                if (window.__razinSound) return;   // one Audio instance across Turbo navigations
                window.__razinSound = true;
                const audio = new Audio(@js(asset('sounds/razinsoft-message.mp3')));
                audio.preload = 'auto'; audio.volume = 0.75;
                let primed = false;
                function prime() { if (primed) return; primed = true; const p = audio.play(); if (p) p.then(() => { audio.pause(); audio.currentTime = 0; }).catch(() => {}); }
                window.addEventListener('click', prime, { once: true });
                window.addEventListener('keydown', prime, { once: true });
                window.Razin.playMessageSound = function () { try { audio.currentTime = 0; audio.play().catch(() => {}); } catch (e) {} };
            })();
        </script>
    @endif

    {{-- Presence heartbeat: keep my "online" fresh and refresh everyone's dots. --}}
    @if (auth()->check() && auth()->user()->isPanelUser())
        <script>
            (function () {
                window.Razin = window.Razin || {};
                if (window.__razinHeartbeat) return;   // one heartbeat loop across Turbo navigations
                window.__razinHeartbeat = true;
                const url = @js(route('admin.chat.heartbeat'));
                const offlineUrl = @js(route('admin.chat.offline'));
                const token = document.querySelector('meta[name="csrf-token"]').content;
                let navUntil = 0;   // if the page unloads before this time, it's a navigation (don't beacon offline)

                function beat() {
                    fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(d => {
                            const online = new Set((d.online || []).map(Number));
                            window.Razin = window.Razin || {};
                            window.Razin.online = online;
                            document.querySelectorAll('[data-online]').forEach(function (el) {
                                el.classList.toggle('hidden', !online.has(Number(el.dataset.online)));
                            });
                            if (typeof window.Razin.onPresence === 'function') window.Razin.onPresence(online);
                        }).catch(() => {});
                }

                // A click on a link / button / form submit means a same-tab navigation is about to
                // happen — mark a short grace window so pagehide during it doesn't flag us offline.
                function markNav() { navUntil = Date.now() + 1500; }
                document.addEventListener('click', function (e) {
                    const a = e.target.closest('a[href], button');
                    if (a && (a.tagName !== 'A' || !a.target)) markNav();
                });
                document.addEventListener('submit', markNav);

                // Tab/window actually closing (no recent navigation) → mark offline instantly.
                window.addEventListener('pagehide', function () {
                    if (Date.now() < navUntil) return;   // this is a navigation, not a real leave
                    try { const fd = new FormData(); fd.append('_token', token); navigator.sendBeacon(offlineUrl, fd); } catch (e) {}
                });

                beat();
                setInterval(beat, 15000);
                // Coming back to a hidden-then-visible tab should re-announce presence right away.
                document.addEventListener('visibilitychange', function () { if (!document.hidden) beat(); });
            })();
        </script>
    @endif
</body>
</html>
