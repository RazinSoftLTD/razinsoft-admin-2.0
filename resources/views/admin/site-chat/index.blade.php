@extends('admin.layouts.app')
@section('title', 'Website Chat')

@section('content')
    {{-- The visitor's side of this lives on razinsoft.com; this is the team's side. Same shape as
         the WhatsApp inbox on purpose — list on the left, conversation on the right — because it
         is the same job, and nobody should have to learn a second inbox. --}}
    <div x-data="siteChat()" x-init="init()" class="flex h-[calc(100vh-8.5rem)] overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">

        {{-- ============ LEFT: conversations ============ --}}
        <aside class="flex w-full max-w-xs shrink-0 flex-col border-r border-gray-100">
            <div class="border-b border-gray-100 px-4 py-3">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-bold text-[var(--color-heading)]">Website Chat</p>
                        <p class="text-[11px] text-[var(--color-muted)]">Visitors on razinsoft.com</p>
                    </div>
                    <span x-show="unread" x-cloak class="grid h-5 min-w-5 place-items-center rounded-full bg-emerald-500 px-1.5 text-[10px] font-bold text-white" x-text="unread"></span>
                </div>

                <div class="relative mt-3">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                    <input x-model.debounce.400ms="search" @input="loadChats()" type="search" placeholder="Search name, email or message"
                           class="h-9 w-full rounded-lg border-gray-200 pl-9 text-sm">
                </div>

                <div class="mt-3 flex flex-wrap gap-1.5">
                    <template x-for="f in filters" :key="f.key">
                        <button type="button" @click="filter = f.key; loadChats()"
                                class="rounded-full px-2.5 py-1 text-[11px] font-semibold transition"
                                :class="filter === f.key ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                x-text="f.label"></button>
                    </template>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <template x-for="c in chats" :key="c.id">
                    <button type="button" @click="openChat(c.id)"
                            class="flex w-full items-start gap-3 border-b border-gray-50 px-4 py-3 text-left transition hover:bg-gray-50"
                            :class="active && active.id === c.id ? 'bg-indigo-50/60' : ''">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]" x-text="c.initials"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-sm font-bold text-[var(--color-heading)]" x-text="c.name"></span>
                                <span class="shrink-0 text-[10px] text-gray-400" x-text="c.at"></span>
                            </span>
                            <span class="mt-0.5 flex items-center justify-between gap-2">
                                <span class="truncate text-xs text-[var(--color-muted)]" x-text="c.preview || '—'"></span>
                                <span x-show="c.unread" class="grid h-4 min-w-4 shrink-0 place-items-center rounded-full bg-emerald-500 px-1 text-[9px] font-bold text-white" x-text="c.unread"></span>
                            </span>
                        </span>
                    </button>
                </template>
                <p x-show="!chats.length" x-cloak class="px-4 py-10 text-center text-sm text-gray-400">No conversations yet.</p>
            </div>
        </aside>

        {{-- ============ RIGHT: the conversation ============ --}}
        <section class="flex min-w-0 flex-1 flex-col">
            <template x-if="!active">
                <div class="grid flex-1 place-items-center text-center">
                    <div>
                        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v10Z"/></svg>
                        </span>
                        <p class="mt-3 text-sm text-gray-400">Pick a conversation to read it.</p>
                    </div>
                </div>
            </template>

            <template x-if="active">
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-[var(--color-heading)]" x-text="active.name"></p>
                            <p class="truncate text-[11px] text-gray-400">
                                <span x-text="active.email || 'no email yet'"></span>
                                <span x-show="active.page_url"> · <span x-text="active.page_url"></span></span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if (auth()->user()->hasPermission('site_chat.assign'))
                                <select @change="assign($event.target.value)" class="h-9 rounded-lg border-gray-200 text-xs">
                                    <option value="">Unassigned</option>
                                    @foreach ($agents as $a)<option value="{{ $a->id }}" :selected="active.assigned_to == {{ $a->id }}">{{ $a->name }}</option>@endforeach
                                </select>
                            @endif
                            <select @change="setStatus($event.target.value)" class="h-9 rounded-lg border-gray-200 text-xs">
                                @foreach ($statuses as $k => $v)<option value="{{ $k }}" :selected="active.status === '{{ $k }}'">{{ $v }}</option>@endforeach
                            </select>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto bg-gray-50/60 px-5 py-4" x-ref="thread">
                        <template x-for="m in messages" :key="m.id">
                            <div class="flex" :class="m.direction === 'in' ? 'justify-start' : 'justify-end'">
                                <div class="max-w-lg rounded-2xl px-3.5 py-2.5 text-sm shadow-sm"
                                     :class="m.direction === 'in' ? 'bg-white text-[var(--color-heading)]' : 'text-white'"
                                     :style="m.direction === 'out' ? 'background: var(--color-primary)' : ''">
                                    <p class="whitespace-pre-line leading-relaxed" x-text="m.body"></p>
                                    <p class="mt-1 text-[10px]" :class="m.direction === 'in' ? 'text-gray-400' : 'text-white/70'">
                                        <span x-text="m.time"></span>
                                        <span x-show="m.agent"> · <span x-text="m.agent"></span></span>
                                        <span x-show="m.ai"> · AI</span>
                                    </p>
                                </div>
                            </div>
                        </template>
                        <p x-show="!messages.length" x-cloak class="py-10 text-center text-xs text-gray-400">No messages yet.</p>
                    </div>

                    @if (auth()->user()->hasPermission('site_chat.reply'))
                        <form @submit.prevent="send()" class="flex items-end gap-2 border-t border-gray-100 px-4 py-3">
                            <textarea x-model="draft" rows="1" @keydown.enter.prevent="send()" placeholder="Type a reply… (Enter to send)"
                                      class="max-h-32 min-h-[2.75rem] flex-1 rounded-xl border-gray-200 text-sm"></textarea>
                            <button :disabled="sending || !draft.trim()"
                                    class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-white disabled:opacity-60"
                                    style="background: var(--color-primary)">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4 20-7Z"/></svg>
                            </button>
                        </form>
                    @else
                        <p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-400">You can read these conversations, but not reply.</p>
                    @endif
                </div>
            </template>
        </section>
    </div>

    <script>
        function siteChat() {
            return {
                chats: @js($chats->map(fn ($c) => ['id' => $c->id, 'name' => $c->displayName(), 'initials' => $c->initials(), 'preview' => $c->last_message_preview, 'at' => $c->last_message_at?->diffForHumans(), 'unread' => (int) $c->unread_count, 'status' => $c->status])->values()),
                unread: {{ $unreadCount }},
                active: null,
                messages: [],
                draft: '',
                sending: false,
                search: '',
                filter: 'all',
                filters: [
                    { key: 'all', label: 'All' }, { key: 'unread', label: 'Unread' },
                    { key: 'open', label: 'Open' }, { key: 'pending', label: 'Pending' }, { key: 'resolved', label: 'Resolved' },
                ],
                csrf: document.querySelector('meta[name=csrf-token]').content,

                init() {
                    // Live: the same Reverb connection the rest of the panel uses.
                    const wait = setInterval(() => {
                        if (window.Razin && window.Razin.pusher) {
                            clearInterval(wait);
                            window.Razin.pusher.subscribe('site-chat.inbox').bind('message', (e) => {
                                if (this.active && this.active.id === e.chatId) this.openChat(e.chatId, true);
                                this.loadChats();
                            });
                        }
                    }, 400);
                },

                params() {
                    const p = new URLSearchParams();
                    if (this.search.trim()) p.set('search', this.search.trim());
                    if (this.filter !== 'all') p.set('status', this.filter);
                    return p.toString();
                },

                async loadChats() {
                    const r = await fetch(@js(route('admin.site-chat.chats')) + '?' + this.params());
                    const d = await r.json();
                    this.chats = d.chats;
                    this.unread = d.unread;
                },

                async openChat(id, silent = false) {
                    const r = await fetch(@js(url('admin/site-chat')) + '/' + id);
                    const d = await r.json();
                    this.active = d.chat;
                    this.messages = d.messages;
                    if (!silent) this.loadChats();
                    this.$nextTick(() => { const t = this.$refs.thread; if (t) t.scrollTop = t.scrollHeight; });
                },

                async send() {
                    const body = this.draft.trim();
                    if (!body || this.sending) return;
                    this.sending = true;
                    try {
                        const r = await fetch(@js(url('admin/site-chat')) + '/' + this.active.id + '/reply', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ body }),
                        });
                        if (!r.ok) { alert('Could not send that reply.'); return; }
                        const d = await r.json();
                        this.messages.push(d.message);
                        this.draft = '';
                        this.loadChats();
                        this.$nextTick(() => { const t = this.$refs.thread; if (t) t.scrollTop = t.scrollHeight; });
                    } finally {
                        this.sending = false;
                    }
                },

                post(url, data) {
                    return fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(data),
                    });
                },
                setStatus(status) { this.post(@js(url('admin/site-chat')) + '/' + this.active.id + '/status', { status }); this.active.status = status; this.loadChats(); },
                assign(id) { this.post(@js(url('admin/site-chat')) + '/' + this.active.id + '/assign', { assigned_to: id || null }); this.active.assigned_to = id || null; this.loadChats(); },
            };
        }
    </script>
@endsection
