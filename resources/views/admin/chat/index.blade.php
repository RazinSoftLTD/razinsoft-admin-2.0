@extends('admin.layouts.app')
@section('title', 'Messenger')

@php
    $me = auth()->user();

    $groups = $conversations->where('type', 'group');
    $directByUser = [];
    foreach ($conversations->where('type', 'direct') as $c) {
        $other = $c->counterpart($me);
        if ($other) $directByUser[$other->id] = $c;
    }

    // WhatsApp-style ordering: teammates you've messaged float to the top by most-recent
    // message; people you haven't chatted with yet fall to the bottom (alphabetical).
    $people = $people->sort(function ($a, $b) use ($directByUser) {
        $ta = optional(optional($directByUser[$a->id] ?? null)->latestMessage)->created_at;
        $tb = optional(optional($directByUser[$b->id] ?? null)->latestMessage)->created_at;
        if (! $ta && ! $tb) return strcasecmp($a->name, $b->name);
        if (! $ta) return 1;
        if (! $tb) return -1;
        return $tb->timestamp <=> $ta->timestamp;
    })->values();

    // Short last-activity label like WhatsApp (time today · "Yesterday" · date).
    $chatTime = function ($t) {
        if (! $t) return '';
        return $t->isToday() ? $t->format('g:i A') : ($t->isYesterday() ? 'Yesterday' : $t->format('d/m/y'));
    };

    $avatar = function ($u, $size = 'h-9 w-9') {
        if ($u && $u->photo_url) {
            return '<img src="'.e($u->photo_url).'" class="'.$size.' rounded-full object-cover" alt="">';
        }
        $initial = strtoupper(substr($u->name ?? '?', 0, 1));
        return '<span class="'.$size.' grid place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]">'.$initial.'</span>';
    };
@endphp

