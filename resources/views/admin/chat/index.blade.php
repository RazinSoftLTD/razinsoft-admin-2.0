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

        const delBtn = (id) => '<button type="button" data-del="' + id + '" title="Delete message" class="mb-5 grid h-7 w-7 shrink-0 place-items-center rounded-full text-gray-400 opacity-0 transition hover:bg-red-50 hover:text-red-500 group-hover:opacity-100"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7"/></svg></button>';
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
            const canDel = mine || IS_ADMIN;
            const name = (isGroup && !mine) ? '<p class="mb-0.5 px-1 text-xs font-semibold text-[var(--color-heading)]">' + esc(d.author) + '</p>' : '';
            const bubble = mine ? 'bg-[var(--color-primary)] text-white rounded-br-sm' : 'bg-white text-[var(--color-heading)] border border-gray-100 rounded-bl-sm';
            const bodyHtml = d.body ? '<div class="chat-html break-words">' + d.body + '</div>' : '';
            const row = document.createElement('div');
            row.className = 'group flex items-end gap-2 ' + (mine ? 'flex-row-reverse' : '');
            row.dataset.msgId = d.id;
            row.innerHTML = avatarHtml(d, mine) + (canDel ? delBtn(d.id) : '')
                + '<div class="max-w-[75%]">' + name
                + '<div class="rounded-2xl px-3.5 py-2 text-sm ' + bubble + '">' + bodyHtml + fileChipHtml(d, mine) + '</div>'
                + '<p class="mt-0.5 px-1 text-[11px] text-gray-400 ' + (mine ? 'text-right' : '') + '">' + (d.time || '') + '</p></div>';
            scroll.appendChild(row); toBottom();
        }
        function removeMsg(id) { const el = scroll.querySelector('[data-msg-id="' + id + '"]'); if (el) el.remove(); }

        scroll.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-del]'); if (!btn) return;
            if (!confirm('Delete this message?')) return;
            fetch(DEL_BASE + '/' + btn.dataset.del, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                .then(r => r.json()).then(() => removeMsg(btn.dataset.del)).catch(() => {});
        });

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
            const hasFile = fileInput.files.length > 0;
            if (!hasText && !hasFile) return;
            const fd = new FormData();
            fd.append('_token', CSRF);
            fd.append('body', hasText ? quill.root.innerHTML : '');
            if (hasFile) fd.append('attachment', fileInput.files[0]);
            fetch(STORE_URL, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d && d.id) append({ id: d.id, user_id: ME, author: 'You', body: d.body, attachment: d.attachment, attachment_name: d.attachment_name, is_image: d.is_image, time: d.time });
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
            ch.bind('typing', function (d) { if (Number(d.user_id) !== ME) showTyping(d.name); });
        })();
    };

    (function () {
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
