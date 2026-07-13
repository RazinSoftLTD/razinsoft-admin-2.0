@extends('admin.layouts.app')
@section('title', 'Teams')

@php
    $me = auth()->user();

    $groups = $conversations->where('type', 'group');
    $directByUser = [];
    foreach ($conversations->where('type', 'direct') as $c) {
        $other = $c->counterpart($me);
        if ($other) $directByUser[$other->id] = $c;
    }

    $avatar = function ($u, $size = 'h-9 w-9') {
        if ($u && $u->photo_url) {
            return '<img src="'.e($u->photo_url).'" class="'.$size.' rounded-full object-cover" alt="">';
        }
        $initial = strtoupper(substr($u->name ?? '?', 0, 1));
        return '<span class="'.$size.' grid place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]">'.$initial.'</span>';
    };
@endphp

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <style>
        .chat-html a { text-decoration: underline; }
        .chat-html p { margin: 0; }
        .chat-html ul, .chat-html ol { margin: .25rem 0; padding-left: 1.25rem; }
        .chat-html ul { list-style: disc; }
        .chat-html ol { list-style: decimal; }
        #chat-editor .ql-editor { min-height: 2.5rem; max-height: 10rem; font-size: .875rem; padding: .5rem .75rem; }
        #chat-editor .ql-editor.ql-blank::before { left: .75rem; font-style: normal; color: #9ca3af; }
        .chat-composer .ql-toolbar.ql-snow { border: 0; border-bottom: 1px solid #f0f0f0; padding: .35rem .5rem; }
        .chat-composer .ql-container.ql-snow { border: 0; }
        [data-conv-link] { transition: background-color .12s ease; }
        [data-conv-link]:hover { background: #f9fafb; }
        [data-conv-link].active-conv, [data-conv-link].active-conv:hover { background: var(--color-primary-soft); }
        [data-conv-link].active-conv .conv-name { color: var(--color-primary); }
        @keyframes chatFadeIn { from { opacity: 0; } to { opacity: 1; } }
        #thread-root { animation: chatFadeIn .18s ease; }
    </style>
@endpush

@section('content')
    <div class="flex h-[calc(100dvh-7rem)] overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">

        {{-- ───────── Left rail ───────── --}}
        <aside x-data="{ tab: '{{ $tab }}' }" class="flex w-72 shrink-0 flex-col border-r border-gray-100">
            <div class="flex items-center justify-between px-4 py-3.5 border-b border-gray-100">
                <h1 class="text-sm font-bold text-[var(--color-heading)]">Messages</h1>
                @if ($me->hasPermission('chat.create_group'))
                    <a href="{{ route('admin.chat.groups.create') }}" title="New group" x-show="tab === 'team'"
                       class="grid h-8 w-8 place-items-center rounded-lg border border-gray-200 text-[var(--color-heading)] hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    </a>
                @endif
            </div>

            {{-- Team / Client tabs (Client tab only for staff who hold chat.clients) --}}
            @if ($canClients)
                @php $clientUnread = $clientConversations->sum(fn ($c) => $c->unreadCountFor($me)); @endphp
                <div class="flex gap-1 border-b border-gray-100 px-2 py-2">
                    <button type="button" @click="tab = 'team'" :class="tab === 'team' ? 'bg-[var(--color-primary)] text-white shadow-sm' : 'text-[var(--color-muted)] hover:bg-gray-50'" class="flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition">Team</button>
                    <button type="button" @click="tab = 'client'" :class="tab === 'client' ? 'bg-[var(--color-primary)] text-white shadow-sm' : 'text-[var(--color-muted)] hover:bg-gray-50'" class="flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition">
                        Client @if ($clientUnread)<span class="ml-1 rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{{ $clientUnread }}</span>@endif
                    </button>
                </div>
            @endif

            {{-- Team panel --}}
            <div x-show="tab === 'team'" class="min-h-0 flex-1 overflow-y-auto px-2 py-3">
                <input type="text" data-chat-search placeholder="Search people…"
                       class="mb-3 h-9 w-full rounded-lg border border-gray-200 px-3 text-sm">

                <p class="px-2 pb-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">Channels</p>
                @forelse ($groups as $g)
                    @php $un = $g->unreadCountFor($me); $on = $active && $active->id === $g->id; @endphp
                    <a href="{{ route('admin.chat.show', $g) }}" data-turbo="false" data-conv-link data-conv="{{ $g->id }}" data-chat-row="{{ strtolower($g->name) }}"
                       class="flex items-center gap-2.5 rounded-lg px-2 py-2 {{ $on ? 'active-conv' : '' }}">
                        <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-lg bg-gray-100 text-gray-500">
                            @if ($g->photo_url)
                                <img src="{{ $g->photo_url }}" class="h-full w-full object-cover" alt="">
                            @else
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 9h12M6 15h12M9 4 7 20M17 4l-2 16"/></svg>
                            @endif
                        </span>
                        <span class="conv-name min-w-0 flex-1 truncate text-sm font-medium text-[var(--color-heading)]">{{ $g->name }}</span>
                        @if ($un)<span data-unread class="grid h-5 min-w-5 place-items-center rounded-full bg-red-500 px-1.5 text-[11px] font-bold text-white">{{ $un }}</span>@endif
                    </a>
                @empty
                    <p class="px-2 py-1.5 text-xs text-[var(--color-muted)]">No channels yet.</p>
                @endforelse

                <p class="mt-4 px-2 pb-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">Direct Messages</p>
                @forelse ($people as $p)
                    @php $c = $directByUser[$p->id] ?? null; $un = $c ? $c->unreadCountFor($me) : 0; $on = $active && $c && $active->id === $c->id; @endphp
                    <a href="{{ route('admin.chat.direct', $p) }}" data-turbo="false" data-conv-link data-user="{{ $p->id }}" data-chat-row="{{ strtolower($p->name) }}"
                       class="flex items-center gap-2.5 rounded-lg px-2 py-2 {{ $on ? 'active-conv' : '' }}">
                        <span class="relative shrink-0">
                            {!! $avatar($p) !!}
                            <span data-online="{{ $p->id }}" class="{{ $p->isOnline() ? '' : 'hidden' }} absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full bg-green-500 ring-2 ring-white"></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="conv-name block truncate text-sm font-medium text-[var(--color-heading)]">{{ $p->name }}</span>
                            <span class="block truncate text-xs text-[var(--color-muted)]">{{ $p->designation->name ?? 'Team member' }}</span>
                        </span>
                        @if ($un)<span data-unread class="grid h-5 min-w-5 place-items-center rounded-full bg-red-500 px-1.5 text-[11px] font-bold text-white">{{ $un }}</span>@endif
                    </a>
                @empty
                    <p class="px-2 py-1.5 text-xs text-[var(--color-muted)]">No teammates yet.</p>
                @endforelse
            </div>

            {{-- Client panel — shared inbox of customer conversations --}}
            @if ($canClients)
                <div x-show="tab === 'client'" x-cloak class="min-h-0 flex-1 overflow-y-auto px-2 py-3">
                    <input type="text" data-chat-search placeholder="Search clients…" class="mb-3 h-9 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    <p class="px-2 pb-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">Client Messages</p>
                    @forelse ($clientConversations as $c)
                        @php
                            $client = $c->clientMember() ?? $c->members->first();
                            $un = $c->unreadCountFor($me);
                            $on = $active && $active->id === $c->id;
                        @endphp
                        <a href="{{ route('admin.chat.show', $c) }}" data-turbo="false" data-conv-link data-conv="{{ $c->id }}" data-chat-row="{{ strtolower($client->name ?? 'client') }}"
                           class="flex items-center gap-2.5 rounded-lg px-2 py-2 {{ $on ? 'active-conv' : '' }}">
                            <span class="relative shrink-0">
                                {!! $avatar($client) !!}
                                <span class="absolute -bottom-0.5 -right-0.5 grid h-3.5 w-3.5 place-items-center rounded-full bg-sky-500 ring-2 ring-white" title="Client"><svg class="h-2 w-2 text-white" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0Z"/></svg></span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="conv-name block truncate text-sm font-medium text-[var(--color-heading)]">{{ $client->name ?? 'Client' }}</span>
                                <span class="block truncate text-xs text-[var(--color-muted)]">{{ $c->latestMessage?->preview ?: 'New conversation' }}</span>
                            </span>
                            @if ($un)<span data-unread class="grid h-5 min-w-5 place-items-center rounded-full bg-red-500 px-1.5 text-[11px] font-bold text-white">{{ $un }}</span>@endif
                        </a>
                    @empty
                        <div class="px-2 py-10 text-center text-xs text-[var(--color-muted)]">
                            <svg class="mx-auto mb-2 h-8 w-8 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v10Z"/></svg>
                            No client messages yet.
                        </div>
                    @endforelse
                </div>
            @endif
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
        if (!root || !window.Quill) return;
        // Guard against a double-init on the same frame content (would nest Quill's toolbar).
        const editorEl = document.getElementById('chat-editor');
        if (!editorEl || editorEl.classList.contains('ql-container')) return;
        window.Razin = window.Razin || {};

        const ME = Number(root.dataset.me);
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;
        const CONV = Number(root.dataset.convId);
        const isGroup = root.dataset.isGroup === '1';
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
        if (activeRow) { activeRow.classList.add('active-conv'); const b = activeRow.querySelector('[data-unread]'); if (b) b.remove(); }

        const esc = s => (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const playSound = () => { if (typeof window.Razin.playMessageSound === 'function') window.Razin.playMessageSound(); };
        const toBottom = () => { scroll.scrollTop = scroll.scrollHeight; };
        toBottom();

        let lastReadPing = 0;
        function markReadPing() {
            const now = Date.now(); if (now - lastReadPing < 1200) return; lastReadPing = now;
            fetch(READ_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } }).catch(() => {});
        }

        const quill = new Quill('#chat-editor', {
            theme: 'snow',
            placeholder: 'Write a message…',
            modules: { toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ background: ['#fff3bf', '#d3f9d8', '#ffe3e3', '#e5dbff', false] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link', 'clean'],
            ] },
        });
        quill.keyboard.addBinding({ key: 13 }, { shiftKey: false }, function () { form.requestSubmit(); return false; });

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
            if (row.querySelector('[data-kebab]')) return;
            const id = row.dataset.msgId;
            const mine = row.dataset.mine === '1';
            const hasText = !!(row.querySelector('.chat-html') && row.querySelector('.chat-html').innerText.trim());
            const svg = (p) => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">' + p + '</svg>';
            const ICON = {
                copy: svg('<rect x="9" y="9" width="11" height="11" rx="2"/><path stroke-linecap="round" d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>'),
                edit: svg('<path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>'),
                forward: svg('<path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M20 12H4"/>'),
                delete: svg('<path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7"/>'),
            };
            // gated=true → hidden automatically once the 1-hour window elapses (checked on open).
            const item = (act, label, danger, gated) => '<button type="button" data-act="' + act + '" data-mid="' + id + '"' + (gated ? ' data-gated="1"' : '') + ' class="flex w-full items-center gap-2.5 px-3 py-1.5 text-left text-xs font-medium ' + (danger ? 'text-red-600' : 'text-[var(--color-heading)]') + ' hover:bg-gray-50">' + ICON[act] + '<span>' + label + '</span></button>';
            let items = hasText ? item('copy', 'Copy') : '';
            if (mine) items += item('edit', 'Edit', false, true);            // author only, within window
            items += item('forward', 'Forward');
            if (mine || IS_ADMIN) items += item('delete', 'Delete', true, mine && !IS_ADMIN); // author gated; admin anytime
            const wrap = document.createElement('div');
            wrap.className = 'relative mb-5 self-end';
            wrap.innerHTML =
                '<button type="button" data-kebab class="grid h-7 w-7 place-items-center rounded-full text-gray-400 opacity-0 transition hover:bg-gray-100 group-hover:opacity-100 focus:opacity-100"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg></button>' +
                '<div data-menu class="absolute bottom-9 z-30 hidden min-w-[8.5rem] ' + (mine ? 'right-0' : 'left-0') + ' overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg">' + items + '</div>';
            row.insertBefore(wrap, row.querySelector('[data-bubble-wrap]'));
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
        function append(d) {
            if (seen.has(Number(d.id))) return;
            seen.add(Number(d.id));
            const mine = Number(d.user_id) === ME;
            const name = (isGroup && !mine) ? '<p class="mb-0.5 px-1 text-xs font-semibold text-[var(--color-heading)]">' + esc(d.author) + '</p>' : '';
            const bubble = mine ? 'bg-[var(--color-primary)] text-white rounded-br-sm' : 'bg-white text-[var(--color-heading)] border border-gray-100 rounded-bl-sm';
            const bodyHtml = d.body ? '<div class="chat-html break-words">' + d.body + '</div>' : '';
            const row = document.createElement('div');
            row.className = 'group flex items-end gap-2 ' + (mine ? 'flex-row-reverse' : '');
            row.dataset.msgId = d.id;
            row.dataset.mine = mine ? '1' : '0';
            row.dataset.created = toEpoch(d.created_at) || Math.floor(Date.now() / 1000);
            row.innerHTML = avatarHtml(d, mine)
                + '<div class="max-w-[75%]" data-bubble-wrap>' + name
                + '<div class="rounded-2xl px-3.5 py-2 text-sm ' + bubble + '">' + bodyHtml + fileChipHtml(d, mine) + '</div>'
                + '<p class="mt-0.5 px-1 text-[11px] text-gray-400 ' + (mine ? 'text-right' : '') + '">' + (d.time || '') + '<span data-edited-tag class="' + (d.edited ? '' : 'hidden') + '"> · edited</span></p></div>';
            scroll.appendChild(row);
            attachMenu(row);
            toBottom();
        }
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
            const kebab = e.target.closest('[data-kebab]');
            if (kebab) { const wrap = kebab.parentElement; const m = wrap.querySelector('[data-menu]'); const open = !m.classList.contains('hidden'); closeMenus(); if (!open) { refreshGated(wrap); m.classList.remove('hidden'); } return; }
            const act = e.target.closest('[data-act]');
            if (!act) return;
            const id = act.dataset.mid;
            const row = scroll.querySelector('[data-msg-id="' + id + '"]');
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
            quill.root.innerHTML = html || '';
            editBanner.classList.remove('hidden'); editBanner.classList.add('flex');
            quill.focus();
        }
        function cancelEdit() {
            editingId = null;
            quill.setContents([]);
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
        quill.on('text-change', function (_d, _o, source) {
            if (source !== 'user') return;
            const now = Date.now(); if (now - lastTypingSent < 2500) return; lastTypingSent = now;
            fetch(TYPING_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } }).catch(() => {});
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const hasText = quill.getText().trim().length > 0;

            // Editing an existing message → PATCH (text only, within the 1-hour window).
            if (editingId) {
                if (!hasText) return;
                const id = editingId;
                fetch(DEL_BASE + '/' + id, {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ body: quill.root.innerHTML }),
                }).then(r => r.json()).then(d => {
                    if (d.error) { window.Razin.toast(d.error); return; }
                    updateBody(id, d.body);
                    cancelEdit();
                }).catch(() => {});
                return;
            }

            const hasFile = fileInput.files.length > 0;
            if (!hasText && !hasFile) return;
            const fd = new FormData();
            fd.append('_token', CSRF);
            fd.append('body', hasText ? quill.root.innerHTML : '');
            if (hasFile) fd.append('attachment', fileInput.files[0]);
            fetch(STORE_URL, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d && d.id) append({ id: d.id, user_id: ME, author: 'You', body: d.body, attachment: d.attachment, attachment_name: d.attachment_name, is_image: d.is_image, time: d.time, created_at: d.created_at });
                    quill.setContents([]); fileInput.value = ''; fileChip.classList.add('hidden'); fileChip.classList.remove('flex');
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
            ch.bind('typing', function (d) { if (Number(d.user_id) !== ME) showTyping(d.name); });
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
                const a = e.target.closest && e.target.closest('a[data-conv-link]');
                if (!a) return;
                const pane = document.getElementById('thread-pane');
                const aside = document.querySelector('aside');
                if (!pane || !aside) return;
                e.preventDefault();
                e.stopPropagation();

                // Instant feedback: highlight + clear this row's unread.
                aside.querySelectorAll('[data-conv-link].active-conv').forEach(el => el.classList.remove('active-conv'));
                a.classList.add('active-conv');
                const badge = a.querySelector('[data-unread]'); if (badge) badge.remove();

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

        // Left-list search (re-bound per render; the input element is fresh each load).
        const search = document.querySelector('[data-chat-search]');
        if (search && !search.dataset.bound) {
            search.dataset.bound = '1';
            search.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                document.querySelectorAll('[data-chat-row]').forEach(function (row) {
                    row.style.display = row.dataset.chatRow.includes(q) ? '' : 'none';
                });
            });
        }

        // Init whatever conversation is open on first load.
        try {
            const root0 = document.querySelector('#thread-root');
            if (root0) window.__initChatThread(root0);
        } catch (err) { /* thread init failed — clicks still work */ }
    })();
    </script>
@endsection
