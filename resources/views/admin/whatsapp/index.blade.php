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

    {{-- Full-screen inbox: break out of the layout padding and fill the viewport below the topbar --}}
    {{-- forceCollapse hides the main sidebar so the inbox gets the full width (reset on leaving the page). --}}
    <div x-data="waInbox()" x-init="init(); forceCollapse = true; lockScroll = true" style="height:calc(100dvh - 4rem); margin:-1.5rem;" class="flex overflow-hidden bg-white">
        {{-- ============ LEFT: chat list ============ --}}
        <aside class="flex w-80 shrink-0 flex-col border-r border-gray-100">
            <div class="border-b border-gray-100 p-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-base font-bold text-[var(--color-heading)]">WhatsApp</h1>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-600">{{ $stats['open'] }} open</span>
                </div>
                <div class="relative mt-3">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                    <input type="text" x-model="search" @input.debounce.300ms="loadChats()" placeholder="Search name, number or message…" class="h-9 w-full rounded-lg border-gray-200 pl-9 pr-8 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                    <button type="button" x-show="search" @click="search = ''; loadChats()" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500" title="Clear">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <template x-for="f in filters" :key="f.key">
                        <button type="button" @click="setFilter(f.key)" class="rounded-full px-2.5 py-1 text-[11px] font-semibold transition"
                                :class="filter === f.key ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'" x-text="f.label"></button>
                    </template>
                </div>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <template x-if="!chats.length">
                    <p class="py-10 text-center text-sm text-gray-300">No conversations.</p>
                </template>
                <template x-for="c in chats" :key="c.id">
                    <button type="button" @click="openChat(c.id)"
                            class="flex w-full items-start gap-3 border-b border-gray-50 px-4 py-3 text-left transition hover:bg-gray-50"
                            :class="active && active.id === c.id ? 'bg-[var(--color-primary-soft)]' : ''">
                        <template x-if="c.avatar"><img :src="c.avatar" class="h-10 w-10 shrink-0 rounded-full object-cover"></template>
                        <span x-show="!c.avatar" class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-xs font-bold" :class="c.is_group ? 'text-white' : 'bg-emerald-100 text-emerald-700'" :style="c.is_group ? ('background:' + c.color) : ''">
                            <template x-if="c.is_group"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 0 0-3-3.87M9 20H4v-1a4 4 0 0 1 3-3.87m0 0a4 4 0 1 1 5.9 0M17 11a3 3 0 1 0-2.5-4.5"/></svg></template>
                            <span x-show="!c.is_group" x-text="c.initials"></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-1.5">
                                    <span class="truncate text-sm font-bold text-[var(--color-heading)]" x-text="c.name"></span>
                                    <svg x-show="c.is_group" class="h-3 w-3 shrink-0 text-indigo-400" fill="currentColor" viewBox="0 0 24 24" title="Group"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3Zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3Zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5Zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5Z"/></svg>
                                </span>
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
                        <button type="button" @click="showInfo = !showInfo" class="flex min-w-0 items-center gap-3 text-left">
                            <template x-if="active.avatar"><img :src="active.avatar" class="h-9 w-9 shrink-0 rounded-full object-cover"></template>
                            <span x-show="!active.avatar" class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-bold" :class="active.is_group ? 'text-white' : 'bg-emerald-100 text-emerald-700'" :style="active.is_group ? ('background:' + active.color) : ''">
                                <template x-if="active.is_group"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 0 0-3-3.87M9 20H4v-1a4 4 0 0 1 3-3.87m0 0a4 4 0 1 1 5.9 0M17 11a3 3 0 1 0-2.5-4.5"/></svg></template>
                                <span x-show="!active.is_group" x-text="active.initials"></span>
                            </span>
                            <span class="min-w-0">
                                <span class="flex items-center gap-1.5">
                                    <span class="block truncate text-sm font-bold text-[var(--color-heading)]" x-text="active.name"></span>
                                    <span x-show="active.is_group" class="shrink-0 text-[10px] font-medium text-indigo-400">Group</span>
                                </span>
                                <span class="block truncate text-xs text-gray-400" x-text="active.last_seen ? 'last seen ' + active.last_seen : active.wa_id"></span>
                            </span>
                        </button>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="markUnread()" class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Mark as unread">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/><circle cx="18" cy="6" r="3" fill="currentColor" stroke="none"/></svg>
                            </button>
                            <button type="button" @click="showInfo = !showInfo" class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Contact info">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v5M12 8h.01"/></svg>
                            </button>
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

                    {{-- Messages — WhatsApp Web look (beige doodle bg, green/white bubbles) --}}
                    {{-- flex-col + mt-auto anchors messages to the bottom (empty space stays on top, like WhatsApp). --}}
                    <div class="wa-thread flex flex-1 flex-col overflow-y-auto px-6 py-6 sm:px-16" x-ref="thread">
                        <div class="space-y-3" style="margin-top:auto">
                        <template x-for="(m, i) in messages" :key="m.id">
                            <div>
                                {{-- Date separator pill --}}
                                <template x-if="showDate(i)">
                                    <div class="mb-4 mt-2 flex justify-center">
                                        <span class="rounded-lg bg-white/90 px-3 py-1 text-[11px] font-medium uppercase tracking-wide text-gray-500 shadow-sm" x-text="dayLabel(m)"></span>
                                    </div>
                                </template>
                                <div class="flex flex-col" :class="m.direction === 'out' ? 'items-end' : 'items-start'">
                                    <div class="relative rounded-lg px-3.5 pb-2 pt-2 text-sm shadow-[0_1px_0.5px_rgba(0,0,0,0.13)]" style="max-width:72%;"
                                         :class="m.direction === 'out' ? 'wa-out text-gray-800' : 'wa-in text-gray-800'">
                                        {{-- group sender name --}}
                                        <template x-if="m.sender_name && m.direction === 'in'">
                                            <span class="mb-0.5 block text-xs font-bold text-indigo-600" x-text="m.sender_name"></span>
                                        </template>
                                        {{-- media --}}
                                        <template x-if="m.media && m.type === 'image'"><img :src="m.media" class="mb-1 max-h-64 rounded-md"></template>
                                        <template x-if="m.media && m.type === 'video'"><video :src="m.media" controls class="mb-1 max-h-64 rounded-md"></video></template>
                                        <template x-if="m.media && m.type === 'audio'"><audio :src="m.media" controls class="mb-1 w-56"></audio></template>
                                        <template x-if="m.media && m.type === 'document'"><a :href="m.media" target="_blank" class="mb-1 flex items-center gap-2 rounded-md bg-black/5 px-2.5 py-2 text-gray-700 hover:bg-black/10"><svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/></svg><span class="truncate" x-text="m.media_name || 'Document'"></span></a></template>
                                        <span x-show="m.body" x-text="m.body" class="whitespace-pre-line break-words align-bottom"></span>
                                        {{-- inline meta (time + ticks) --}}
                                        <span class="float-right ml-2 mt-1 inline-flex translate-y-0.5 items-center gap-0.5 text-[10px] leading-none text-gray-500/80">
                                            <span x-text="m.at"></span>
                                            <template x-if="m.direction === 'out'">
                                                <svg class="h-3.5 w-3.5" :class="m.status === 'read' ? 'text-[#53bdeb]' : 'text-gray-400'" viewBox="0 0 18 12" fill="none">
                                                    <template x-if="m.status === 'failed'"><path d="M9 1a5 5 0 1 0 0 10A5 5 0 0 0 9 1Zm.6 7.5H8.4v-1.2h1.2v1.2Zm0-2.1H8.4V3.5h1.2v2.9Z" fill="currentColor"/></template>
                                                    <template x-if="m.status !== 'failed' && (m.status === 'delivered' || m.status === 'read')">
                                                        <path d="M2 6.3 4.4 8.7 9.2 2.9M6.4 8.7 11.2 2.9M11 6.3 13.4 8.7 18 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </template>
                                                    <template x-if="m.status !== 'failed' && m.status !== 'delivered' && m.status !== 'read'">
                                                        <path d="M4 6.3 6.4 8.7 12 2.9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </template>
                                                </svg>
                                            </template>
                                        </span>
                                    </div>
                                    {{-- Under outgoing messages: who replied + (on the last one) Seen/Delivered status --}}
                                    <template x-if="m.direction === 'out' && (m.agent || isLastOut(i))">
                                        <span class="mr-1 mt-0.5 text-[10px] font-medium text-gray-400">
                                            <span x-show="m.agent" x-text="m.agent"></span>
                                            <template x-if="isLastOut(i)">
                                                <span :class="m.status === 'read' ? 'text-[#53bdeb]' : 'text-gray-400'"
                                                      x-text="(m.agent ? ' · ' : '') + (m.status === 'read' ? 'Seen' : (m.status === 'delivered' ? 'Delivered' : (m.status === 'failed' ? 'Failed' : 'Sent')))"></span>
                                            </template>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </template>
                        </div>
                    </div>

                    {{-- Composer — WhatsApp-style pill, smooth auto-grow --}}
                    @if ($canReply)
                        <div class="shrink-0 border-t border-gray-100 px-4 py-3" style="background:#f0f2f5;">
                            <div class="mb-2 flex flex-wrap gap-1.5" x-show="showQuick" x-cloak>
                                @foreach ($quickReplies as $qr)
                                    <button type="button" @click="draft = @js($qr->body); showQuick = false; $nextTick(() => autoGrow())" class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-600 shadow-sm hover:bg-gray-50">{{ $qr->shortcut ?: \Illuminate\Support\Str::limit($qr->body, 20) }}</button>
                                @endforeach
                            </div>
                            <form @submit.prevent="send()" class="flex items-end gap-2">
                                <button type="button" @click="showQuick = !showQuick" class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-gray-500 transition hover:bg-gray-200" title="Quick replies">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 2 3 14h7l-1 8 10-12h-7l1-8Z"/></svg>
                                </button>
                                <textarea x-ref="composer" x-model="draft" @keydown.enter="if (!$event.shiftKey && !$event.isComposing) { $event.preventDefault(); send(); }" @input="autoGrow()" rows="1" placeholder="Type a message… (Enter to send, Shift+Enter for a new line)"
                                          class="max-h-40 min-h-[2.75rem] flex-1 resize-none rounded-3xl border-0 bg-white px-4 py-3 text-sm leading-5 text-gray-800 shadow-sm outline-none ring-1 ring-gray-200 transition focus:ring-2 focus:ring-emerald-400"></textarea>
                                <button type="submit" :disabled="!draft.trim() || sending" class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-emerald-500 text-white shadow-sm transition hover:bg-emerald-600 disabled:opacity-50">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4 20-7Z"/></svg>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </template>
        </section>

        {{-- ============ RIGHT: contact details (toggle on any screen) ============ --}}
        <aside class="flex w-80 shrink-0 flex-col overflow-hidden border-l border-gray-100 bg-gray-50/60" x-show="active && showInfo" x-cloak>
            <template x-if="active">
                <div class="flex min-h-0 flex-1 flex-col">
                    {{-- Profile header (pinned) --}}
                    <div class="relative shrink-0 bg-gradient-to-b from-emerald-50 to-gray-50/60 px-5 pb-5 pt-3">
                        <button type="button" @click="showInfo = false" class="absolute right-3 top-3 grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-white hover:text-[var(--color-heading)]" title="Close">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                        <div class="text-center">
                            <div class="relative mx-auto h-20 w-20">
                                {{-- avatar: uploaded image, else initials / group icon --}}
                                <template x-if="active.avatar">
                                    <img :src="active.avatar" class="h-20 w-20 rounded-full object-cover shadow-sm ring-4 ring-white">
                                </template>
                                <template x-if="!active.avatar">
                                    <span class="grid h-20 w-20 place-items-center rounded-full text-2xl font-bold shadow-sm ring-4 ring-white" :class="active.is_group ? 'text-white' : 'bg-emerald-100 text-emerald-700'" :style="active.is_group ? ('background:' + active.color) : ''">
                                        <template x-if="active.is_group"><svg class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 0 0-3-3.87M9 20H4v-1a4 4 0 0 1 3-3.87m0 0a4 4 0 1 1 5.9 0M17 11a3 3 0 1 0-2.5-4.5"/></svg></template>
                                        <span x-show="!active.is_group" x-text="active.initials"></span>
                                    </span>
                                </template>
                                {{-- upload / change photo --}}
                                <button type="button" @click="$refs.avatarInput.click()" :disabled="uploadingAvatar"
                                        class="absolute -bottom-0.5 -right-0.5 grid h-7 w-7 place-items-center rounded-full bg-emerald-500 text-white shadow ring-2 ring-white transition hover:bg-emerald-600 disabled:opacity-60" :title="active.avatar ? 'Change photo' : 'Upload photo'">
                                    <svg x-show="!uploadingAvatar" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 8h.01M4 16l4-4 3 3 5-5 4 4M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg>
                                    <svg x-show="uploadingAvatar" x-cloak class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/></svg>
                                </button>
                                <input type="file" x-ref="avatarInput" accept="image/*" class="hidden" @change="uploadAvatar($event)">
                            </div>
                            <p class="mt-3 text-base font-bold text-[var(--color-heading)]" x-text="active.name"></p>
                            <p class="text-xs text-gray-400" x-text="active.phone || active.wa_id"></p>
                            {{-- lead quality pill --}}
                            <template x-if="active.lead_quality">
                                <span class="mt-2 inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                                      :class="active.lead_quality === 'qualified' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600'">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="active.lead_quality === 'qualified' ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                                    <span x-text="active.lead_quality === 'qualified' ? 'Qualified' : 'Unqualified'"></span>
                                </span>
                            </template>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                        {{-- Contact details --}}
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                            <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-gray-400">Contact</p>
                            <dl class="space-y-3 text-sm">
                                <div class="flex items-start gap-2.5">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.6 10.8a15 15 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11 11 0 0 0 3.6.58 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11 11 0 0 0 .57 3.6 1 1 0 0 1-.25 1L6.6 10.8Z"/></svg>
                                    <div class="min-w-0">
                                        <dt class="text-[10px] uppercase tracking-wide text-gray-400" x-text="active.phone ? 'Phone' : 'WhatsApp ID'"></dt>
                                        <dd class="break-all font-medium text-[var(--color-heading)]" x-text="active.phone || active.wa_id"></dd>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2.5" x-show="active.country">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                                    <div>
                                        <dt class="text-[10px] uppercase tracking-wide text-gray-400">Country</dt>
                                        <dd class="font-medium text-[var(--color-heading)]"><span x-text="active.country?.flag"></span> <span x-text="active.country?.name"></span></dd>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4l3 2"/></svg>
                                    <div>
                                        <dt class="text-[10px] uppercase tracking-wide text-gray-400">Last message</dt>
                                        <dd class="font-medium text-[var(--color-heading)]" x-text="active.at || '—'"></dd>
                                    </div>
                                </div>
                            </dl>
                        </div>

                        {{-- Lead info (editable) --}}
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                            <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-gray-400">Lead info</p>
                            <div class="space-y-3">
                                {{-- Client name --}}
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-500">Name</label>
                                    <input type="text" x-model="form.name" @keydown.enter.prevent="saveDetails()" placeholder="Contact name"
                                           class="h-9 w-full rounded-lg border-gray-200 text-sm focus:border-emerald-400 focus:ring-emerald-400">
                                </div>
                                {{-- Manual phone number --}}
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-500">Phone number <span class="text-gray-300">(add manually)</span></label>
                                    <input type="text" x-model="form.phone" @keydown.enter.prevent="saveDetails()" placeholder="+880 1XXX-XXXXXX"
                                           class="h-9 w-full rounded-lg border-gray-200 text-sm focus:border-emerald-400 focus:ring-emerald-400">
                                </div>
                                {{-- Lead quality --}}
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-500">Lead quality</label>
                                    <select x-model="form.lead_quality" class="h-9 w-full rounded-lg border-gray-200 text-sm focus:border-emerald-400 focus:ring-emerald-400">
                                        <option value="">— Not set —</option>
                                        @foreach ($leadQualities as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                                    </select>
                                </div>
                                {{-- Interested product --}}
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-500">Interested in</label>
                                    <select x-model="form.interested_product" class="h-9 w-full rounded-lg border-gray-200 text-sm focus:border-emerald-400 focus:ring-emerald-400">
                                        <option value="">— Select a product —</option>
                                        <template x-if="form.interested_product && !{{ \Illuminate\Support\Js::from($interestOptions) }}.includes(form.interested_product)">
                                            <option :value="form.interested_product" x-text="form.interested_product"></option>
                                        </template>
                                        @foreach ($interestOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                                    </select>
                                    <p class="mt-1 text-[10px] text-gray-300">Manage options in Settings › WhatsApp Config.</p>
                                </div>
                                <button type="button" @click="saveDetails()" :disabled="savingDetails"
                                        class="w-full rounded-lg bg-emerald-500 py-2 text-xs font-semibold text-white transition hover:bg-emerald-600 disabled:opacity-50">
                                    <span x-show="!savingDetails">Save details</span>
                                    <span x-show="savingDetails" x-cloak>Saving…</span>
                                </button>
                            </div>
                        </div>

                        {{-- Client match --}}
                        <template x-if="active.client">
                            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 text-xs shadow-sm">
                                <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-emerald-700">Matched client</p>
                                <p class="text-sm font-semibold text-[var(--color-heading)]" x-text="active.client.name"></p>
                                <p class="mt-0.5 text-gray-500" x-text="active.client.email"></p>
                                <p class="text-gray-500" x-show="active.client.phone" x-text="active.client.phone"></p>
                                <p class="text-gray-500" x-show="active.client.company" x-text="active.client.company"></p>
                            </div>
                        </template>

                        {{-- Labels --}}
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                            <p class="mb-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-400">Labels</p>
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
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                            <p class="mb-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-400">Internal notes</p>
                            <form @submit.prevent="addNote()" class="mb-3">
                                <textarea x-model="noteDraft" rows="2" placeholder="Add a private note…" class="w-full rounded-lg border-gray-200 text-xs focus:border-emerald-400 focus:ring-emerald-400"></textarea>
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
                </div>
            </template>
        </aside>
    </div>

    <style>
        /* WhatsApp Web thread: warm beige base + a faint doodle texture. */
        .wa-thread {
            background-color: #efeae2;
            background-image:
                radial-gradient(rgba(0,0,0,0.035) 1px, transparent 1px),
                radial-gradient(rgba(0,0,0,0.025) 1px, transparent 1px);
            background-size: 26px 26px, 26px 26px;
            background-position: 0 0, 13px 13px;
        }
        /* Bubble colours + tails, WhatsApp-style (inline so it never depends on a Tailwind rebuild). */
        .wa-thread .wa-in, .wa-thread .wa-out { border-radius: 8px; }
        .wa-thread .wa-in { background: #ffffff; border-top-left-radius: 0; }
        .wa-thread .wa-out { background: #e7ffdb; border-top-right-radius: 0; }
        .wa-thread .wa-in::before,
        .wa-thread .wa-out::before {
            content: ''; position: absolute; top: 0; width: 8px; height: 12px;
        }
        .wa-thread .wa-in::before {
            left: -8px;
            background: radial-gradient(circle at bottom left, transparent 12px, #fff 0);
        }
        .wa-thread .wa-out::before {
            right: -8px;
            background: radial-gradient(circle at bottom right, transparent 12px, #e7ffdb 0);
        }
    </style>

    <script>
        function waInbox() {
            return {
                chats: [], active: null, messages: [], draft: '', noteDraft: '', sending: false, showQuick: false,
                showInfo: window.innerWidth >= 1280, search: '', filter: 'all',
                form: { name: '', phone: '', lead_quality: '', interested_product: '' }, savingDetails: false, uploadingAvatar: false, _chatReq: 0,
                filters: [
                    { key: 'all', label: 'All' }, { key: 'unread', label: 'Unread' }, { key: 'single', label: 'Single' },
                    { key: 'group', label: 'Group' }, { key: 'open', label: 'Open' },
                    { key: 'pending', label: 'Pending' }, { key: 'mine', label: 'Mine' }, { key: 'resolved', label: 'Resolved' },
                ],
                csrf: document.querySelector('meta[name=csrf-token]').content,
                showDate(i) { return i === 0 || this.messages[i - 1].date_key !== this.messages[i].date_key; },
                // True only for the newest outgoing message — where WhatsApp shows the Seen/Delivered caption.
                isLastOut(i) {
                    for (let j = this.messages.length - 1; j >= 0; j--) {
                        if (this.messages[j].direction === 'out') return j === i;
                    }
                    return false;
                },
                dayLabel(m) { return m.day; },
                // Smooth grow of the composer + reset to one row after send.
                autoGrow() {
                    const el = this.$refs.composer;
                    if (!el) return;
                    el.style.height = 'auto';
                    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
                },
                scrollBottom(smooth = false) {
                    const jump = () => {
                        const t = this.$refs.thread;
                        if (t) t.scrollTo({ top: t.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
                    };
                    // Land at the newest message; retry as late media/images grow the thread height.
                    this.$nextTick(() => { jump(); setTimeout(jump, 60); setTimeout(jump, 250); });
                },
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
                    else if (this.filter === 'single' || this.filter === 'group') p.set('type', this.filter);
                    else if (this.filter !== 'all') p.set('status', this.filter);
                    return p.toString();
                },
                setFilter(k) { this.filter = k; this.loadChats(); },
                async loadChats() {
                    const token = ++this._chatReq;
                    const r = await fetch(@js(route('admin.whatsapp.chats')) + '?' + this.params());
                    const data = await r.json();
                    if (token !== this._chatReq) return; // a newer search superseded this response
                    this.chats = data.chats;
                },
                async openChat(id, silent = false) {
                    const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + id);
                    const d = await r.json();
                    const atBottom = silent ? this.isAtBottom() : true;
                    this.active = d.chat; this.messages = d.messages;
                    // Seed the editable lead form (strip the leading + so the input holds plain digits).
                    this.form = {
                        name: d.chat.raw_name || '',
                        phone: (d.chat.phone || '').replace(/^\+/, ''),
                        lead_quality: d.chat.lead_quality || '',
                        interested_product: d.chat.interested_product || '',
                    };
                    if (!silent) { const c = this.chats.find(x => x.id === id); if (c) c.unread = 0; }
                    // Always land at the newest message when opening; on live refresh only if already at bottom.
                    if (atBottom) this.scrollBottom();
                },
                isAtBottom() {
                    const t = this.$refs.thread;
                    return !t || (t.scrollHeight - t.scrollTop - t.clientHeight < 80);
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
                        if (r.ok) { this.messages.push((await r.json()).message); this.scrollBottom(true); this.loadChats(); this.$nextTick(() => this.autoGrow()); }
                        else { alert((await r.json()).error || 'Could not send.'); this.draft = body; }
                    } catch { this.draft = body; } finally { this.sending = false; }
                },
                async post(url, data) {
                    return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(data) });
                },
                assign(id) { this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/assign', { assigned_to: id || null }); this.active.assigned_to = id; },
                setStatus(s) { this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/status', { status: s }); this.active.status = s; this.loadChats(); },
                async markUnread() {
                    if (!this.active) return;
                    const id = this.active.id;
                    await this.post(@js(url('admin/whatsapp/chats')) + '/' + id + '/unread', {});
                    this.active = null;        // close the thread so its unread state is visible in the list
                    this.loadChats();
                },
                async toggleLabel(id) {
                    const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/label', { label_id: id });
                    if (r.ok) { this.active.label_ids = (await r.json()).labels.map(l => l.id); this.loadChats(); }
                },
                async saveDetails() {
                    if (this.savingDetails || !this.active) return;
                    this.savingDetails = true;
                    try {
                        const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/details', {
                            name: this.form.name, phone: this.form.phone, lead_quality: this.form.lead_quality, interested_product: this.form.interested_product,
                        });
                        if (r.ok) {
                            const d = await r.json();
                            this.active.name = d.name; this.active.initials = d.initials;
                            this.active.phone = d.phone; this.active.country = d.country;
                            this.active.lead_quality = d.lead_quality; this.active.interested_product = d.interested_product;
                            this.loadChats();
                        } else { alert((await r.json()).message || 'Could not save.'); }
                    } catch { alert('Could not save.'); } finally { this.savingDetails = false; }
                },
                async uploadAvatar(e) {
                    const file = e.target.files[0];
                    if (!file || !this.active) return;
                    this.uploadingAvatar = true;
                    const fd = new FormData();
                    fd.append('avatar', file);
                    try {
                        const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/avatar', {
                            method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' }, body: fd,
                        });
                        if (r.ok) { this.active.avatar = (await r.json()).avatar; this.loadChats(); }
                        else { alert((await r.json()).message || 'Could not upload the photo.'); }
                    } catch { alert('Could not upload the photo.'); }
                    finally { this.uploadingAvatar = false; e.target.value = ''; }
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
