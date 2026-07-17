@extends('admin.layouts.app')
@section('title', 'WhatsApp Inbox')

@php
    $canReply = auth()->user()->allows('whatsapp', 'reply');
    $canAssign = auth()->user()->allows('whatsapp', 'assign');
@endphp

@section('content')
    @if (! $settings->isConfigured())
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <span>WhatsApp is not connected yet. Add your API credentials to start receiving and replying to messages.</span>
            @if (auth()->user()->allows('whatsapp', 'settings'))<a href="{{ route('admin.whatsapp-settings') }}" class="rounded-lg bg-amber-500 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-600">Configure WhatsApp</a>@endif
        </div>
    @endif

    <div x-data="waInbox()" x-init="init()" class="flex h-[calc(100vh-9rem)] overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        {{-- ============ LEFT: chat list ============ --}}
        <aside class="flex w-80 shrink-0 flex-col border-r border-gray-100">
            <div class="border-b border-gray-100 p-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-base font-bold text-[var(--color-heading)]">WhatsApp</h1>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-600">{{ $stats['open'] }} open</span>
                </div>
                <div class="relative mt-3">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                    <input type="text" x-model.debounce.300ms="search" @input="loadChats()" placeholder="Search chats…" class="h-9 w-full rounded-lg border-gray-200 pl-9 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <template x-for="f in filters" :key="f.key">
                        <button type="button" @click="setFilter(f.key)" class="rounded-full px-2.5 py-1 text-[11px] font-semibold transition"
                                :class="filter === f.key ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'" x-text="f.label"></button>
                    </template>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto">
                <template x-if="!chats.length">
                    <p class="py-10 text-center text-sm text-gray-300">No conversations.</p>
                </template>
                <template x-for="c in chats" :key="c.id">
                    <button type="button" @click="openChat(c.id)"
                            class="flex w-full items-start gap-3 border-b border-gray-50 px-4 py-3 text-left transition hover:bg-gray-50"
                            :class="active && active.id === c.id ? 'bg-[var(--color-primary-soft)]' : ''">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700" x-text="c.initials"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-bold text-[var(--color-heading)]" x-text="c.name"></span>
                                <span class="shrink-0 text-[10px] text-gray-400" x-text="c.at"></span>
                            </span>
                            <span class="mt-0.5 flex items-center gap-1.5">
                                <span class="truncate text-xs text-gray-500" x-text="c.preview || '—'"></span>
                                <span x-show="c.unread" class="ml-auto grid h-4 min-w-4 shrink-0 place-items-center rounded-full bg-emerald-500 px-1 text-[10px] font-bold text-white" x-text="c.unread"></span>
                            </span>
                            <span class="mt-1 flex flex-wrap gap-1">
                                <template x-for="l in c.labels" :key="l.name">
                                    <span class="rounded px-1.5 py-0.5 text-[9px] font-bold" :style="`background:${l.color}1a;color:${l.color}`" x-text="l.name"></span>
                                </template>
                            </span>
                        </span>
                    </button>
                </template>
            </div>
        </aside>

        {{-- ============ MIDDLE: thread ============ --}}
        <section class="flex min-w-0 flex-1 flex-col">
            <template x-if="!active">
                <div class="grid flex-1 place-items-center text-center">
                    <div>
                        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-emerald-500">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v10Z"/></svg>
                        </span>
                        <p class="mt-3 text-sm text-gray-400">Select a conversation to start.</p>
                    </div>
                </div>
            </template>

            <template x-if="active">
                <div class="flex min-h-0 flex-1 flex-col">
                    {{-- Thread header --}}
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-[var(--color-heading)]" x-text="active.name"></p>
                            <p class="text-xs text-gray-400" x-text="'+' + active.wa_id"></p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($canAssign)
                                <select @change="assign($event.target.value)" class="h-9 rounded-lg border-gray-200 text-xs">
                                    <option value="">Unassigned</option>
                                    @foreach ($agents as $a)<option value="{{ $a->id }}" :selected="active.assigned_to == {{ $a->id }}">{{ $a->name }}</option>@endforeach
                                </select>
                            @endif
                            <select @change="setStatus($event.target.value)" class="h-9 rounded-lg border-gray-200 text-xs">
                                @foreach (\App\Models\WhatsappChat::STATUSES as $k => $v)<option value="{{ $k }}" :selected="active.status === '{{ $k }}'">{{ $v }}</option>@endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Messages --}}
                    <div class="flex-1 space-y-2 overflow-y-auto bg-gray-50/60 p-5" x-ref="thread">
                        <template x-for="m in messages" :key="m.id">
                            <div class="flex" :class="m.direction === 'out' ? 'justify-end' : 'justify-start'">
                                <div class="max-w-[70%] rounded-2xl px-3.5 py-2 text-sm shadow-sm"
                                     :class="m.direction === 'out' ? 'bg-emerald-500 text-white' : 'bg-white text-[var(--color-heading)]'">
                                    {{-- media --}}
                                    <template x-if="m.media && m.type === 'image'"><img :src="m.media" class="mb-1 max-h-52 rounded-lg"></template>
                                    <template x-if="m.media && m.type === 'video'"><video :src="m.media" controls class="mb-1 max-h-52 rounded-lg"></video></template>
                                    <template x-if="m.media && m.type === 'audio'"><audio :src="m.media" controls class="mb-1 w-52"></audio></template>
                                    <template x-if="m.media && m.type === 'document'"><a :href="m.media" target="_blank" class="mb-1 flex items-center gap-1.5 underline" :class="m.direction === 'out' ? 'text-white' : 'text-[var(--color-primary)]'"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M7 3h7l5 5v13H7z"/></svg><span x-text="m.media_name || 'Document'"></span></a></template>
                                    <p x-show="m.body" x-text="m.body" class="whitespace-pre-line"></p>
                                    <p class="mt-1 flex items-center justify-end gap-1 text-[10px]" :class="m.direction === 'out' ? 'text-white/70' : 'text-gray-400'">
                                        <span x-text="m.at"></span>
                                        <template x-if="m.direction === 'out'"><span x-text="m.status === 'read' ? '✓✓' : (m.status === 'delivered' ? '✓✓' : (m.status === 'failed' ? '⚠' : '✓'))"></span></template>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Composer --}}
                    @if ($canReply)
                        <div class="border-t border-gray-100 p-3">
                            <div class="mb-2 flex flex-wrap gap-1.5" x-show="showQuick">
                                @foreach ($quickReplies as $qr)
                                    <button type="button" @click="draft = @js($qr->body); showQuick = false" class="rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-200">{{ $qr->shortcut ?: \Illuminate\Support\Str::limit($qr->body, 20) }}</button>
                                @endforeach
                            </div>
                            <form @submit.prevent="send()" class="flex items-end gap-2">
                                <button type="button" @click="showQuick = !showQuick" class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-gray-400 hover:bg-gray-100" title="Quick replies">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 2 3 14h7l-1 8 10-12h-7l1-8Z"/></svg>
                                </button>
                                <textarea x-model="draft" @keydown.enter.exact.prevent="send()" rows="1" placeholder="Type a message…" class="max-h-32 flex-1 resize-none rounded-xl border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"></textarea>
                                <button type="submit" :disabled="!draft.trim() || sending" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-500 text-white hover:bg-emerald-600 disabled:opacity-50">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4 20-7Z"/></svg>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </template>
        </section>

        {{-- ============ RIGHT: details ============ --}}
        <aside class="hidden w-72 shrink-0 flex-col overflow-y-auto border-l border-gray-100 xl:flex" x-show="active" x-cloak>
            <template x-if="active">
                <div class="p-5">
                    <div class="text-center">
                        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-lg font-bold text-emerald-700" x-text="active.initials"></span>
                        <p class="mt-2 font-bold text-[var(--color-heading)]" x-text="active.name"></p>
                        <p class="text-xs text-gray-400" x-text="'+' + active.wa_id"></p>
                    </div>

                    {{-- Client match --}}
                    <template x-if="active.client">
                        <div class="mt-4 rounded-lg bg-gray-50 p-3 text-xs">
                            <p class="font-semibold text-[var(--color-heading)]">Matched client</p>
                            <p class="mt-1 text-gray-500" x-text="active.client.email"></p>
                            <p class="text-gray-500" x-show="active.client.company" x-text="active.client.company"></p>
                        </div>
                    </template>

                    {{-- Labels --}}
                    <div class="mt-5">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-400">Labels</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($labels as $lbl)
                                <button type="button" @click="toggleLabel({{ $lbl->id }})"
                                        class="rounded-full border px-2.5 py-1 text-[11px] font-semibold transition"
                                        :class="active.label_ids.includes({{ $lbl->id }}) ? '' : 'opacity-40'"
                                        :style="`border-color:{{ $lbl->color }};background:{{ $lbl->color }}1a;color:{{ $lbl->color }}`">{{ $lbl->name }}</button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div class="mt-5">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-400">Internal Notes</p>
                        <form @submit.prevent="addNote()" class="mb-3">
                            <textarea x-model="noteDraft" rows="2" placeholder="Add a private note…" class="w-full rounded-lg border-gray-200 text-xs focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"></textarea>
                            <button class="mt-1.5 rounded-lg bg-amber-400 px-3 py-1.5 text-xs font-semibold text-ink-900 hover:bg-amber-500">Add note</button>
                        </form>
                        <ul class="space-y-2">
                            <template x-for="n in active.notes" :key="n.id">
                                <li class="rounded-lg bg-amber-50 p-2.5 text-xs">
                                    <p class="whitespace-pre-line text-[var(--color-heading)]" x-text="n.body"></p>
                                    <p class="mt-1 text-[10px] text-gray-400"><span x-text="n.user"></span> · <span x-text="n.at"></span></p>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </template>
        </aside>
    </div>

    <script>
        function waInbox() {
            return {
                chats: [], active: null, messages: [], draft: '', noteDraft: '', sending: false, showQuick: false,
                search: '', filter: 'all',
                filters: [
                    { key: 'all', label: 'All' }, { key: 'unread', label: 'Unread' }, { key: 'open', label: 'Open' },
                    { key: 'pending', label: 'Pending' }, { key: 'mine', label: 'Mine' }, { key: 'resolved', label: 'Resolved' },
                ],
                csrf: document.querySelector('meta[name=csrf-token]').content,
                init() {
                    this.loadChats();
                    // Live updates via Reverb.
                    const wait = setInterval(() => {
                        if (window.Razin && window.Razin.pusher) {
                            clearInterval(wait);
                            const ch = window.Razin.pusher.subscribe('whatsapp.inbox');
                            ch.bind('message', (e) => {
                                this.loadChats();
                                if (this.active && this.active.id === e.chat_id) this.openChat(e.chat_id, true);
                            });
                        }
                    }, 400);
                },
                params() {
                    const p = new URLSearchParams();
                    if (this.search) p.set('search', this.search);
                    if (this.filter === 'mine') p.set('mine', '1');
                    else if (this.filter !== 'all') p.set('status', this.filter);
                    return p.toString();
                },
                setFilter(k) { this.filter = k; this.loadChats(); },
                async loadChats() {
                    const r = await fetch(@js(route('admin.whatsapp.chats')) + '?' + this.params());
                    this.chats = (await r.json()).chats;
                },
                async openChat(id, silent = false) {
                    const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + id);
                    const d = await r.json();
                    this.active = d.chat; this.messages = d.messages;
                    if (!silent) { const c = this.chats.find(x => x.id === id); if (c) c.unread = 0; }
                    this.$nextTick(() => { const t = this.$refs.thread; if (t) t.scrollTop = t.scrollHeight; });
                },
                async send() {
                    if (!this.draft.trim() || this.sending) return;
                    this.sending = true;
                    const body = this.draft; this.draft = '';
                    try {
                        const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/send', {
                            method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ body }),
                        });
                        if (r.ok) { this.messages.push((await r.json()).message); this.$nextTick(() => { const t = this.$refs.thread; if (t) t.scrollTop = t.scrollHeight; }); this.loadChats(); }
                        else { alert((await r.json()).error || 'Could not send.'); this.draft = body; }
                    } catch { this.draft = body; } finally { this.sending = false; }
                },
                async post(url, data) {
                    return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(data) });
                },
                assign(id) { this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/assign', { assigned_to: id || null }); this.active.assigned_to = id; },
                setStatus(s) { this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/status', { status: s }); this.active.status = s; this.loadChats(); },
                async toggleLabel(id) {
                    const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/label', { label_id: id });
                    if (r.ok) { this.active.label_ids = (await r.json()).labels.map(l => l.id); this.loadChats(); }
                },
                async addNote() {
                    if (!this.noteDraft.trim()) return;
                    const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/note', { body: this.noteDraft });
                    if (r.ok) { this.active.notes.unshift((await r.json()).note); this.noteDraft = ''; }
                },
            };
        }
    </script>
@endsection