@push('head')
    <style>
        .chat-html a { text-decoration: underline; }
        .chat-html p { margin: 0; }
        .chat-html blockquote { border-left: 3px solid rgba(0,0,0,.15); padding-left: .6rem; margin: .25rem 0; opacity: .85; }
        .chat-html s, .chat-html strike { text-decoration: line-through; }
        /* Rich composer */
        .chat-composer:empty:before { content: attr(data-placeholder); color: #9ca3af; pointer-events: none; }
        .chat-composer:focus, .chat-composer:focus-visible { outline: none; box-shadow: none; }
        .chat-composer ul, .chat-composer ol { margin: .25rem 0; padding-left: 1.25rem; }
        .chat-composer ul { list-style: disc; }
        .chat-composer ol { list-style: decimal; }
        .chat-composer blockquote { border-left: 3px solid #e5e7eb; padding-left: .6rem; color: #6b7280; }
        .chat-composer a { color: var(--color-primary); text-decoration: underline; }
        .chat-composer code, .chat-html code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85em; background: rgba(0,0,0,.06); padding: .1em .35em; border-radius: .3rem; }
        .chat-html.break-words code, [data-mine="1"] .chat-html code { background: rgba(255,255,255,.2); }
        .chat-html ul, .chat-html ol { margin: .25rem 0; padding-left: 1.25rem; }
        .chat-html ul { list-style: disc; }
        .chat-html ol { list-style: decimal; }
        [data-conv-link] { transition: background-color .12s ease; }
        [data-conv-link]:hover { background: #f9fafb; }
        [data-conv-link].active-conv, [data-conv-link].active-conv:hover { background: var(--color-primary-soft); }
        [data-conv-link].active-conv .conv-name { color: var(--color-primary); }
        /* Sidebar tabs */
        .chat-tab-underline { opacity: 0; transition: opacity .15s ease; }
        .chat-tab.is-active { color: var(--color-primary); }
        .chat-tab.is-active .chat-tab-underline { opacity: 1; }
        /* Pin button: show on row hover, keep visible + accented when pinned */
        .pin-btn { display: none; }
        .chat-row:hover .pin-btn { display: block; }
        .chat-row.is-pinned .pin-btn { display: block; color: var(--color-primary); }
        /* Toggled header/sidebar icon-buttons (filter, mute) — avoids uncompiled arbitrary classes */
        .chat-btn-on { border-color: var(--color-primary) !important; color: var(--color-primary) !important; background: var(--color-primary-soft) !important; }
        @keyframes chatFadeIn { from { opacity: 0; } to { opacity: 1; } }
        #thread-root { animation: chatFadeIn .18s ease; }
        /* Full-bleed like WhatsApp — break out of the page padding, fill below the top bar. */
        .chat-shell { margin: -1rem; height: calc(100dvh - 4rem); border-radius: 0; border-left: 0; border-right: 0; }
        @media (min-width: 640px) { .chat-shell { margin: -1.5rem; } }
        /* Emoji reaction chip under a bubble. */
        .rx-chip { display: inline-flex; align-items: center; gap: .15rem; border-radius: 9999px; background: #fff; border: 1px solid #e5e7eb; padding: 0 .4rem; height: 1.25rem; font-size: .72rem; line-height: 1; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
        .rx-chip.mine { background: var(--color-primary-soft); border-color: var(--color-primary); }
        /* Hover action bar beside a message. */
        .msg-actions { opacity: 0; transition: opacity .12s ease; }
        .group:hover .msg-actions { opacity: 1; }
        /* Conversation bumped to the top of the list. */
        @keyframes convBump { 0% { background: var(--color-primary-soft); } 100% { background: transparent; } }
        [data-conv-link].conv-bump { animation: convBump .8s ease; }
        /* New message sliding in at the bottom. */
        @keyframes msgIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        .msg-in { animation: msgIn .2s ease; }
    </style>
@endpush

@section('content')
    <div class="chat-shell flex overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">

        {{-- ───────── Left rail ───────── --}}
        @php
            // A small, deterministic badge colour per role label (safe inline HSL — no Tailwind dependency).
            $roleBadge = function ($label) {
                if (! $label) return '';
                $h = crc32($label) % 360;
                return '<span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold" style="background:hsl('.$h.' 85% 95%);color:hsl('.$h.' 55% 42%)">'.e($label).'</span>';
            };
            $pinBtn = '<button type="button" data-pin-toggle title="Pin conversation" class="pin-btn absolute right-1.5 top-1.5 hidden rounded p-1 text-gray-300 hover:bg-white hover:text-[var(--color-primary)]"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4h6l-1 6 4 3H6l4-3-1-6Z"/><path stroke-linecap="round" d="M12 13v7"/></svg></button>';
        @endphp
        <aside id="chat-aside" class="flex w-72 shrink-0 flex-col border-r border-gray-100">
            {{-- Search --}}
            <div class="px-3 pt-4">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
                    <input type="text" data-chat-search placeholder="Search people or messages…"
                           class="h-9 w-full rounded-lg border border-gray-200 pl-9 pr-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                </div>
            </div>

            {{-- Tabs (JS-filtered) --}}
            <div class="flex items-center gap-4 border-b border-gray-100 px-4 pt-3" data-chat-tabs>
                @php
                    $tabs = ['team' => 'Team', 'unread' => 'Unread', 'groups' => 'Groups'];
                    if ($canClients) $tabs['clients'] = 'Clients';
                    $tabs['all'] = 'All';
                @endphp
                @foreach ($tabs as $key => $label)
                    <button type="button" data-tab="{{ $key }}"
                            class="chat-tab relative pb-2 text-sm font-medium text-gray-500 transition hover:text-[var(--color-heading)] {{ $key === 'all' ? 'is-active' : '' }}">
                        {{ $label }}
                        <span class="chat-tab-underline absolute inset-x-0 rounded-full bg-[var(--color-primary)]" style="bottom:-1px;height:2px"></span>
                    </button>
                @endforeach
            </div>

            {{-- Conversation list --}}
            <div class="min-h-0 flex-1 overflow-y-auto px-2 py-3" data-chat-list>
                {{-- Pinned (JS moves matching rows here from localStorage) --}}
                <div data-pinned-section class="mb-2 hidden">
                    <p class="flex items-center gap-1.5 px-2 pb-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 4h6l-1 6 4 3H6l4-3-1-6Z"/><rect x="11" y="13" width="2" height="7" rx="1"/></svg>
                        Pinned
                    </p>
                    <div data-pinned-items></div>
                </div>

                {{-- Team Chats (channels/groups) --}}
                <p id="ch-header" data-group-head="group" class="px-2 pb-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">Team Chats</p>
                @forelse ($groups as $g)
                    @php $un = $g->unreadCountFor($me); $on = $active && $active->id === $g->id; $glast = $g->latestMessage; @endphp
                    <a href="{{ route('admin.chat.show', $g) }}" data-turbo="false" data-conv-link data-conv="{{ $g->id }}"
                       data-pin-key="conv:{{ $g->id }}" data-kind="group" data-unread-count="{{ $un }}" data-chat-row="{{ strtolower($g->name) }}"
                       class="chat-row group relative flex items-center gap-2.5 rounded-lg px-2 py-2 {{ $on ? 'active-conv' : '' }}">
                        <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-lg bg-gray-100 text-gray-500">
                            @if ($g->photo_url)
                                <img src="{{ $g->photo_url }}" class="h-full w-full object-cover" alt="">
                            @else
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 9h12M6 15h12M9 4 7 20M17 4l-2 16"/></svg>
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-1.5">
                                    <span class="conv-name truncate text-sm font-semibold text-[var(--color-heading)]">{{ $g->name }}</span>
                                    <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold" style="background:hsl(220 85% 95%);color:hsl(220 55% 42%)">Team</span>
                                </span>
                                <span data-row-time class="shrink-0 text-[10px] {{ $un ? 'font-semibold text-[var(--color-primary)]' : 'text-gray-400' }}">{{ $glast ? $chatTime($glast->created_at) : '' }}</span>
                            </span>
                            <span class="mt-0.5 flex items-center justify-between gap-2">
                                <span data-row-preview class="truncate text-xs {{ $un ? 'font-medium text-[var(--color-heading)]' : 'text-[var(--color-muted)]' }}">{{ $glast?->preview ?: 'No messages yet' }}</span>
                                @if ($un)<span data-unread class="grid h-[18px] min-w-[18px] shrink-0 place-items-center rounded-full bg-[var(--color-primary)] px-1.5 text-[11px] font-bold text-white">{{ $un }}</span>@endif
                            </span>
                        </span>
                        {!! $pinBtn !!}
                    </a>
                @empty
                    <p data-empty="group" class="px-2 py-1.5 text-xs text-[var(--color-muted)]">No channels yet.</p>
                @endforelse

                {{-- Direct Messages --}}
                <p id="dm-header" data-group-head="direct" class="mt-4 px-2 pb-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">Direct Messages</p>
                @forelse ($people as $p)
                    @php
                        $c = $directByUser[$p->id] ?? null;
                        $un = $c ? $c->unreadCountFor($me) : 0;
                        $on = $active && $c && $active->id === $c->id;
                        $last = $c?->latestMessage;
                        $preview = $last?->preview;
                        $role = $p->designation->name ?? null;
                    @endphp
                    <a href="{{ route('admin.chat.direct', $p) }}" data-turbo="false" data-conv-link data-user="{{ $p->id }}"
                       data-pin-key="user:{{ $p->id }}" data-kind="direct" data-unread-count="{{ $un }}" data-chat-row="{{ strtolower($p->name.' '.$role) }}"
                       class="chat-row group relative flex items-center gap-2.5 rounded-lg px-2 py-2 {{ $on ? 'active-conv' : '' }}">
                        <span class="relative shrink-0">
                            {!! $avatar($p) !!}
                            <span data-online="{{ $p->id }}" class="{{ $p->isOnline() ? '' : 'hidden' }} absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full bg-green-500 ring-2 ring-white"></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-1.5">
                                    <span class="conv-name truncate text-sm font-semibold text-[var(--color-heading)]">{{ $p->name }}</span>
                                    {!! $roleBadge($role) !!}
                                </span>
                                <span data-row-time class="shrink-0 text-[10px] {{ $un ? 'font-semibold text-[var(--color-primary)]' : 'text-gray-400' }}">{{ $last ? $chatTime($last->created_at) : '' }}</span>
                            </span>
                            <span class="mt-0.5 flex items-center justify-between gap-2">
                                <span data-row-preview class="truncate text-xs {{ $un ? 'font-medium text-[var(--color-heading)]' : 'text-[var(--color-muted)]' }}">{{ $preview !== null && $preview !== '' ? $preview : ($role ?? 'Team member') }}</span>
                                @if ($un)<span data-unread class="grid h-[18px] min-w-[18px] shrink-0 place-items-center rounded-full bg-[var(--color-primary)] px-1.5 text-[11px] font-bold text-white">{{ $un }}</span>@endif
                            </span>
                        </span>
                        {!! $pinBtn !!}
                    </a>
                @empty
                    <p data-empty="direct" class="px-2 py-1.5 text-xs text-[var(--color-muted)]">No teammates yet.</p>
                @endforelse

                {{-- Client Messages --}}
                @if ($canClients)
                    <p id="cl-header" data-group-head="client" class="mt-4 px-2 pb-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">Client Messages</p>
                    @forelse ($clientConversations as $c)
                        @php
                            $client = $c->clientMember() ?? $c->members->first();
                            $un = $c->unreadCountFor($me);
                            $on = $active && $active->id === $c->id;
                            $clast = $c->latestMessage;
                        @endphp
                        <a href="{{ route('admin.chat.show', $c) }}" data-turbo="false" data-conv-link data-conv="{{ $c->id }}"
                           data-pin-key="conv:{{ $c->id }}" data-kind="client" data-unread-count="{{ $un }}" data-chat-row="{{ strtolower($client->name ?? 'client') }}"
                           class="chat-row group relative flex items-center gap-2.5 rounded-lg px-2 py-2 {{ $on ? 'active-conv' : '' }}">
                            <span class="relative shrink-0">
                                {!! $avatar($client) !!}
                                <span class="absolute -bottom-0.5 -right-0.5 grid h-3.5 w-3.5 place-items-center rounded-full bg-sky-500 ring-2 ring-white" title="Client"><svg class="h-2 w-2 text-white" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0Z"/></svg></span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="flex min-w-0 items-center gap-1.5">
                                        <span class="conv-name truncate text-sm font-semibold text-[var(--color-heading)]">{{ $client->name ?? 'Client' }}</span>
                                        <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold" style="background:hsl(200 85% 94%);color:hsl(200 60% 40%)">Client</span>
                                    </span>
                                    <span data-row-time class="shrink-0 text-[10px] {{ $un ? 'font-semibold text-[var(--color-primary)]' : 'text-gray-400' }}">{{ $clast ? $chatTime($clast->created_at) : '' }}</span>
                                </span>
                                <span class="mt-0.5 flex items-center justify-between gap-2">
                                    <span data-row-preview class="truncate text-xs {{ $un ? 'font-medium text-[var(--color-heading)]' : 'text-[var(--color-muted)]' }}">{{ $clast?->preview ?: 'New conversation' }}</span>
                                    @if ($un)<span data-unread class="grid h-[18px] min-w-[18px] shrink-0 place-items-center rounded-full bg-[var(--color-primary)] px-1.5 text-[11px] font-bold text-white">{{ $un }}</span>@endif
                                </span>
                            </span>
                            {!! $pinBtn !!}
                        </a>
                    @empty
                        <div data-empty="client" class="px-2 py-10 text-center text-xs text-[var(--color-muted)]">
                            <svg class="mx-auto mb-2 h-8 w-8 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v10Z"/></svg>
                            No client messages yet.
                        </div>
                    @endforelse
                @endif

                <p data-list-empty class="hidden px-2 py-10 text-center text-xs text-[var(--color-muted)]">Nothing here.</p>
            </div>

            {{-- Footer --}}
            <a href="{{ route('admin.chat.index') }}" data-turbo="false"
               class="flex shrink-0 items-center justify-between border-t border-gray-100 px-4 py-3 text-sm font-semibold text-[var(--color-heading)] transition hover:bg-gray-50">
                View all conversations
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
            </a>
        </aside>

        {{-- ───────── Right: thread (Turbo Frame — only this swaps on conversation switch) ───────── --}}
        @include('admin.chat._pane')
    </div>

    {{-- Forward-to-teammate modal (persistent shell — survives thread swaps) --}}
    <div id="fwd-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-xl" @click.stop>
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <p class="text-sm font-bold text-[var(--color-heading)]">Forward to</p>
                <button type="button" id="fwd-close" class="grid h-7 w-7 place-items-center rounded-lg text-gray-400 hover:bg-gray-50">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="p-3">
                <input id="fwd-search" type="text" placeholder="Search teammates…" class="mb-2 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                <div id="fwd-list" class="max-h-72 space-y-0.5 overflow-y-auto"></div>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div id="chat-toast" class="pointer-events-none fixed bottom-6 left-1/2 z-[60] hidden -translate-x-1/2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-lg"></div>

    @php
        $chatTeam = $people->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->photo_url, 'initial' => strtoupper(substr($u->name, 0, 1))])->values();
    @endphp
    <script>
        window.Razin = window.Razin || {};
        window.__chatTeam = @json($chatTeam);
    </script>

    <script>
    // Thread initialiser — called deterministically on every frame load (Turbo) and on first render.
    window.__initChatThread = function (root) {
        if (!root) return;
        // Guard against a double-init on the same frame content.
        const editorEl = document.getElementById('chat-input');
        if (!editorEl || editorEl.dataset.ready === '1') return;
        editorEl.dataset.ready = '1';
        window.Razin = window.Razin || {};

        const ME = Number(root.dataset.me);
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;
        const CONV = Number(root.dataset.convId);
        const isGroup = root.dataset.isGroup === '1';
        const CONV_TYPE = root.dataset.convType || 'direct';
        const IS_ADMIN = root.dataset.isAdmin === '1';
        const STORE_URL = root.dataset.storeUrl;
        const TYPING_URL = root.dataset.typingUrl;
        const READ_URL = root.dataset.readUrl;
        const DEL_BASE = root.dataset.delBase;

        const scroll = document.getElementById('chat-scroll');
        const form = document.getElementById('chat-form');
        const fileInput = document.getElementById('chat-file');
        const fileChip = document.getElementById('chat-file-chip');
        const fileName = document.getElementById('chat-file-name');
        const seen = new Set();
        document.querySelectorAll('[data-msg-id]').forEach(el => seen.add(Number(el.dataset.msgId)));

        window.Razin.openConversation = CONV;
        if (typeof window.Razin.markConversationRead === 'function') window.Razin.markConversationRead(CONV);

        // Highlight the matching sidebar row (clicks + back/forward).
        document.querySelectorAll('[data-conv-link].active-conv').forEach(el => el.classList.remove('active-conv'));
        const cp = root.dataset.counterpartId;
        const activeRow = document.querySelector('[data-conv="' + CONV + '"]') || (cp ? document.querySelector('[data-user="' + cp + '"]') : null);
        if (activeRow) { activeRow.classList.add('active-conv'); activeRow.dataset.unreadCount = '0'; const b = activeRow.querySelector('[data-unread]'); if (b) b.remove(); if (window.Razin.refreshChatFilter) window.Razin.refreshChatFilter(); }

        const esc = s => (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

        // ── Reactions & reply (WhatsApp-style) ──
        const REACT_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
        const REACT_BASE = DEL_BASE;   // /admin/chat/messages/{id}/react
        const CHECKLIST_BASE = DEL_BASE;  // /admin/chat/messages/{id}/checklist

        // Render the reaction chips under a bubble from a {userId: emoji} map.
        function renderReactions(row, map) {
            const box = row.querySelector('[data-reactions-box]');
            if (!box) return;
            map = map || {};
            const counts = {};              // emoji → count
            let mine = null;
            Object.keys(map).forEach(uid => {
                const em = map[uid];
                counts[em] = (counts[em] || 0) + 1;
                if (Number(uid) === ME) mine = em;
            });
            row.dataset.reactions = JSON.stringify(map);
            box.innerHTML = Object.keys(counts).map(em =>
                '<button type="button" class="rx-chip ' + (mine === em ? 'mine' : '') + '" data-rx="' + em + '"><span>' + em + '</span>' + (counts[em] > 1 ? '<span>' + counts[em] + '</span>' : '') + '</button>'
            ).join('');
        }
        function readReactions(row) { try { return JSON.parse(row.dataset.reactions || '{}'); } catch (e) { return {}; } }
        function sendReaction(id, emoji) {
            fetch(REACT_BASE + '/' + id + '/react', {
                method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ emoji }),
            }).then(r => r.json()).then(d => {
                if (d && d.id) { const row = scroll.querySelector('[data-msg-id="' + d.id + '"]'); if (row) renderReactions(row, d.reactions || {}); }
            }).catch(() => {});
        }

        // Move a conversation's left-list row to the top of its section and refresh its
        // preview/time/unread — the live WhatsApp-style reorder (no reload needed).
        window.Razin.bumpConversation = function (o) {
            const aside = document.getElementById('chat-aside');
            if (!aside) return;
            let row, headerId;
            if (o.type === 'direct') { row = aside.querySelector('a[data-user="' + o.userId + '"]'); headerId = 'dm-header'; }
            else { row = aside.querySelector('a[data-conv="' + o.convId + '"]'); headerId = o.type === 'client' ? 'cl-header' : 'ch-header'; }
            if (!row) return;
            const pv = row.querySelector('[data-row-preview]');
            const tm = row.querySelector('[data-row-time]');
            if (pv && o.preview != null && o.preview !== '') pv.textContent = o.preview;
            if (tm && o.time) tm.textContent = o.time;
            if (o.incUnread) {
                let b = row.querySelector('[data-unread]');
                if (!b) {
                    b = document.createElement('span');
                    b.setAttribute('data-unread', '');
                    b.className = 'grid h-[18px] min-w-[18px] shrink-0 place-items-center rounded-full bg-red-500 px-1.5 text-[11px] font-bold text-white';
                    b.textContent = '0';
                    (pv ? pv.parentElement : row).appendChild(b);
                }
                b.textContent = String((parseInt(b.textContent, 10) || 0) + 1);
                row.dataset.unreadCount = b.textContent;
                if (pv) { pv.classList.add('font-medium', 'text-[var(--color-heading)]'); pv.classList.remove('text-[var(--color-muted)]'); }
                if (tm) { tm.classList.add('font-semibold', 'text-[var(--color-primary)]'); tm.classList.remove('text-gray-400'); }
            }
            if (window.Razin.refreshChatFilter) window.Razin.refreshChatFilter();
            const header = document.getElementById(headerId);
            if (header && header.parentElement === row.parentElement) header.after(row);   // → top of its section
            row.classList.remove('conv-bump'); void row.offsetWidth; row.classList.add('conv-bump');
            setTimeout(() => row.classList.remove('conv-bump'), 800);
        };

        // Quoted-reply preview markup shown at the top of a bubble.
        function quotedHtml(q, mine) {
            if (!q) return '';
            const accent = mine ? 'border-white/70 bg-white/15' : 'border-[var(--color-primary)] bg-gray-100';
            const nameCol = mine ? 'text-white' : 'text-[var(--color-primary)]';
            const txtCol = mine ? 'text-white/80' : 'text-gray-500';
            const snippet = q.is_image ? '📷 Photo' : (q.preview || '');
            return '<div class="mb-1 rounded-md border-l-4 px-2 py-1 text-xs ' + accent + '">'
                + '<span class="block font-semibold ' + nameCol + '">' + esc(q.author || '') + '</span>'
                + '<span class="block truncate ' + txtCol + '">' + esc(snippet) + '</span></div>';
        }
        const playSound = () => { if (window.Razin.isConvMuted && window.Razin.isConvMuted()) return; if (typeof window.Razin.playMessageSound === 'function') window.Razin.playMessageSound(); };
        const toBottom = () => { scroll.scrollTop = scroll.scrollHeight; };
        toBottom();

        let lastReadPing = 0;
        function markReadPing() {
            const now = Date.now(); if (now - lastReadPing < 1200) return; lastReadPing = now;
            fetch(READ_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } }).catch(() => {});
        }

        // ── Header controls: Files · Search-in-chat · Mute · More ──
        (function initHeaderControls() {
            // Mute (per-conversation, localStorage). playSound() respects window.Razin.isConvMuted.
            const bell = document.getElementById('chat-mute-btn');
            const bellOn = bell && bell.querySelector('[data-bell-on]');
            const bellOff = bell && bell.querySelector('[data-bell-off]');
            const MUTE_KEY = 'razin_chat_mute_' + CONV;
            const isMuted = () => { try { return localStorage.getItem(MUTE_KEY) === '1'; } catch (e) { return false; } };
            const paintMute = () => {
                const m = isMuted();
                if (bellOn) bellOn.classList.toggle('hidden', m);
                if (bellOff) bellOff.classList.toggle('hidden', !m);
                if (bell && window.Razin.paintToggleBtn) window.Razin.paintToggleBtn(bell, m);
            };
            window.Razin.isConvMuted = isMuted;
            paintMute();
            bell && bell.addEventListener('click', function () {
                try { localStorage.setItem(MUTE_KEY, isMuted() ? '0' : '1'); } catch (e) {}
                paintMute();
                window.Razin.toast(isMuted() ? 'Notifications muted' : 'Notifications on');
            });

            // Shared-files dropdown — built from the loaded messages.
            const filesBtn = document.getElementById('chat-files-btn');
            const filesPanel = document.getElementById('chat-files-panel');
            const filesList = document.getElementById('chat-files-list');
            const fileIcon = '<span class="grid h-9 w-9 shrink-0 place-items-center rounded bg-gray-100 text-gray-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7zM14 3v5h5"/></svg></span>';
            function buildFiles() {
                const links = [...scroll.querySelectorAll('[data-bubble-wrap] a[href]')]
                    .filter(a => a.querySelector('img') || /\.(pdf|docx?|xlsx?|pptx?|zip|rar|csv|txt|png|jpe?g|gif|webp|svg)(\?|$)/i.test(a.getAttribute('href') || ''));
                if (!links.length) { filesList.innerHTML = '<p class="px-2 py-3 text-center text-xs text-gray-400">No files shared yet.</p>'; return; }
                filesList.innerHTML = links.slice().reverse().map(a => {
                    const img = a.querySelector('img');
                    const name = img ? 'Photo' : (a.textContent.trim() || 'File');
                    const thumb = img ? '<img src="' + img.src + '" class="h-9 w-9 shrink-0 rounded object-cover">' : fileIcon;
                    return '<a href="' + a.href + '" target="_blank" rel="noopener" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50">' + thumb + '<span class="truncate text-xs font-medium text-[var(--color-heading)]">' + esc(name) + '</span></a>';
                }).join('');
            }
            function toggleFiles(force) {
                if (!filesPanel) return;
                const willShow = force === true ? true : filesPanel.classList.contains('hidden');
                if (willShow) buildFiles();
                filesPanel.classList.toggle('hidden', !willShow);
            }
            filesBtn && filesBtn.addEventListener('click', function (e) { e.stopPropagation(); toggleFiles(); });

            // Search within this conversation (filters loaded messages).
            const inSearchBtn = document.getElementById('chat-insearch-btn');
            const searchBar = document.getElementById('chat-search-bar');
            const searchInput = document.getElementById('chat-search-input');
            const searchCount = document.getElementById('chat-search-count');
            const searchClose = document.getElementById('chat-search-close');
            function runInSearch() {
                const q = (searchInput.value || '').trim().toLowerCase();
                let n = 0;
                scroll.querySelectorAll('[data-msg-id]').forEach(row => {
                    if (!q) { row.style.display = ''; return; }
                    const hit = (row.innerText || '').toLowerCase().includes(q);
                    row.style.display = hit ? '' : 'none';
                    if (hit) n++;
                });
                searchCount.textContent = q ? (n + ' found') : '';
            }
            function openInSearch() { searchBar.classList.remove('hidden'); searchBar.classList.add('flex'); searchInput.focus(); }
            function closeInSearch() { searchBar.classList.add('hidden'); searchBar.classList.remove('flex'); searchInput.value = ''; runInSearch(); }
            inSearchBtn && inSearchBtn.addEventListener('click', openInSearch);
            searchInput && searchInput.addEventListener('input', runInSearch);
            searchClose && searchClose.addEventListener('click', closeInSearch);

            // More menu.
            const moreBtn = document.getElementById('chat-more-btn');
            const moreMenu = document.getElementById('chat-more-menu');
            moreBtn && moreBtn.addEventListener('click', function (e) { e.stopPropagation(); moreMenu.classList.toggle('hidden'); });
            moreMenu && moreMenu.addEventListener('click', function (e) {
                const b = e.target.closest('[data-more]'); if (!b) return;
                moreMenu.classList.add('hidden');
                if (b.dataset.more === 'mark-read') { fetch(READ_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } }).then(() => window.Razin.toast('Marked as read')).catch(() => {}); }
                else if (b.dataset.more === 'files') toggleFiles(true);
                else if (b.dataset.more === 'search') openInSearch();
            });

            document.addEventListener('click', function (e) {
                if (filesPanel && !filesPanel.classList.contains('hidden') && !filesPanel.contains(e.target) && !(filesBtn && filesBtn.contains(e.target))) filesPanel.classList.add('hidden');
                if (moreMenu && !moreMenu.classList.contains('hidden') && !moreMenu.contains(e.target) && !(moreBtn && moreBtn.contains(e.target))) moreMenu.classList.add('hidden');
            });
        })();

        // ── Reply state (composer banner) ──
        let replyToId = null;
        const replyBanner = document.getElementById('chat-reply-banner');
        const replyAuthorEl = document.getElementById('chat-reply-author');
        const replyTextEl = document.getElementById('chat-reply-text');
        function startReply(row) {
            replyToId = Number(row.dataset.msgId);
            replyAuthorEl.textContent = row.dataset.mine === '1' ? 'You' : (row.dataset.author || 'Reply');
            const bodyEl = row.querySelector('.chat-html');
            const img = row.querySelector('[data-bubble-wrap] img');
            replyTextEl.textContent = bodyEl && bodyEl.innerText.trim() ? bodyEl.innerText.trim() : (img ? '📷 Photo' : 'Attachment');
            replyBanner.classList.remove('hidden'); replyBanner.classList.add('flex');
            input.focus();
        }
        function cancelReply() { replyToId = null; replyBanner.classList.add('hidden'); replyBanner.classList.remove('flex'); }
        document.getElementById('chat-reply-cancel').addEventListener('click', cancelReply);

        // ── Emoji picker popover (for the React action) ──
        let emojiPop = null;
        function closeEmoji() { if (emojiPop) { emojiPop.remove(); emojiPop = null; } }
        function openEmoji(anchor, id) {
            closeEmoji();
            const pop = document.createElement('div');
            pop.className = 'fixed z-50 flex gap-1 rounded-full border border-gray-100 bg-white px-2 py-1 shadow-lg';
            pop.innerHTML = REACT_EMOJIS.map(e => '<button type="button" class="grid h-8 w-8 place-items-center rounded-full text-lg hover:bg-gray-100" data-emoji="' + e + '">' + e + '</button>').join('');
            document.body.appendChild(pop);
            const r = anchor.getBoundingClientRect();
            pop.style.top = Math.max(8, r.top - 46) + 'px';
            pop.style.left = Math.min(window.innerWidth - pop.offsetWidth - 8, r.left - 40) + 'px';
            emojiPop = pop;
            pop.addEventListener('click', function (e) {
                const b = e.target.closest('[data-emoji]');
                if (!b) return;
                sendReaction(id, b.dataset.emoji);
                closeEmoji();
            });
        }
        document.addEventListener('click', function (e) { if (emojiPop && !emojiPop.contains(e.target) && !e.target.closest('[data-act="react"]')) closeEmoji(); });

        // Rich composer (contenteditable + formatting toolbar). Enter to send, Shift+Enter = newline.
        const input = document.getElementById('chat-input');
        const autoGrow = () => {};                                   // contenteditable grows on its own
        const textToHtml = (t) => esc(t).replace(/\n/g, '<br>');    // still used for the edit PATCH path
        const htmlToText = (h) => { const d = document.createElement('div'); d.innerHTML = (h || '').replace(/<br\s*\/?>/gi, '\n').replace(/<\/(p|div|li)>/gi, '\n'); return (d.textContent || '').replace(/\n{3,}/g, '\n\n').trim(); };
        // Read/write the editor uniformly (replaces the old textarea .value calls).
        const getHtml = () => { const h = input.innerHTML.trim(); return (h === '<br>' || h === '<div><br></div>') ? '' : h; };
        const getText = () => (input.textContent || '').trim();
        const setHtml = (h) => { input.innerHTML = h || ''; };
        const clearInput = () => { input.innerHTML = ''; };
        // Formatting buttons live in the rich panel; delegate from the panel itself.
        const toolbar = document.getElementById('chat-format-panel');
        if (toolbar) toolbar.addEventListener('mousedown', function (e) {
            const btn = e.target.closest('[data-fmt]'); if (!btn) return;
            e.preventDefault();                                       // keep the caret in the editor
            input.focus();
            const cmd = btn.dataset.fmt;
            if (cmd === 'createLink') {
                let url = prompt('Link URL (https://…)');
                if (url) {
                    url = url.trim();
                    if (!/^(https?:\/\/|mailto:)/i.test(url)) url = 'https://' + url;
                    const sel = window.getSelection();
                    if (sel && sel.toString()) {
                        document.execCommand('createLink', false, url);
                    } else {
                        // No text selected → drop the URL in as a clickable link.
                        document.execCommand('insertHTML', false, '<a href="' + url.replace(/"/g, '&quot;') + '">' + url.replace(/</g, '&lt;') + '</a>&nbsp;');
                    }
                }
            } else if (cmd === 'blockquote') {
                document.execCommand('formatBlock', false, 'blockquote');
            } else if (cmd === 'code') {
                const sel = window.getSelection();
                const picked = sel ? sel.toString() : '';
                const safe = (picked || 'code').replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
                document.execCommand('insertHTML', false, '<code>' + safe + '</code>&nbsp;');
            } else {
                document.execCommand(cmd, false, null);
            }
        });
        // ── Checklist builder ──
        let checklistItems = [];
        const clWrap = document.getElementById('chat-checklist');
        const clItemsEl = document.getElementById('chat-checklist-items');
        const clInput = document.getElementById('chat-checklist-input');
        const renderChecklistBuilder = () => {
            clItemsEl.innerHTML = checklistItems.map((t, i) =>
                '<div class="flex items-center gap-2 text-sm text-gray-700"><span class="grid h-4 w-4 place-items-center rounded border border-gray-300"></span>' +
                '<span class="flex-1">' + esc(t) + '</span>' +
                '<button type="button" data-cl-rm="' + i + '" class="text-gray-300 hover:text-red-500">&times;</button></div>').join('');
            clWrap.classList.toggle('hidden', checklistItems.length === 0 && document.activeElement !== clInput);
        };
        const addChecklistItem = () => {
            const v = clInput.value.trim();
            if (v) { checklistItems.push(v.slice(0, 500)); clInput.value = ''; renderChecklistBuilder(); }
            clInput.focus();
        };
        document.getElementById('chat-checklist-btn')?.addEventListener('click', function () {
            clWrap.classList.remove('hidden'); clInput.focus();
        });
        document.getElementById('chat-checklist-add')?.addEventListener('click', addChecklistItem);
        clInput?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); addChecklistItem(); }
        });
        document.getElementById('chat-checklist-title')?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); clInput?.focus(); }   // Enter on the title jumps to items, never sends
        });
        clItemsEl?.addEventListener('click', function (e) {
            const b = e.target.closest('[data-cl-rm]'); if (!b) return;
            checklistItems.splice(Number(b.dataset.clRm), 1); renderChecklistBuilder();
        });
        const clTitleEl = document.getElementById('chat-checklist-title');
        const clearChecklist = () => { checklistItems = []; clItemsEl.innerHTML = ''; if (clTitleEl) clTitleEl.value = ''; clWrap.classList.add('hidden'); };

        // ── Emoji picker for the composer (insert at the caret) ──
        const COMPOSER_EMOJIS = ['😀','😁','😂','🤣','😊','😍','😎','😉','🙂','🤔','😅','😭','😴','😡','👍','👎','🙏','👏','🙌','💪','🔥','✅','❌','⭐','💯','🎉','❤️','💙','💚','🚀','👀','☕','⏰','📌','📎','💡'];
        let composerEmojiPop = null;
        const closeComposerEmoji = () => { if (composerEmojiPop) { composerEmojiPop.remove(); composerEmojiPop = null; } };
        const emojiBtn = document.getElementById('chat-emoji-btn');
        emojiBtn?.addEventListener('mousedown', function (e) {
            e.preventDefault();                                   // keep the caret in the editor
            if (composerEmojiPop) { closeComposerEmoji(); return; }
            const pop = document.createElement('div');
            pop.className = 'z-50 rounded-xl border border-gray-100 bg-white p-2 shadow-lg';
            pop.style.position = 'fixed';
            pop.style.display = 'grid';
            pop.style.gridTemplateColumns = 'repeat(8, minmax(0, 1fr))';
            pop.style.gap = '2px';
            pop.style.width = '15.5rem';
            pop.innerHTML = COMPOSER_EMOJIS.map(em => '<button type="button" tabindex="-1" data-emoji="' + em + '" class="grid h-7 w-7 place-items-center rounded-lg text-lg hover:bg-gray-100">' + em + '</button>').join('');
            document.body.appendChild(pop);
            const r = emojiBtn.getBoundingClientRect();
            pop.style.top = Math.max(8, r.top - pop.offsetHeight - 8) + 'px';
            pop.style.left = Math.min(window.innerWidth - pop.offsetWidth - 8, r.left) + 'px';
            composerEmojiPop = pop;
            pop.addEventListener('mousedown', function (ev) {
                const b = ev.target.closest('[data-emoji]'); if (!b) return;
                ev.preventDefault();
                input.focus();
                document.execCommand('insertText', false, b.dataset.emoji);
                closeComposerEmoji();
            });
        });
        document.addEventListener('click', function (e) { if (composerEmojiPop && !composerEmojiPop.contains(e.target) && e.target !== emojiBtn && !emojiBtn.contains(e.target) && !e.target.closest('[data-quick="emoji"],[data-insert="emoji"]')) closeComposerEmoji(); });

        // ── @mention picker: insert @Name at the caret ──
        const mentionBtn = document.getElementById('chat-mention-btn');
        let mentionPop = null;
        const closeMention = () => { if (mentionPop) { mentionPop.remove(); mentionPop = null; } };
        function renderMentionList(pop, q) {
            const team = window.__chatTeam || [];
            const needle = (q || '').trim().toLowerCase();
            const box = pop.querySelector('[data-mention-list]');
            box.innerHTML = team.filter(p => !needle || p.name.toLowerCase().includes(needle)).slice(0, 30).map(p => {
                const av = p.avatar
                    ? '<img src="' + p.avatar + '" class="h-6 w-6 rounded-full object-cover">'
                    : '<span class="grid h-6 w-6 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[11px] font-bold text-[var(--color-primary)]">' + esc(p.initial) + '</span>';
                return '<button type="button" data-mention="' + esc(p.name) + '" class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-gray-50">' + av + '<span class="truncate text-sm text-[var(--color-heading)]">' + esc(p.name) + '</span></button>';
            }).join('') || '<p class="px-2 py-3 text-center text-xs text-gray-400">No teammates.</p>';
        }
        mentionBtn?.addEventListener('mousedown', function (e) {
            e.preventDefault();
            if (mentionPop) { closeMention(); return; }
            const pop = document.createElement('div');
            pop.className = 'z-50 max-h-72 w-56 overflow-hidden rounded-xl border border-gray-100 bg-white p-1.5 shadow-lg';
            pop.style.position = 'fixed';
            pop.innerHTML = '<input type="text" data-mention-search placeholder="Mention…" class="mb-1 h-8 w-full rounded-lg border border-gray-200 px-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none"><div data-mention-list class="max-h-56 overflow-y-auto"></div>';
            document.body.appendChild(pop);
            renderMentionList(pop, '');
            const r = mentionBtn.getBoundingClientRect();
            pop.style.top = Math.max(8, r.top - pop.offsetHeight - 8) + 'px';
            pop.style.left = Math.min(window.innerWidth - pop.offsetWidth - 8, r.left) + 'px';
            mentionPop = pop;
            const si = pop.querySelector('[data-mention-search]');
            si.addEventListener('input', () => renderMentionList(pop, si.value));
            si.addEventListener('mousedown', ev => ev.stopPropagation());
            setTimeout(() => si.focus(), 0);
            pop.addEventListener('mousedown', function (ev) {
                const b = ev.target.closest('[data-mention]'); if (!b) return;
                ev.preventDefault();
                input.focus();
                document.execCommand('insertText', false, '@' + b.dataset.mention + ' ');
                closeMention();
            });
        });
        document.addEventListener('click', function (e) { if (mentionPop && !mentionPop.contains(e.target) && e.target !== mentionBtn && !mentionBtn.contains(e.target) && !e.target.closest('[data-insert="mention"]')) closeMention(); });

        // ── Rich FORMAT / INSERT / SHORTCUTS panel + quick-bar proxies ──
        (function initComposerPanel() {
            const panel = document.getElementById('chat-format-panel');
            const plusBtn = document.getElementById('chat-plus');
            const fmtBtn = document.getElementById('chat-format-btn');
            if (!panel) return;
            const closePanel = () => panel.classList.add('hidden');
            const togglePanel = () => panel.classList.toggle('hidden');
            const fire = (id) => { const el = document.getElementById(id); el && el.dispatchEvent(new MouseEvent('mousedown', { bubbles: true })); };
            const insertAtCaret = (t) => { input.focus(); document.execCommand('insertText', false, t); };

            // "+" reveals/hides the quick icon bar (clean & minimal by default).
            const quickbar = document.getElementById('chat-quickbar');
            plusBtn && plusBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (!quickbar) return;
                const show = quickbar.classList.contains('hidden');
                quickbar.classList.toggle('hidden', !show);
                quickbar.classList.toggle('flex', show);
                plusBtn.classList.toggle('bg-[var(--color-primary)]', show);
                plusBtn.classList.toggle('text-white', show);
                plusBtn.classList.toggle('bg-[var(--color-primary-soft)]', !show);
                plusBtn.classList.toggle('text-[var(--color-primary)]', !show);
                if (!show) closePanel();                    // hiding the bar also closes the panel
            });
            // "Aa" opens the full Format / Insert / Shortcuts panel.
            fmtBtn && fmtBtn.addEventListener('mousedown', function (e) { e.preventDefault(); togglePanel(); });

            // Quick-bar: emoji / channel / code proxy to the canonical controls
            // (format, mention, checklist and the file <label> keep their own handlers).
            const quickRow = fmtBtn && fmtBtn.parentElement;
            quickRow && quickRow.addEventListener('mousedown', function (e) {
                const b = e.target.closest('[data-quick]'); if (!b) return;
                const act = b.dataset.quick;
                if (act === 'format' || act === 'mention' || act === 'checklist') return;
                e.preventDefault();
                if (act === 'emoji') fire('chat-emoji-btn');
                else if (act === 'channel') insertAtCaret('#');
                else if (act === 'code') { const cb = panel.querySelector('[data-fmt="code"]'); cb && cb.dispatchEvent(new MouseEvent('mousedown', { bubbles: true })); }
            });

            // INSERT + SHORTCUTS (formatting [data-fmt] buttons are handled by the toolbar listener).
            panel.addEventListener('mousedown', function (e) {
                const ins = e.target.closest('[data-insert]');
                if (ins) {
                    e.preventDefault();
                    const k = ins.dataset.insert;
                    if (k === 'file' || k === 'image') fileInput.click();
                    else if (k === 'emoji') fire('chat-emoji-btn');
                    else if (k === 'mention') fire('chat-mention-btn');
                    else if (k === 'channel') insertAtCaret('#');
                    else if (k === 'flag') insertAtCaret('🚩 ');
                    else if (k === 'task') { const c = document.getElementById('chat-checklist-btn'); c && c.click(); }
                    else if (k === 'template') window.Razin.toast('No templates yet');
                    closePanel();
                    return;
                }
                const sc = e.target.closest('[data-shortcut]');
                if (sc) { e.preventDefault(); insertAtCaret(sc.dataset.shortcut); closePanel(); }
            });

            document.addEventListener('click', function (e) {
                if (panel.classList.contains('hidden')) return;
                if (!panel.contains(e.target) && e.target !== plusBtn && !(plusBtn && plusBtn.contains(e.target)) && e.target !== fmtBtn && !(fmtBtn && fmtBtn.contains(e.target))) closePanel();
            });
        })();

        // Paste as plain text so foreign markup never enters the composer.
        input.addEventListener('paste', function (e) {
            const items = (e.clipboardData && e.clipboardData.items) ? e.clipboardData.items : [];
            for (const it of items) { if (it.kind === 'file' && it.type.indexOf('image/') === 0) return; }
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
        });
        // True when the caret sits inside a list — there Enter should make a new item, not send.
        const caretInList = () => {
            const sel = window.getSelection();
            let n = sel && sel.rangeCount ? sel.getRangeAt(0).startContainer : null;
            while (n && n !== input) { if (n.nodeName === 'LI' || n.nodeName === 'UL' || n.nodeName === 'OL') return true; n = n.parentNode; }
            return false;
        };
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                if (caretInList()) return;              // let the browser add the next list item
                e.preventDefault(); form.requestSubmit();
            }
        });
        // Paste an image straight from the clipboard (screenshot / copied picture) → attach it.
        input.addEventListener('paste', function (e) {
            const items = (e.clipboardData && e.clipboardData.items) ? e.clipboardData.items : [];
            for (const it of items) {
                if (it.kind === 'file' && it.type.indexOf('image/') === 0) {
                    const file = it.getAsFile();
                    if (!file) continue;
                    const ext = (file.type.split('/')[1] || 'png');
                    const named = new File([file], (file.name && file.name !== 'image.png') ? file.name : ('pasted-' + Date.now() + '.' + ext), { type: file.type });
                    const dt = new DataTransfer(); dt.items.add(named); fileInput.files = dt.files;
                    fileInput.dispatchEvent(new Event('change'));
                    return;
                }
            }
        });

        fileInput.addEventListener('change', function () {
            if (this.files.length) { fileName.textContent = this.files[0].name; fileChip.classList.remove('hidden'); fileChip.classList.add('flex'); }
        });
        document.getElementById('chat-file-remove').addEventListener('click', function () {
            fileInput.value = ''; fileChip.classList.add('hidden'); fileChip.classList.remove('flex');
        });

        window.Razin.msgBase = DEL_BASE;   // used by the forward modal (outer scope)
        const WINDOW_SECS = 60 * 60;       // 1-hour edit/delete window
        const toEpoch = (v) => { if (!v) return 0; if (typeof v === 'number') return v > 1e11 ? Math.floor(v / 1000) : v; const t = Date.parse(v); return isNaN(t) ? 0 : Math.floor(t / 1000); };

        // Per-message actions menu (kebab): Copy · Edit · Forward · Delete.
        // Edit/Delete for the author are time-gated: they are re-checked every time the
        // menu opens (see refreshGated), so they disappear once the 1-hour window passes.
        function attachMenu(row) {
            if (row.querySelector('[data-actions]')) { renderReactions(row, readReactions(row)); return; }
            const id = row.dataset.msgId;
            const mine = row.dataset.mine === '1';
            const hasText = !!(row.querySelector('.chat-html') && row.querySelector('.chat-html').innerText.trim());
            const svg = (p) => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">' + p + '</svg>';
            const ICON = {
                reply: svg('<path stroke-linecap="round" stroke-linejoin="round" d="M11 17 6 12l5-5M6 12h8a4 4 0 0 1 4 4v2"/>'),
                react: svg('<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M9 10h.01M15 10h.01M9 15c.8.7 1.9 1 3 1s2.2-.3 3-1"/>'),
                copy: svg('<rect x="9" y="9" width="11" height="11" rx="2"/><path stroke-linecap="round" d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>'),
                edit: svg('<path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>'),
                forward: svg('<path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M20 12H4"/>'),
                delete: svg('<path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7"/>'),
            };
            // Quick round icon beside the bubble.
            const quick = (act, title) => '<button type="button" data-act="' + act + '" data-mid="' + id + '" title="' + title + '" class="grid h-7 w-7 place-items-center rounded-full bg-white text-gray-500 shadow-sm ring-1 ring-gray-100 hover:bg-gray-50 hover:text-[var(--color-heading)]">' + ICON[act] + '</button>';
            // gated=true → hidden automatically once the 1-hour window elapses (checked on open).
            const item = (act, label, danger, gated) => '<button type="button" data-act="' + act + '" data-mid="' + id + '"' + (gated ? ' data-gated="1"' : '') + ' class="flex w-full items-center gap-2.5 px-3 py-1.5 text-left text-xs font-medium ' + (danger ? 'text-red-600' : 'text-[var(--color-heading)]') + ' hover:bg-gray-50">' + ICON[act] + '<span>' + label + '</span></button>';
            // Kebab menu holds the less-common actions.
            let items = '';
            if (mine) items += item('edit', 'Edit', false, true);            // author only, within window
            items += item('forward', 'Forward');
            if (mine || IS_ADMIN) items += item('delete', 'Delete', true, mine && !IS_ADMIN); // author gated; admin anytime
            const wrap = document.createElement('div');
            wrap.className = 'msg-actions relative mb-5 flex items-center gap-1 self-end';
            wrap.setAttribute('data-actions', '');
            wrap.innerHTML =
                quick('reply', 'Reply') + quick('react', 'React') + (hasText ? quick('copy', 'Copy') : '') +
                '<div class="relative"><button type="button" data-kebab class="grid h-7 w-7 place-items-center rounded-full bg-white text-gray-400 shadow-sm ring-1 ring-gray-100 hover:bg-gray-50"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg></button>' +
                '<div data-menu class="absolute bottom-9 z-30 hidden min-w-[8.5rem] ' + (mine ? 'right-0' : 'left-0') + ' overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg">' + items + '</div></div>';
            // Place the bar on the bubble's INNER side (toward the chat centre) for both
            // sent (row-reverse) and received rows — appending after the bubble does this.
            row.appendChild(wrap);
            renderReactions(row, readReactions(row));
        }

        function fileChipHtml(d, mine) {
            if (!d.attachment) return '';
            if (d.is_image) return '<a href="' + d.attachment + '" target="_blank" rel="noopener"><img src="' + d.attachment + '" class="mt-1 max-h-56 rounded-lg" alt=""></a>';
            const box = mine ? 'bg-white/15' : 'bg-gray-50 border border-gray-100';
            return '<a href="' + d.attachment + '" target="_blank" rel="noopener" class="mt-1 flex items-center gap-2 rounded-lg ' + box + ' px-3 py-2"><svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05 12 20.5a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-3-3l8-8"/></svg><span class="truncate text-xs font-medium">' + esc(d.attachment_name || 'file') + '</span></a>';
        }
        function avatarHtml(d, mine) {
            if (mine) return '';
            if (d.avatar) return '<img src="' + d.avatar + '" class="h-7 w-7 rounded-full object-cover" alt="">';
            return '<span class="h-7 w-7 grid place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]">' + esc((d.author || '?').charAt(0).toUpperCase()) + '</span>';
        }
        function makeRow(d) {
            const mine = Number(d.user_id) === ME;
            const name = (isGroup && !mine) ? '<p class="mb-0.5 px-1 text-xs font-semibold text-[var(--color-heading)]">' + esc(d.author) + '</p>' : '';
            const bubble = mine ? 'bg-[var(--color-primary)] text-white rounded-br-sm' : 'bg-white text-[var(--color-heading)] border border-gray-100 rounded-bl-sm';
            const bodyHtml = d.body ? '<div class="chat-html break-words">' + d.body + '</div>' : '';
            const checklistHtml = checklistToHtml(d.id, d.checklist, mine, d.checklist_title);
            const row = document.createElement('div');
            row.className = 'group flex items-end gap-2 ' + (mine ? 'flex-row-reverse' : '');
            row.dataset.msgId = d.id;
            row.dataset.mine = mine ? '1' : '0';
            row.dataset.author = d.author || '—';
            row.dataset.reactions = JSON.stringify(d.reactions || {});
            row.dataset.created = toEpoch(d.created_at) || Math.floor(Date.now() / 1000);
            row.innerHTML = avatarHtml(d, mine)
                + '<div class="max-w-[75%]" data-bubble-wrap>' + name
                + '<div class="rounded-2xl px-3.5 py-2 text-sm ' + bubble + '">' + quotedHtml(d.quoted, mine) + bodyHtml + checklistHtml + fileChipHtml(d, mine) + '</div>'
                + '<div data-reactions-box class="mt-1 flex flex-wrap gap-1 ' + (mine ? 'justify-end' : '') + '"></div>'
                + '<p class="mt-0.5 px-1 text-[11px] text-gray-400 ' + (mine ? 'text-right' : '') + '">' + (d.time || '') + '<span data-edited-tag class="' + (d.edited ? '' : 'hidden') + '"> · edited</span></p></div>';
            attachMenu(row);
            return row;
        }
        // Build the interactive checklist markup for a message (mirrors the Blade render).
        function checklistToHtml(msgId, list, mine, title) {
            if (!Array.isArray(list) || !list.length) return '';
            const head = title ? '<p class="mt-1 mb-0.5 text-sm font-bold ' + (mine ? 'text-white' : 'text-[var(--color-heading)]') + '">' + esc(title) + '</p>' : '';
            const rows = list.map((it, i) => {
                const on = !!it.checked;
                const box = on ? 'border-emerald-500 bg-emerald-500 text-white' : (mine ? 'border-white/40' : 'border-gray-300');
                const tick = on ? '<svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>' : '';
                return '<li class="flex items-start gap-2 text-sm"><button type="button" data-check-toggle data-msg="' + msgId + '" data-idx="' + i + '" class="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded border ' + box + '">' + tick + '</button><span class="' + (on ? 'line-through opacity-60' : '') + '">' + esc(it.text) + '</span></li>';
            }).join('');
            return head + '<ul class="chat-checklist mt-1 space-y-1" data-msg-checklist="' + msgId + '">' + rows + '</ul>';
        }
        // Tick / untick — persist and update the button, then let the broadcast update everyone else.
        function applyChecklistToggle(msgId, idx, checked) {
            const li = document.querySelector('[data-msg-checklist="' + msgId + '"] [data-check-toggle][data-idx="' + idx + '"]');
            if (!li) return;
            const span = li.parentElement.querySelector('span');
            if (checked) {
                li.classList.remove('border-white/40', 'border-gray-300'); li.classList.add('border-emerald-500', 'bg-emerald-500', 'text-white');
                li.innerHTML = '<svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>';
                span.classList.add('line-through', 'opacity-60');
            } else {
                li.classList.remove('border-emerald-500', 'bg-emerald-500', 'text-white'); li.classList.add('border-gray-300');
                li.innerHTML = '';
                span.classList.remove('line-through', 'opacity-60');
            }
        }
        scroll.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-check-toggle]'); if (!btn) return;
            const msgId = btn.dataset.msg, idx = Number(btn.dataset.idx);
            const checked = !(btn.classList.contains('bg-emerald-500'));
            applyChecklistToggle(msgId, idx, checked);
            fetch(CHECKLIST_BASE + '/' + msgId + '/checklist', {
                method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ index: idx, checked }),
            }).then(r => { if (!r.ok) applyChecklistToggle(msgId, idx, !checked); }).catch(() => applyChecklistToggle(msgId, idx, !checked));
        });

        // ── Day separators ("Today" / "Yesterday" / "July 19, 2026") ──
        // Compared message-to-message in the browser's local time so it stays
        // consistent with itself (never against a server-tz string).
        function sameLocalDay(a, b) {
            if (!a || !b) return false;
            const da = new Date(a * 1000), db = new Date(b * 1000);
            return da.getFullYear() === db.getFullYear() && da.getMonth() === db.getMonth() && da.getDate() === db.getDate();
        }
        function dayLabel(epoch) {
            const d = new Date(epoch * 1000), now = new Date();
            const t = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const dd = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            const diff = Math.round((t - dd) / 86400000);
            if (diff === 0) return 'Today';
            if (diff === 1) return 'Yesterday';
            return d.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
        }
        function daySepEl(epoch) {
            const sep = document.createElement('div');
            sep.className = 'flex justify-center py-1';
            sep.setAttribute('data-date-sep', '');
            sep.innerHTML = '<span class="rounded-full bg-white px-3 py-1 text-[11px] font-semibold text-gray-500 shadow-sm ring-1 ring-gray-100">' + esc(dayLabel(epoch)) + '</span>';
            return sep;
        }
        function lastRowEpoch() {
            const rows = scroll.querySelectorAll('[data-msg-id]');
            const last = rows[rows.length - 1];
            return last ? Number(last.dataset.created || 0) : 0;
        }

        function append(d) {
            if (seen.has(Number(d.id))) return;
            seen.add(Number(d.id));
            const epoch = toEpoch(d.created_at) || Math.floor(Date.now() / 1000);
            if (!sameLocalDay(lastRowEpoch(), epoch)) scroll.appendChild(daySepEl(epoch));
            const row = makeRow(d);
            row.classList.add('msg-in');
            scroll.appendChild(row);
            toBottom();
        }

        // ── Load earlier messages (pagination) + drag-and-drop ──
        const OLDER_URL = root.dataset.olderUrl;
        const earlierWrap = document.getElementById('chat-load-earlier');
        const earlierBtn = document.getElementById('chat-load-earlier-btn');
        let loadingOlder = false;
        async function loadOlder() {
            if (loadingOlder || !earlierBtn) return;
            loadingOlder = true; earlierBtn.textContent = 'Loading…';
            const firstRow = scroll.querySelector('[data-msg-id]');
            const beforeId = firstRow ? firstRow.dataset.msgId : 0;
            const prevHeight = scroll.scrollHeight;
            try {
                const r = await fetch(OLDER_URL + '?before_id=' + beforeId, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                (d.messages || []).slice().reverse().forEach(m => {           // newest-first insert keeps order
                    if (seen.has(Number(m.id))) return;
                    seen.add(Number(m.id));
                    earlierWrap.after(makeRow(m));
                });
                earlierWrap.classList.toggle('hidden', !d.has_more);
                earlierWrap.classList.toggle('flex', !!d.has_more);
                scroll.scrollTop = scroll.scrollHeight - prevHeight;         // keep position
            } catch {} finally { loadingOlder = false; earlierBtn.textContent = 'Load earlier messages'; }
        }
        if (earlierBtn) earlierBtn.addEventListener('click', loadOlder);
        scroll.addEventListener('scroll', function () {
            if (scroll.scrollTop < 60 && !earlierWrap.classList.contains('hidden') && !loadingOlder) loadOlder();
        });

        // Drag a file onto the thread → attach it (with an optional message as caption).
        const dropOverlay = document.getElementById('chat-drop-overlay');
        let dragDepth = 0;
        root.addEventListener('dragenter', function (e) { if (![...(e.dataTransfer?.types || [])].includes('Files')) return; e.preventDefault(); dragDepth++; dropOverlay.classList.remove('hidden'); dropOverlay.classList.add('flex'); });
        root.addEventListener('dragover', function (e) { if ([...(e.dataTransfer?.types || [])].includes('Files')) e.preventDefault(); });
        root.addEventListener('dragleave', function () { dragDepth--; if (dragDepth <= 0) { dragDepth = 0; dropOverlay.classList.add('hidden'); dropOverlay.classList.remove('flex'); } });
        root.addEventListener('drop', function (e) {
            if (!e.dataTransfer.files.length) return;
            e.preventDefault(); dragDepth = 0; dropOverlay.classList.add('hidden'); dropOverlay.classList.remove('flex');
            const dt = new DataTransfer(); dt.items.add(e.dataTransfer.files[0]); fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change'));
        });
        function removeMsg(id) { const el = scroll.querySelector('[data-msg-id="' + id + '"]'); if (el) el.remove(); }
        function updateBody(id, html) {
            const el = scroll.querySelector('[data-msg-id="' + id + '"]');
            if (!el) return;
            const body = el.querySelector('.chat-html');
            if (body) body.innerHTML = html;
            const tag = el.querySelector('[data-edited-tag]');
            if (tag) tag.classList.remove('hidden');
        }
        // Attach the actions menu to every server-rendered message on first paint.
        scroll.querySelectorAll('[data-msg-id]').forEach(attachMenu);

        // ── Per-message menu: open/close + Copy / Edit / Forward / Delete ──
        const closeMenus = () => scroll.querySelectorAll('[data-menu]').forEach(m => m.classList.add('hidden'));
        // Re-evaluate the 1-hour window at open time: hide the author's Edit/Delete once it has passed.
        function refreshGated(wrap) {
            const row = wrap.closest('[data-msg-id]');
            const created = Number(row.dataset.created || 0);
            const expired = !created || (Date.now() / 1000 - created >= WINDOW_SECS);
            wrap.querySelectorAll('[data-gated="1"]').forEach(b => b.classList.toggle('hidden', expired));
        }
        scroll.addEventListener('click', function (e) {
            // Toggle a reaction by clicking its chip.
            const chip = e.target.closest('.rx-chip[data-rx]');
            if (chip) { const row = chip.closest('[data-msg-id]'); sendReaction(row.dataset.msgId, chip.dataset.rx); return; }
            const kebab = e.target.closest('[data-kebab]');
            if (kebab) { const wrap = kebab.parentElement; const m = wrap.querySelector('[data-menu]'); const open = !m.classList.contains('hidden'); closeMenus(); if (!open) { refreshGated(wrap); m.classList.remove('hidden'); } return; }
            const act = e.target.closest('[data-act]');
            if (!act) return;
            const id = act.dataset.mid;
            const row = scroll.querySelector('[data-msg-id="' + id + '"]');
            if (act.dataset.act === 'reply') { startReply(row); return; }
            if (act.dataset.act === 'react') { openEmoji(act, id); return; }
            closeMenus();
            if (act.dataset.act === 'copy') {
                const txt = row.querySelector('.chat-html') ? row.querySelector('.chat-html').innerText : '';
                (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(() => window.Razin.toast('Copied to clipboard')).catch(() => window.Razin.toast('Copy failed'));
            } else if (act.dataset.act === 'edit') {
                startEdit(id, row.querySelector('.chat-html') ? row.querySelector('.chat-html').innerHTML : '');
            } else if (act.dataset.act === 'forward') {
                window.Razin.openForward(id);
            } else if (act.dataset.act === 'delete') {
                if (!confirm('Delete this message?')) return;
                fetch(DEL_BASE + '/' + id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                    .then(r => r.json()).then(d => { if (d.error) window.Razin.toast(d.error); else removeMsg(id); }).catch(() => {});
            }
        });
        document.addEventListener('click', function (e) { if (!e.target.closest('[data-menu]') && !e.target.closest('[data-kebab]')) closeMenus(); });

        // ── Edit mode: load the message into the composer, submit as a PATCH ──
        let editingId = null;
        const editBanner = document.getElementById('chat-edit-banner');
        function startEdit(id, html) {
            editingId = id;
            setHtml(html || '');
            editBanner.classList.remove('hidden'); editBanner.classList.add('flex');
            input.focus();
        }
        function cancelEdit() {
            editingId = null;
            clearInput();
            editBanner.classList.add('hidden'); editBanner.classList.remove('flex');
        }
        document.getElementById('chat-edit-cancel').addEventListener('click', cancelEdit);

        const typingInd = document.getElementById('typing-ind');
        const typingText = document.getElementById('typing-text');
        let typingHideTimer = null, lastTypingSent = 0;
        function showTyping(nm) {
            typingText.textContent = isGroup ? (nm + ' is typing…') : 'typing…';
            typingInd.classList.remove('hidden'); typingInd.classList.add('flex');
            clearTimeout(typingHideTimer);
            typingHideTimer = setTimeout(() => { typingInd.classList.add('hidden'); typingInd.classList.remove('flex'); }, 3500);
        }
        input.addEventListener('input', function () {
            const now = Date.now(); if (now - lastTypingSent < 2500) return; lastTypingSent = now;
            fetch(TYPING_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } }).catch(() => {});
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const hasText = getText().length > 0;

            // Editing an existing message → PATCH (text only, within the 1-hour window).
            if (editingId) {
                if (!hasText) return;
                const id = editingId;
                fetch(DEL_BASE + '/' + id, {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ body: getHtml() }),
                }).then(r => r.json()).then(d => {
                    if (d.error) { window.Razin.toast(d.error); return; }
                    updateBody(id, d.body);
                    cancelEdit();
                }).catch(() => {});
                return;
            }

            const hasFile = fileInput.files.length > 0;
            const hasChecklist = checklistItems.length > 0;
            if (!hasText && !hasFile && !hasChecklist) return;
            const sentPreview = getText() || (hasFile ? '📎 ' + (fileInput.files[0]?.name || 'Attachment') : (hasChecklist ? '☑ Checklist' : ''));
            const fd = new FormData();
            fd.append('_token', CSRF);
            fd.append('body', hasText ? getHtml() : '');
            checklistItems.forEach(t => fd.append('checklist[]', t));
            const checklistTitle = (clTitleEl && clTitleEl.value.trim()) || '';
            if (hasChecklist && checklistTitle) fd.append('checklist_title', checklistTitle);
            if (hasFile) fd.append('attachment', fileInput.files[0]);
            if (replyToId) fd.append('reply_to_id', replyToId);
            fetch(STORE_URL, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d && d.id) append({ id: d.id, user_id: ME, author: 'You', body: d.body, checklist: d.checklist, checklist_title: d.checklist_title, attachment: d.attachment, attachment_name: d.attachment_name, is_image: d.is_image, time: d.time, created_at: d.created_at, quoted: d.quoted, reactions: d.reactions });
                    clearInput(); clearChecklist(); fileInput.value = ''; fileChip.classList.add('hidden'); fileChip.classList.remove('flex');
                    cancelReply();
                    // My own message → bump this conversation to the top of the left list (no unread).
                    if (window.Razin.bumpConversation) window.Razin.bumpConversation({
                        type: CONV_TYPE, convId: CONV, userId: Number(root.dataset.counterpartId) || null,
                        preview: sentPreview, time: (d && d.time) || '', incUnread: false,
                    });
                }).catch(() => {});
        });

        window.Razin.onPresence = function (online) {
            const el = document.getElementById('peer-status');
            if (el) el.textContent = online.has(Number(el.dataset.peer)) ? 'Online' : 'Offline';
        };

        // Reverb — cleanly swap channels when switching conversations.
        if (window.Razin.currentConvChannel && window.Razin.pusher) {
            window.Razin.pusher.unsubscribe(window.Razin.currentConvChannel);
        }
        (function subscribe() {
            if (!window.Razin.pusher) return setTimeout(subscribe, 400);
            const chName = 'chat.conversation.' + CONV;
            window.Razin.currentConvChannel = chName;
            const ch = window.Razin.pusher.subscribe(chName);
            ch.bind('message.posted', function (d) { if (seen.has(Number(d.id))) return; append(d); if (Number(d.user_id) !== ME) { playSound(); markReadPing(); } });
            ch.bind('message.deleted', function (d) { removeMsg(d.id); });
            ch.bind('message.edited', function (d) { updateBody(d.id, d.body); });
            ch.bind('message.reacted', function (d) { const row = scroll.querySelector('[data-msg-id="' + d.id + '"]'); if (row) renderReactions(row, d.reactions || {}); });
            ch.bind('checklist.toggled', function (d) { applyChecklistToggle(String(d.id), Number(d.index), !!d.checked); });
            ch.bind('typing', function (d) { if (Number(d.user_id) !== ME) showTyping(d.name); });

            // Personal channel → live-reorder the left list for EVERY conversation I'm in (bind once).
            if (!window.__chatListBound) {
                window.__chatListBound = true;
                window.Razin.pusher.subscribe('chat.user.' + ME).bind('message.posted', function (d) {
                    if (typeof window.Razin.bumpConversation !== 'function') return;
                    const open = Number(window.Razin.openConversation) === Number(d.conversation_id);
                    window.Razin.bumpConversation({
                        type: d.conv_type || 'direct',
                        convId: d.conversation_id,
                        userId: d.user_id,
                        preview: d.preview,
                        time: d.time,
                        incUnread: !open && Number(d.user_id) !== ME,
                    });
                });
            }
        })();
    };

    (function () {
        window.Razin = window.Razin || {};

        // ── Toast (bound once) ──
        if (!window.Razin.toast) {
            let toastTimer = null;
            window.Razin.toast = function (msg) {
                const el = document.getElementById('chat-toast');
                if (!el) return;
                el.textContent = msg;
                el.classList.remove('hidden');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(() => el.classList.add('hidden'), 2200);
            };
        }

        // ── Forward modal (bound once) ──
        if (!window.__chatFwdBound) {
            window.__chatFwdBound = true;
            const modal = document.getElementById('fwd-modal');
            const listEl = document.getElementById('fwd-list');
            const searchEl = document.getElementById('fwd-search');
            let fwdMsgId = null;
            const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
            const closeModal = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); fwdMsgId = null; };

            function renderList(q) {
                const team = window.__chatTeam || [];
                const needle = (q || '').trim().toLowerCase();
                listEl.innerHTML = team
                    .filter(p => !needle || p.name.toLowerCase().includes(needle))
                    .map(p => {
                        const av = p.avatar
                            ? '<img src="' + p.avatar + '" class="h-8 w-8 rounded-full object-cover" alt="">'
                            : '<span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]">' + p.initial + '</span>';
                        return '<button type="button" data-fwd-to="' + p.id + '" class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-gray-50">' + av + '<span class="text-sm font-medium text-[var(--color-heading)]">' + p.name + '</span></button>';
                    }).join('') || '<p class="px-2 py-4 text-center text-sm text-gray-400">No teammates found.</p>';
            }

            window.Razin.openForward = function (msgId) {
                fwdMsgId = msgId;
                searchEl.value = '';
                renderList('');
                modal.classList.remove('hidden'); modal.classList.add('flex');
                searchEl.focus();
            };

            searchEl.addEventListener('input', () => renderList(searchEl.value));
            document.getElementById('fwd-close').addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
            listEl.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-fwd-to]');
                if (!btn || !fwdMsgId) return;
                const base = window.Razin.msgBase || '/admin/chat/messages';
                fetch(base + '/' + fwdMsgId + '/forward', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: Number(btn.dataset.fwdTo) }),
                }).then(r => r.json()).then(d => {
                    if (d.error) { window.Razin.toast(d.error); return; }
                    closeModal();
                    window.Razin.toast('Forwarded to ' + (d.to || 'teammate'));
                }).catch(() => window.Razin.toast('Forward failed'));
            });
        }

        // ── Conversation switch: fetch only the thread and swap it in (no blink, sidebar untouched). ──
        // Bound ONCE on document in the capture phase (must run before the <a> navigation starts).
        window.__chatLoadSeq = window.__chatLoadSeq || 0;
        if (!window.__chatClickBound) {
            window.__chatClickBound = true;
            document.addEventListener('click', function (e) {
                if (e.target.closest('[data-pin-toggle]')) return;   // pin button handled separately
                const a = e.target.closest && e.target.closest('a[data-conv-link]');
                if (!a) return;
                const pane = document.getElementById('thread-pane');
                const aside = document.getElementById('chat-aside');
                if (!pane || !aside) return;
                e.preventDefault();
                e.stopPropagation();

                // Instant feedback: highlight + clear this row's unread.
                aside.querySelectorAll('[data-conv-link].active-conv').forEach(el => el.classList.remove('active-conv'));
                a.classList.add('active-conv');
                a.dataset.unreadCount = '0';
                const badge = a.querySelector('[data-unread]'); if (badge) badge.remove();
                if (window.Razin.refreshChatFilter) window.Razin.refreshChatFilter();

                const seq = ++window.__chatLoadSeq;
                fetch(a.href, { headers: { 'X-Chat-Partial': '1', 'Accept': 'text/html' } })
                    .then(r => r.text())
                    .then(html => {
                        if (seq !== window.__chatLoadSeq) return;     // a newer click superseded this one
                        const tmp = document.createElement('div');
                        tmp.innerHTML = html;
                        const fresh = tmp.querySelector('#thread-pane');
                        if (!fresh) throw new Error('no pane');
                        pane.innerHTML = fresh.innerHTML;             // old thread stays until this instant → no blink
                        const root = pane.querySelector('#thread-root');
                        if (root) window.__initChatThread(root);
                    })
                    .catch(() => { window.location.href = a.href; }); // fall back to a full navigation
            }, true);   // capture
        }

        // ── Sidebar: search + tabs + unread-filter + pin (bound once; the aside survives thread swaps) ──
        if (!window.__chatSidebarBound) {
            window.__chatSidebarBound = true;
            const aside = document.getElementById('chat-aside');
            const listEl = aside && aside.querySelector('[data-chat-list]');
            if (aside && listEl) {
                const searchEl = aside.querySelector('[data-chat-search]');
                const tabsEl = aside.querySelector('[data-chat-tabs]');
                const filterBtn = aside.querySelector('[data-chat-filter]');
                const pinnedSection = aside.querySelector('[data-pinned-section]');
                const pinnedItems = aside.querySelector('[data-pinned-items]');
                const emptyEl = aside.querySelector('[data-list-empty]');
                const ME_SIDE = Number((document.querySelector('#thread-root') || {}).dataset ? document.querySelector('#thread-root').dataset.me : 0) || 0;
                const PIN_KEY = 'razin_chat_pins_' + ME_SIDE;
                let currentTab = 'all';
                let unreadOnly = false;

                const getPins = () => { try { return JSON.parse(localStorage.getItem(PIN_KEY) || '[]'); } catch (e) { return []; } };
                const setPins = (arr) => { try { localStorage.setItem(PIN_KEY, JSON.stringify(arr)); } catch (e) {} };
                const rowByKey = (key) => [...listEl.querySelectorAll('.chat-row')].find(r => r.dataset.pinKey === key);

                // Put a row back under its home section header (top of that section).
                function restoreRow(row) {
                    row.classList.remove('is-pinned');
                    const headId = row.dataset.kind === 'group' ? 'ch-header' : (row.dataset.kind === 'client' ? 'cl-header' : 'dm-header');
                    const head = document.getElementById(headId);
                    if (head) head.after(row); else listEl.insertBefore(row, emptyEl);
                }
                // Reflect the stored pin set into the DOM (move rows in/out of the Pinned box).
                function applyPins() {
                    const pins = getPins();
                    [...pinnedItems.querySelectorAll('.chat-row')].forEach(row => { if (!pins.includes(row.dataset.pinKey)) restoreRow(row); });
                    pins.forEach(key => {
                        const row = rowByKey(key) || [...pinnedItems.querySelectorAll('.chat-row')].find(r => r.dataset.pinKey === key);
                        if (row) { row.classList.add('is-pinned'); pinnedItems.appendChild(row); }
                    });
                    aside.querySelectorAll('.chat-row').forEach(r => r.classList.toggle('is-pinned', pins.includes(r.dataset.pinKey)));
                }

                function rowMatchesTab(row) {
                    const k = row.dataset.kind;
                    switch (currentTab) {
                        case 'team': return k === 'direct' || k === 'group';
                        case 'clients': return k === 'client';
                        case 'groups': return k === 'group';
                        case 'unread': return Number(row.dataset.unreadCount) > 0;
                        default: return true;                       // 'all'
                    }
                }
                function applyFilter() {
                    const q = (searchEl && searchEl.value || '').trim().toLowerCase();
                    const byKind = {};
                    aside.querySelectorAll('.chat-row').forEach(row => {
                        const show = rowMatchesTab(row)
                            && (!q || (row.dataset.chatRow || '').includes(q))
                            && (!unreadOnly || Number(row.dataset.unreadCount) > 0);
                        row.style.display = show ? '' : 'none';
                        if (show && row.dataset.kind && !row.classList.contains('is-pinned')) byKind[row.dataset.kind] = true;
                    });
                    document.querySelectorAll('[data-group-head]').forEach(h => { h.style.display = byKind[h.dataset.groupHead] ? '' : 'none'; });
                    const pinnedVisible = [...pinnedItems.querySelectorAll('.chat-row')].some(r => r.style.display !== 'none');
                    pinnedSection.classList.toggle('hidden', !pinnedVisible);
                    const anyVisible = pinnedVisible || Object.keys(byKind).length > 0;
                    if (emptyEl) emptyEl.classList.toggle('hidden', anyVisible);
                }
                window.Razin.refreshChatFilter = applyFilter;      // let bump/read updates re-run the filter

                applyPins();
                applyFilter();

                searchEl && searchEl.addEventListener('input', applyFilter);
                tabsEl && tabsEl.addEventListener('click', function (e) {
                    const b = e.target.closest('[data-tab]'); if (!b) return;
                    currentTab = b.dataset.tab;
                    aside.querySelectorAll('.chat-tab').forEach(t => t.classList.toggle('is-active', t === b));
                    applyFilter();
                });
                // The admin theme fights button background/colour changes, so mark the active
                // state with a small primary corner dot (a child span renders reliably).
                const paintToggleBtn = (btn, on) => {
                    let dot = btn.querySelector('[data-on-dot]');
                    if (on && !dot) {
                        dot = document.createElement('span');
                        dot.setAttribute('data-on-dot', '');
                        dot.style.cssText = 'position:absolute;top:-3px;right:-3px;width:9px;height:9px;border-radius:9999px;background:var(--color-primary);box-shadow:0 0 0 2px #fff';
                        btn.style.position = 'relative';
                        btn.appendChild(dot);
                    } else if (!on && dot) {
                        dot.remove();
                    }
                };
                window.Razin.paintToggleBtn = paintToggleBtn;
                filterBtn && filterBtn.addEventListener('click', function () {
                    unreadOnly = !unreadOnly;
                    filterBtn.setAttribute('aria-pressed', unreadOnly ? 'true' : 'false');
                    paintToggleBtn(filterBtn, unreadOnly);
                    applyFilter();
                });
                // Pin / unpin (bubbles; the conv-link capture handler bails on pin clicks).
                aside.addEventListener('click', function (e) {
                    const pin = e.target.closest('[data-pin-toggle]'); if (!pin) return;
                    e.preventDefault(); e.stopPropagation();
                    const row = pin.closest('.chat-row'); if (!row) return;
                    const key = row.dataset.pinKey;
                    let pins = getPins();
                    pins = pins.includes(key) ? pins.filter(k => k !== key) : [...pins, key];
                    setPins(pins);
                    applyPins();
                    applyFilter();
                });

                // ⌘K / Ctrl-K focuses the search.
                document.addEventListener('keydown', function (e) {
                    if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); searchEl && searchEl.focus(); }
                });
            }
        }

        // Init whatever conversation is open on first load.
        try {
            const root0 = document.querySelector('#thread-root');
            if (root0) window.__initChatThread(root0);
        } catch (err) { /* thread init failed — clicks still work */ }
    })();
    </script>
@endsection
