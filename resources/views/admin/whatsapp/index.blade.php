@extends('admin.layouts.app')
@section('title', 'WhatsApp Inbox')

@php
    $canReply = auth()->user()->allows('whatsapp', 'reply');
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
    <div x-data="waInbox()" x-init="init(); lockScroll = true" style="height:calc(100dvh - 4rem); margin:-1.5rem;" class="flex overflow-hidden bg-white">
        {{-- ============ LEFT: chat list ============ --}}
        <aside class="flex w-80 shrink-0 flex-col border-r border-gray-100">
            <div class="border-b border-gray-100 p-4">
                <div class="flex items-center justify-between">
                    {{-- Account (number) switcher dropdown --}}
                    <div class="relative min-w-0">
                        <button type="button" @click="accMenu = !accMenu" class="flex min-w-0 items-center gap-1.5 text-left">
                            <span class="min-w-0">
                                <span class="flex items-center gap-1 text-base font-bold text-[var(--color-heading)]">
                                    <span class="truncate" x-text="currentAccount().name || 'WhatsApp'"></span>
                                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="accMenu ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                                    {{-- unread on OTHER numbers --}}
                                    <span x-show="otherUnread()" class="grid h-4 min-w-4 place-items-center rounded-full bg-emerald-500 px-1 text-[9px] font-bold text-white" x-text="otherUnread()" title="Unread on other numbers"></span>
                                </span>
                                <span class="block truncate text-[10px] text-gray-400" x-text="currentAccount().number ? ('+' + currentAccount().number) : 'not connected'"></span>
                            </span>
                        </button>
                        <div x-show="accMenu" x-cloak @click.outside="accMenu = false" class="absolute left-0 top-11 z-40 w-64 overflow-hidden rounded-xl border border-gray-100 bg-white p-1 shadow-xl">
                            <p class="px-3 pb-1 pt-2 text-[10px] font-bold uppercase tracking-wide text-gray-400">WhatsApp numbers</p>
                            {{-- Drag a number to reorder it (saved per employee). Each row has its own Sync icon. --}}
                            <template x-for="a in accountsList" :key="a.id">
                                <div draggable="true" @dragstart="dragId = a.id" @dragover.prevent @drop.prevent="dropOnNumber(a.id)" @dragend="dragId = null"
                                     class="group flex items-center gap-1.5 rounded-lg px-2 py-2 transition hover:bg-gray-50"
                                     :class="[accountId === a.id ? 'bg-emerald-50' : '', dragId === a.id ? 'opacity-40' : '']">
                                    <svg class="h-4 w-4 shrink-0 cursor-grab text-gray-300 group-hover:text-gray-400" fill="currentColor" viewBox="0 0 24 24"><circle cx="9" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg>
                                    <button type="button" @click="switchAccount(a.id)" class="flex min-w-0 flex-1 items-center gap-2.5 text-left">
                                        <span class="h-2 w-2 shrink-0 rounded-full" :class="a.connected ? 'bg-emerald-500' : 'bg-gray-300'"></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-semibold text-[var(--color-heading)]" x-text="a.name"></span>
                                            <span class="block truncate text-[10px] text-gray-400" x-text="a.number ? ('+' + a.number) : 'not connected'"></span>
                                        </span>
                                        <span x-show="a.unread" class="grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-emerald-500 px-1.5 text-[10px] font-bold text-white" x-text="a.unread"></span>
                                    </button>
                                    @if ($canReply)
                                        <button type="button" @click.stop="syncAccount(a.id)" :disabled="syncingId === a.id" title="Sync this number from the phone" class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-gray-400 transition hover:bg-emerald-50 hover:text-emerald-600 disabled:opacity-60">
                                            <svg class="h-4 w-4" :class="syncingId === a.id ? 'wa-spin' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M20 8a8 8 0 0 0-14.9-2M4 16a8 8 0 0 0 14.9 2"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </template>
                            @if (auth()->user()->hasPermission('whatsapp.settings') || auth()->user()->isAdmin())
                                <a href="{{ route('admin.whatsapp-settings') }}" class="flex items-center gap-2 border-t border-gray-100 px-3 py-2 text-xs font-semibold text-emerald-600 hover:bg-emerald-50">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Manage numbers
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        {{-- Label filter. Several at once, matching any of them: a chat carries
                             one label, so requiring all of them would return nothing. --}}
                        <div class="relative" @click.outside="labelMenu = false">
                            <button type="button" @click="labelMenu = !labelMenu"
                                    class="flex h-8 items-center gap-1.5 rounded-full px-2.5 text-[11px] font-bold transition"
                                    :class="pickedLabels.length ? 'bg-[var(--color-primary-soft)] text-[var(--color-primary)]' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">
                                <svg x-show="!pickedLabels.length" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v5.6a2 2 0 0 0 .6 1.4l7 7a2 2 0 0 0 2.8 0l5.6-5.6a2 2 0 0 0 0-2.8l-7-7A2 2 0 0 0 10.6 5H5a2 2 0 0 0-2 2Z"/><circle cx="7.5" cy="9.5" r="1.2" fill="currentColor" stroke="none"/></svg>
                                <span x-show="pickedLabels.length" x-cloak class="flex items-center gap-1">
                                    <template x-for="l in pickedLabelObjects.slice(0, 3)" :key="l.id">
                                        <span class="h-2 w-2 rounded-full" :style="`background:${l.color}`"></span>
                                    </template>
                                </span>
                                <span class="truncate" style="max-width:7rem" x-text="labelPillText()"></span>
                            </button>
                            <div x-show="labelMenu" x-cloak class="absolute right-0 z-30 mt-1 w-52 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-xl">
                                <template x-if="!labels.length">
                                    <p class="px-3 py-2 text-xs text-gray-300">No labels set up yet.</p>
                                </template>
                                <template x-for="l in labels" :key="l.id">
                                    <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50">
                                        <input type="checkbox" :checked="pickedLabels.includes(l.id)" @change="toggleLabelFilter(l.id)" class="accent-[var(--color-primary)]">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background:${l.color}`"></span>
                                        <span class="min-w-0 flex-1 truncate" :class="(labelCounts[l.id] || 0) ? 'text-[var(--color-heading)]' : 'text-gray-400'" x-text="l.name"></span>
                                        <span class="shrink-0 text-xs text-gray-400" style="font-variant-numeric:tabular-nums" x-text="labelCounts[l.id] || 0"></span>
                                    </label>
                                </template>
                                <button type="button" x-show="pickedLabels.length" @click="pickedLabels = []; loadChats()"
                                        class="w-full border-t border-gray-100 px-3 py-2 text-left text-xs font-semibold text-gray-500 hover:bg-gray-50">
                                    Clear
                                </button>
                            </div>
                        </div>
                        @if ($canReply)
                            <button type="button" @click="newChat.open = true; newChat.number = ''; newChat.error = ''" title="New chat" class="grid h-8 w-8 place-items-center rounded-full bg-emerald-500 text-white transition hover:bg-emerald-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                            </button>
                        @endif
                    </div>
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
                                :class="filter === f.key ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                x-text="f.key === 'unread' && unreadCount ? f.label + ' (' + unreadCount + ')' : f.label"></button>
                    </template>
                </div>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <template x-if="!chats.length">
                    <p class="py-10 text-center text-sm text-gray-300">No conversations.</p>
                </template>
                <template x-for="c in chats" :key="c.id">
                    <button type="button" @click="openChat(c.id)" @contextmenu.prevent="openChatMenu($event, c)"
                            class="flex w-full items-start gap-3 border-b border-gray-50 px-4 py-3 text-left transition hover:bg-gray-50"
                            :class="active && active.id === c.id ? 'bg-[var(--color-primary-soft)]' : ''">
                        <template x-if="c.avatar"><img :src="c.avatar" loading="lazy" decoding="async" class="h-10 w-10 shrink-0 rounded-full object-cover"></template>
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
                                <span class="flex shrink-0 items-center gap-1">
                                    <svg x-show="c.pinned" x-cloak class="h-3 w-3 text-gray-400" fill="currentColor" viewBox="0 0 24 24" title="Pinned"><path d="M16 3v2l1 1v4l2 2v2h-6v5l-1 2-1-2v-5H5v-2l2-2V6l1-1V3h8Z"/></svg>
                                    <span class="text-[10px] text-gray-400" x-text="c.at"></span>
                                </span>
                            </span>
                            <span class="mt-0.5 flex items-center gap-1.5">
                                <span class="truncate text-xs text-gray-500" x-text="plainPreview(c.preview) || '—'"></span>
                                <span x-show="c.unread" class="ml-auto grid h-4 min-w-4 shrink-0 place-items-center rounded-full bg-emerald-500 px-1 text-[10px] font-bold text-white" x-text="c.unread"></span>
                            </span>
                            <span class="mt-1 flex flex-wrap gap-1">
                                <template x-for="l in c.labels" :key="l.id">
                                    <span class="rounded px-1.5 py-0.5 text-[9px] font-bold" :style="`background:${l.color}1a;color:${l.color}`" x-text="l.name"></span>
                                </template>
                            </span>
                        </span>
                    </button>
                </template>
            </div>
        </aside>

        {{-- Right-click menu on a chat row. Fixed to the pointer and outside the scrolling
             list, or the panel's own overflow would cut it off near the bottom. --}}
        <div x-show="chatMenu.open" x-cloak>
            <div class="fixed inset-0 z-50" @click="chatMenu.open = false" @contextmenu.prevent="chatMenu.open = false"></div>
            <div x-ref="chatMenu" :style="`position:fixed; top:${chatMenu.y}px; left:${chatMenu.x}px`"
                 class="w-52 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 text-left shadow-xl" style="z-index:60">
                <button type="button" @click="menuPin()" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 3v2l1 1v4l2 2v2h-6v5l-1 2-1-2v-5H5v-2l2-2V6l1-1V3h8Z"/></svg>
                    <span x-text="chatMenu.chat?.pinned ? 'Unpin chat' : 'Pin chat'"></span>
                </button>
                <button type="button" @click="menuUnread()" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M3 6h18v12H3z"/></svg>
                    Mark as unread
                </button>

                <div class="border-t border-gray-100"></div>
                <button type="button" @click="menuBlock()" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm hover:bg-red-50"
                        :class="chatMenu.chat?.blocked ? 'text-[var(--color-heading)]' : 'text-red-600'">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="m5.6 5.6 12.8 12.8"/></svg>
                    <span x-text="chatMenu.chat?.blocked ? 'Unblock chat' : 'Block'"></span>
                </button>
            </div>
        </div>

        {{-- Full-screen photo viewer (WhatsApp-style lightbox) --}}
        <div x-show="lightbox.open" x-cloak
             @keydown.window.escape="closeLightbox()" @keydown.window.arrow-left="lightboxPrev()" @keydown.window.arrow-right="lightboxNext()"
             class="fixed inset-0 flex items-center justify-center" style="z-index:60;background:rgba(0,0,0,.92)">
            {{-- top bar --}}
            <div class="absolute left-0 right-0 top-0 flex items-center justify-between px-5 py-4 text-white/90">
                <span class="text-sm font-medium" x-text="lightbox.items.length ? (lightbox.index + 1) + ' / ' + lightbox.items.length : ''"></span>
                <div class="flex items-center gap-2">
                    <a :href="lightbox.items[lightbox.index]?.media" :download="lightbox.items[lightbox.index]?.name || 'photo.jpg'" @click.stop class="grid h-10 w-10 place-items-center rounded-full hover:bg-white/15" title="Download">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 19h16"/></svg>
                    </a>
                    <button type="button" @click="closeLightbox()" class="grid h-10 w-10 place-items-center rounded-full hover:bg-white/15" title="Close">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
            </div>
            {{-- prev / next --}}
            <button type="button" x-show="lightbox.items.length > 1" @click.stop="lightboxPrev()" class="absolute left-3 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20 disabled:opacity-30" :disabled="lightbox.index === 0">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg>
            </button>
            <button type="button" x-show="lightbox.items.length > 1" @click.stop="lightboxNext()" class="absolute right-3 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20 disabled:opacity-30" :disabled="lightbox.index === lightbox.items.length - 1">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 18 6-6-6-6"/></svg>
            </button>
            {{-- image (click backdrop to close) --}}
            <div class="flex h-full w-full items-center justify-center p-6" @click.self="closeLightbox()"
                 @touchstart="lbTouch = $event.changedTouches[0].clientX" @touchend="lbSwipe($event.changedTouches[0].clientX)">
                <img :src="lightbox.items[lightbox.index]?.media" @click.stop class="rounded-lg object-contain shadow-2xl" style="max-height:86vh;max-width:92vw">
            </div>
        </div>

        {{-- New chat modal --}}
        <div x-show="newChat.open" x-cloak @keydown.escape.window="newChat.open = false" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.4)">
            <div @click.outside="newChat.open = false" class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-[var(--color-heading)]">New chat</h3>
                    <button type="button" @click="newChat.open = false" class="grid h-7 w-7 place-items-center rounded-lg text-gray-400 hover:bg-gray-100"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                </div>
                <p class="mb-2 text-xs text-gray-400">Enter the phone number with country code.</p>
                <input type="text" x-model="newChat.number" @keydown.enter="startNewChat()" placeholder="+880 1XXX-XXXXXX"
                       class="h-11 w-full rounded-lg border-gray-200 text-sm focus:border-emerald-400 focus:ring-emerald-400">
                <p x-show="newChat.error" x-cloak class="mt-1.5 text-xs font-medium text-red-500" x-text="newChat.error"></p>
                <button type="button" @click="startNewChat()" :disabled="newChat.busy || !newChat.number.trim()"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-500 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-600 disabled:opacity-50">
                    <span x-show="!newChat.busy">Start chat</span>
                    <span x-show="newChat.busy" x-cloak>Checking…</span>
                </button>
            </div>
        </div>

        {{-- ============ MIDDLE: thread ============ --}}
        <section class="relative flex min-w-0 flex-1 flex-col"
                 @dragover.prevent="if (active && {{ $canReply ? 'true' : 'false' }}) dragOver = true">
            @if ($canReply)
                {{-- Drag-and-drop overlay --}}
                <div x-show="dragOver" x-cloak @dragover.prevent @dragleave.prevent="dragOver = false" @drop.prevent="onDrop($event)"
                     class="absolute inset-0 z-40 m-3 flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-emerald-400 bg-emerald-50/95 text-emerald-700">
                    <svg class="h-12 w-12" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0 4 4m-4-4-4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                    <p class="mt-2 text-sm font-bold">Drop the file to send</p>
                    <p class="text-xs text-emerald-600/80">Photos, videos, documents</p>
                </div>

                {{-- File preview + caption (WhatsApp-style) --}}
                <template x-if="pending">
                <div @keydown.window.escape="cancelPending()" class="absolute inset-0 z-50 flex flex-col" style="background:#efeae2">
                    <div class="flex items-center gap-3 bg-white px-5 py-3 shadow-sm">
                        <button type="button" @click="cancelPending()" class="grid h-9 w-9 place-items-center rounded-lg text-gray-500 hover:bg-gray-100"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                        <span class="truncate text-sm font-semibold text-[var(--color-heading)]" x-text="pending?.name"></span>
                    </div>
                    <div class="flex min-h-0 flex-1 items-center justify-center p-6">
                        <template x-if="pending?.type === 'image'"><img :src="pending?.previewUrl" class="rounded-lg object-contain shadow-lg" style="max-height:70vh;max-width:90%"></template>
                        <template x-if="pending?.type === 'video'"><video :src="pending?.previewUrl" controls class="rounded-lg shadow-lg" style="max-height:70vh;max-width:90%"></video></template>
                        <template x-if="pending?.type === 'file'">
                            <div class="flex flex-col items-center gap-3 rounded-2xl bg-white p-10 shadow">
                                <svg class="h-16 w-16 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/></svg>
                                <span class="max-w-xs truncate text-sm font-medium text-gray-600" x-text="pending?.name"></span>
                            </div>
                        </template>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <div class="flex items-end gap-2">
                            <input type="text" x-model="pending.caption" @keydown.enter="sendPending()" placeholder="Add a caption…" class="h-11 flex-1 rounded-full border-0 bg-[#f0f2f5] px-4 text-sm text-gray-800 outline-none ring-1 ring-gray-200 focus:ring-2 focus:ring-emerald-400">
                            <button type="button" @click="sendPending()" :disabled="sending" class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-emerald-500 text-white shadow-sm transition hover:bg-emerald-600 disabled:opacity-50">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4 20-7Z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                </template>
            @endif
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
                            <select @change="setStatus($event.target.value)" class="h-9 rounded-lg border-gray-200 text-xs">
                                @foreach (\App\Models\WhatsappChat::STATUSES as $k => $v)<option value="{{ $k }}" :selected="active.status === '{{ $k }}'">{{ $v }}</option>@endforeach
                            </select>
                            {{-- Navigation drawer toggle for the contact info panel (hidden by default) --}}
                            <button type="button" @click="showInfo = !showInfo" class="grid h-9 w-9 place-items-center rounded-lg transition hover:bg-gray-100"
                                    :class="showInfo ? 'bg-emerald-50 text-emerald-600' : 'text-gray-400 hover:text-[var(--color-heading)]'" title="Contact details">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M15 4v16"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Messages — WhatsApp Web look (beige doodle bg, green/white bubbles) --}}
                    {{-- flex-col + mt-auto anchors messages to the bottom (empty space stays on top, like WhatsApp). --}}
                    <div class="wa-thread flex flex-1 flex-col overflow-y-auto px-6 py-6 sm:px-16" x-ref="thread"
                         @scroll.debounce.150ms="if ($refs.thread.scrollTop < 80 && hasMore && !loadingOlder) loadOlder()">
                        <div class="space-y-3" style="margin-top:auto">
                        {{-- Load earlier messages (WhatsApp/Facebook style) --}}
                        <template x-if="hasMore">
                            <div class="flex justify-center pb-1">
                                <button type="button" @click="loadOlder()" :disabled="loadingOlder" class="rounded-full bg-white/90 px-4 py-1.5 text-xs font-semibold text-gray-600 shadow-sm transition hover:bg-white disabled:opacity-60">
                                    <span x-show="!loadingOlder">Load earlier messages</span>
                                    <span x-show="loadingOlder" x-cloak>Loading…</span>
                                </button>
                            </div>
                        </template>
                        <template x-for="(m, i) in messages" :key="m.id">
                            <div>
                                {{-- Date separator pill --}}
                                <template x-if="showDate(i)">
                                    <div class="mb-4 mt-2 flex justify-center">
                                        <span class="rounded-lg bg-white/90 px-3 py-1 text-[11px] font-medium uppercase tracking-wide text-gray-500 shadow-sm" x-text="dayLabel(m)"></span>
                                    </div>
                                </template>
                                <div class="flex flex-col" :class="m.direction === 'out' ? 'items-end' : 'items-start'">
                                    <div class="group relative rounded-lg px-3.5 pb-2 pt-2 text-sm shadow-[0_1px_0.5px_rgba(0,0,0,0.13)]" style="max-width:72%;"
                                         :class="m.direction === 'out' ? 'wa-out text-gray-800' : 'wa-in text-gray-800'"
                                         x-data="{ react: false, more: false }">
                                        @if ($canReply)
                                        {{-- Hover actions (react / edit / delete) — absolutely placed so they never shift the bubble --}}
                                        <div class="absolute flex items-center gap-1 opacity-0 transition group-hover:opacity-80"
                                             :style="(m.direction === 'out' ? 'right:100%;padding-right:.4rem;' : 'left:100%;padding-left:.4rem;') + 'top:50%;transform:translateY(-50%);z-index:20'"
                                             x-show="!m.deleted && editingId !== m.id">
                                            @if ($canReply)
                                            <button type="button" @click="replyTo = m; $nextTick(() => $refs.composer && $refs.composer.focus())" title="Reply" class="grid h-7 w-7 place-items-center rounded-full border border-gray-200 bg-white text-gray-500 shadow-sm hover:text-emerald-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14 4 9l5-5M4 9h11a5 5 0 0 1 5 5v3"/></svg>
                                            </button>
                                            @endif
                                            <div class="relative">
                                                <button type="button" @click="react = !react; more = false" title="React" class="grid h-7 w-7 place-items-center rounded-full border border-gray-200 bg-white text-gray-500 shadow-sm hover:text-emerald-600">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M9 10h.01M15 10h.01M8.5 14.5c.9.9 2.1 1.5 3.5 1.5s2.6-.6 3.5-1.5"/></svg>
                                                </button>
                                                {{-- quick reaction row --}}
                                                <div x-show="react" x-cloak @click.outside="react = false; more = false" class="absolute flex items-center gap-1 rounded-full border border-gray-100 bg-white px-2 py-1 shadow-lg" :style="(m.direction === 'out' ? 'right:0;' : 'left:0;') + 'bottom:2.4rem;z-index:30'">
                                                    <template x-for="e in quickEmojis" :key="e">
                                                        <button type="button" @click="sendReaction(m, e); react = false" class="rounded-full px-0.5 text-lg leading-none transition hover:scale-125" :class="m.my_reaction === e ? 'bg-emerald-100' : ''" x-text="e"></button>
                                                    </template>
                                                    <button type="button" @click="more = !more" title="More" class="grid h-6 w-6 place-items-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                                    </button>
                                                </div>
                                                {{-- full emoji grid (more) --}}
                                                <div x-show="more" x-cloak @click.outside="more = false" class="absolute rounded-xl border border-gray-100 bg-white p-2 shadow-xl"
                                                     :style="'width:16rem;max-height:14rem;overflow-y:auto;display:grid;grid-template-columns:repeat(8,1fr);gap:.15rem;bottom:2.4rem;z-index:40;' + (m.direction === 'out' ? 'right:0' : 'left:0')">
                                                    <template x-for="e in emojiList" :key="e">
                                                        <button type="button" @click="sendReaction(m, e); react = false; more = false" class="rounded p-0.5 text-lg leading-none transition hover:scale-125 hover:bg-gray-50" x-text="e"></button>
                                                    </template>
                                                </div>
                                            </div>
                                            <template x-if="m.direction === 'out' && m.type === 'text'">
                                                <button type="button" @click="canModify(m) ? startEdit(m) : null"
                                                        :disabled="!canModify(m)"
                                                        :title="canModify(m) ? 'Edit' : modifyHint('editing')"
                                                        :class="canModify(m) ? 'text-gray-500 hover:text-gray-700' : 'cursor-not-allowed text-gray-300'"
                                                        class="grid h-7 w-7 place-items-center rounded-full border border-gray-200 bg-white shadow-sm">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                                                </button>
                                            </template>
                                            <template x-if="m.direction === 'out' && !m.deleted">
                                                <button type="button" @click="canDelete(m) ? deleteMsg(m) : null"
                                                        :disabled="!canDelete(m)"
                                                        :title="canDelete(m) ? 'Delete for everyone' : modifyHint('deleting')"
                                                        :class="canDelete(m) ? 'text-gray-500 hover:text-red-500' : 'cursor-not-allowed text-gray-300'"
                                                        class="grid h-7 w-7 place-items-center rounded-full border border-gray-200 bg-white shadow-sm">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>
                                                </button>
                                            </template>
                                        </div>
                                        @endif
                                        {{-- quoted (reply-to) reference --}}
                                        <template x-if="m.quoted">
                                            <div class="mb-1 overflow-hidden rounded-md border-l-4 border-emerald-400 bg-black/5 px-2 py-1">
                                                <span class="block text-[11px] font-bold text-emerald-700" x-text="m.quoted.sender || 'Reply'"></span>
                                                <span class="block truncate text-xs text-gray-500" x-text="m.quoted.body"></span>
                                            </div>
                                        </template>
                                        {{-- group sender name --}}
                                        <template x-if="m.sender_name && m.direction === 'in'">
                                            <span class="mb-0.5 block text-xs font-bold text-indigo-600" x-text="m.sender_name"></span>
                                        </template>
                                        {{-- media --}}
                                        <template x-if="m.media && m.type === 'image'">
                                            {{-- Fixed-size thumbnail so loading never reflows the thread (no scroll jump); full view opens in the lightbox. --}}
                                            <div class="mb-1">
                                                <img :src="m.media" @click="openLightbox(m)" loading="lazy" decoding="async" class="cursor-pointer rounded-lg bg-gray-200 object-cover transition hover:brightness-95" style="width:240px;height:190px;max-width:100%">
                                            </div>
                                        </template>
                                        <template x-if="m.media && m.type === 'video'">
                                            <div class="mb-1">
                                                <video :src="m.media" controls preload="none" class="max-h-80 rounded-lg bg-gray-100" style="max-width:260px"></video>
                                                <a :href="m.media" :download="m.media_name || 'video'" class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-gray-500 hover:text-emerald-600"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 19h16"/></svg>Download</a>
                                            </div>
                                        </template>
                                        <template x-if="m.media && m.type === 'audio'">
                                            <div class="mb-1">
                                                <audio controls preload="metadata" class="w-60"><source :src="m.media" :type="(m.media_mime || 'audio/ogg').split(';')[0]"></audio>
                                                <a :href="m.media" :download="m.media_name || 'audio.ogg'" class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-medium text-gray-500 hover:text-emerald-600"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 19h16"/></svg>Download</a>
                                            </div>
                                        </template>
                                        <template x-if="m.media && m.type === 'document'"><a :href="m.media" :download="m.media_name || 'document'" target="_blank" class="mb-1 flex items-center gap-2 rounded-lg bg-black/5 px-3 py-2.5 text-gray-700 hover:bg-black/10" style="max-width:260px"><svg class="h-6 w-6 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/></svg><span class="min-w-0"><span class="block truncate text-sm font-medium" x-text="m.media_name || 'Document'"></span><span class="text-[10px] text-gray-400">Tap to download</span></span></a></template>
                                        {{-- deleted placeholder --}}
                                        <span x-show="m.deleted" class="flex items-center gap-1.5 text-sm italic text-gray-400">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M5.6 5.6l12.8 12.8"/></svg>
                                            <span x-text="m.direction === 'out' ? 'You deleted this message' : 'This message was deleted'"></span>
                                        </span>
                                        {{-- body / inline editor --}}
                                        <span x-show="m.body && editingId !== m.id" x-html="formatBody(m.body)" class="whitespace-pre-line break-words align-bottom"></span>
                                        <template x-if="editingId === m.id">
                                            <div style="width:17rem; max-width:58vw;">
                                                <textarea x-model="editDraft" rows="2" x-init="$nextTick(() => { $el.focus(); $el.setSelectionRange($el.value.length, $el.value.length); })" @keydown.enter.prevent="saveEdit(m)" @keydown.escape="editingId = null"
                                                          class="w-full resize-none rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-sm leading-tight text-gray-800 shadow-inner focus:border-emerald-400 focus:ring-emerald-400"></textarea>
                                                <div class="mt-1.5 flex items-center justify-end gap-2 text-xs">
                                                    <button type="button" @click="editingId = null" class="rounded-md px-2.5 py-1 font-medium text-gray-500 hover:bg-black/5">Cancel</button>
                                                    <button type="button" @click="saveEdit(m)" class="rounded-md bg-emerald-500 px-3 py-1 font-semibold text-white hover:bg-emerald-600">Save</button>
                                                </div>
                                            </div>
                                        </template>
                                        {{-- inline meta (time + ticks) --}}
                                        <span x-show="!m.deleted && editingId !== m.id" class="float-right ml-2 mt-1 inline-flex translate-y-0.5 items-center gap-0.5 text-[10px] leading-none text-gray-500/80">
                                            <span x-show="m.edited" class="mr-0.5 italic">edited</span>
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
                                        {{-- emoji reaction badge(s) — both parties, WhatsApp-style --}}
                                        <template x-if="m.reaction || m.my_reaction">
                                            <span class="absolute inline-flex items-center gap-0.5 rounded-full border border-gray-100 bg-white text-sm shadow"
                                                  :style="'height:1.45rem;padding:0 .3rem;bottom:-.7rem;' + (m.direction === 'out' ? 'right:.4rem' : 'left:.4rem')">
                                                <template x-for="e in [...new Set([m.my_reaction, m.reaction].filter(Boolean))]" :key="e">
                                                    <span class="leading-none" x-text="e"></span>
                                                </template>
                                            </span>
                                        </template>
                                    </div>
                                    {{-- A failed message says why, and offers to go again. Retrying reuses the row:
                                         the customer never saw the first attempt, so showing it twice would be a lie. --}}
                                    <template x-if="m.direction === 'out' && m.status === 'failed'">
                                        <span class="mr-1 mt-1 inline-flex flex-wrap items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-[10px] text-red-700">
                                            <span x-text="m.error || 'Could not be sent.'"></span>
                                            <button type="button" @click="retry(m)" :disabled="retryingId === m.id"
                                                    class="rounded border border-red-200 bg-white px-1.5 py-0.5 font-semibold text-red-700 hover:bg-red-100 disabled:opacity-50">
                                                <span x-text="retryingId === m.id ? 'Sending…' : 'Retry'"></span>
                                            </button>
                                        </span>
                                    </template>

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
                            {{-- Replying-to preview --}}
                            <template x-if="replyTo">
                                <div class="mb-2 flex items-center gap-2 rounded-lg bg-white px-3 py-2 shadow-sm">
                                    <div class="w-1 shrink-0 self-stretch rounded bg-emerald-400"></div>
                                    <div class="min-w-0 flex-1">
                                        <span class="block text-[11px] font-bold text-emerald-700" x-text="replyTo.direction === 'out' ? 'You' : (replyTo.sender_name || active.name)"></span>
                                        <span class="block truncate text-xs text-gray-500" x-text="replyTo.body || (replyTo.type === 'image' ? '📷 Photo' : replyTo.type === 'video' ? '🎥 Video' : replyTo.type === 'audio' ? '🎵 Voice message' : replyTo.type === 'document' ? '📄 Document' : 'Message')"></span>
                                    </div>
                                    <button type="button" @click="replyTo = null" class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-gray-400 hover:bg-gray-100"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                                </div>
                            </template>
                            <div class="mb-2 flex flex-wrap gap-1.5" x-show="showQuick" x-cloak>
                                <template x-for="qr in visibleQuickReplies()" :key="qr.shortcut + '|' + qr.body">
                                    <button type="button" @click="draft = qr.body; showQuick = false; $nextTick(() => autoGrow())" class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-600 shadow-sm hover:bg-gray-50" x-text="qr.shortcut || qr.body.slice(0, 20)"></button>
                                </template>
                                <span x-show="!visibleQuickReplies().length" class="px-1 text-[11px] text-gray-400">No quick replies for this number. Add some in Settings › WhatsApp.</span>
                            </div>

                            {{-- Slash "/" quick-reply picker — appears while typing a shortcut --}}
                            <div x-show="slashOpen()" x-cloak @click.outside="slashOff = true"
                                 class="mb-2 max-h-56 overflow-y-auto rounded-xl border border-gray-100 bg-white p-1 shadow-lg">
                                <p class="px-2 pb-1 pt-1 text-[10px] font-bold uppercase tracking-wide text-gray-400">Quick replies</p>
                                <template x-for="(qr, i) in slashMatches()" :key="qr.shortcut + '|' + qr.body">
                                    <button type="button" @click="pickSlash(qr)" @mouseenter="slashIndex = i"
                                            :class="slashIndex === i ? 'bg-emerald-50' : ''"
                                            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left hover:bg-emerald-50">
                                        <span class="shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-500" x-text="qr.shortcut"></span>
                                        <span class="min-w-0 flex-1 truncate text-xs text-gray-600" x-text="qr.body"></span>
                                    </button>
                                </template>
                            </div>

                            <form @submit.prevent="send()" class="flex items-end gap-2">
                                {{-- Attach (+) menu --}}
                                <div class="relative shrink-0">
                                    <button type="button" @click="attachOpen = !attachOpen" class="grid h-11 w-11 place-items-center rounded-full text-gray-500 transition hover:bg-gray-200" :class="attachOpen ? 'bg-gray-200 text-emerald-600' : ''" title="Attach">
                                        <svg class="h-6 w-6 transition" :class="attachOpen ? 'rotate-45' : ''" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                    </button>
                                    <div x-show="attachOpen" x-cloak x-transition @click.outside="attachOpen = false" style="bottom:3.5rem; left:0;" class="absolute z-30 w-56 overflow-hidden rounded-2xl border border-gray-100 bg-white p-1.5 shadow-xl">
                                        <button type="button" @click="$refs.mediaInput.click(); attachOpen = false" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-sky-100 text-sky-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 18 5-5 4 4 3-3 4 4"/></svg></span>
                                            Photos &amp; Videos
                                        </button>
                                        <button type="button" @click="$refs.docInput.click(); attachOpen = false" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-violet-100 text-violet-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/></svg></span>
                                            Document
                                        </button>
                                    </div>
                                    <input type="file" x-ref="mediaInput" accept="image/*,video/*" class="hidden" @change="sendFile($event)">
                                    <input type="file" x-ref="docInput" class="hidden" @change="sendFile($event)">
                                </div>
                                <button type="button" @click="showQuick = !showQuick" class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-gray-500 transition hover:bg-gray-200" title="Quick replies">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 2 3 14h7l-1 8 10-12h-7l1-8Z"/></svg>
                                </button>
                                {{-- @mention (groups only) --}}
                                <div class="relative shrink-0" x-show="active && active.is_group">
                                    <button type="button" @click="mentionOpen = !mentionOpen; if (mentionOpen && !members.length) loadMembers()" class="grid h-11 w-11 place-items-center rounded-full text-gray-500 transition hover:bg-gray-200" :class="mentionOpen ? 'bg-gray-200 text-emerald-600' : ''" title="Mention">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M16 12v1.5a2.5 2.5 0 0 0 5 0V12a9 9 0 1 0-3.5 7.1"/></svg>
                                    </button>
                                    <div x-show="mentionOpen" x-cloak @click.outside="mentionOpen = false" class="absolute z-30 w-60 overflow-hidden rounded-2xl border border-gray-100 bg-white p-1.5 shadow-xl" style="bottom:3.5rem;left:0">
                                        <p class="px-2 pb-1 pt-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">Mention</p>
                                        <button type="button" @click="mentionEveryone()" class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left hover:bg-gray-50">
                                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 0 0-3-3.87M9 20H4v-1a4 4 0 0 1 3-3.87m0 0a4 4 0 1 1 5.9 0M17 11a3 3 0 1 0-2.5-4.5"/></svg></span>
                                            <span class="text-sm font-semibold text-[var(--color-heading)]">Everyone</span>
                                        </button>
                                        <div class="max-h-52 overflow-y-auto">
                                            <template x-for="mb in members" :key="mb.id">
                                                <button type="button" @click="insertMention(mb)" class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left hover:bg-gray-50">
                                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-500" x-text="mb.name ? mb.name.trim().charAt(0).toUpperCase() : '@'"></span>
                                                    <span class="min-w-0"><span class="block truncate text-sm text-[var(--color-heading)]" x-text="mb.name || mb.phone || 'Member'"></span></span>
                                                </button>
                                            </template>
                                            <p x-show="!members.length" class="px-2.5 py-2 text-xs text-gray-400">Loading members…</p>
                                        </div>
                                    </div>
                                </div>
                            {{-- 24-hour window: WhatsApp only accepts an approved template until the contact replies. --}}
                            <div x-show="templateGate" x-cloak class="mb-2 w-full rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-xs text-amber-800">
                                        This contact last wrote over 24 hours ago. WhatsApp only allows an approved template until they reply.
                                    </p>
                                    <button type="button" @click="templateOpen = !templateOpen"
                                            class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                                        <span x-text="templateOpen ? 'Close' : 'Choose a template'"></span>
                                    </button>
                                </div>

                                <div x-show="templateOpen" x-cloak class="mt-3 border-t border-amber-200 pt-3">
                                    <p x-show="!templates.length" class="text-xs text-amber-700">
                                        No approved templates on this number yet. They are created and submitted for review in Meta &rsaquo; WhatsApp &rsaquo; Message Templates.
                                    </p>

                                    <div x-show="templates.length && !pickedTemplate" class="flex max-h-56 flex-col gap-1.5 overflow-auto">
                                        <template x-for="t in templates" :key="t.name + t.language">
                                            <button type="button" @click="pickTemplate(t)"
                                                    class="rounded-lg border border-amber-200 bg-white px-3 py-2 text-left hover:bg-amber-100">
                                                <span class="block text-xs font-semibold text-[var(--color-heading)]" x-text="t.name"></span>
                                                <span class="block truncate text-xs text-gray-500" x-text="t.body"></span>
                                                <span class="text-[11px] text-gray-400" x-text="t.language + ' · ' + t.category"></span>
                                            </button>
                                        </template>
                                    </div>

                                    <div x-show="pickedTemplate" x-cloak>
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="text-xs font-semibold text-[var(--color-heading)]" x-text="pickedTemplate?.name"></span>
                                            <button type="button" @click="pickedTemplate = null" class="text-xs font-semibold text-amber-700 hover:underline">Back to list</button>
                                        </div>

                                        <template x-for="(v, i) in templateVars" :key="i">
                                            <input type="text" x-model="templateVars[i]" :placeholder="'Value for {{' + (i + 1) + '}}'"
                                                   class="mb-1.5 h-9 w-full rounded-lg border-gray-200 text-xs">
                                        </template>

                                        <p class="mb-2 rounded-lg bg-white px-3 py-2 text-xs text-gray-700" x-text="templatePreview() || 'This template has no text body.'"></p>

                                        <button type="button" @click="sendTemplate()" :disabled="sending"
                                                class="rounded-lg bg-emerald-500 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-600 disabled:opacity-50">
                                            <span x-text="sending ? 'Sending…' : 'Send template'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                                <textarea x-ref="composer" x-model="draft"
                                          @keydown.enter="if (slashOpen()) { $event.preventDefault(); pickSlash(slashMatches()[slashIndex]); } else if (!$event.shiftKey && !$event.isComposing) { $event.preventDefault(); send(); }"
                                          @keydown.arrow-down="if (slashOpen()) { $event.preventDefault(); slashNav(1); }"
                                          @keydown.arrow-up="if (slashOpen()) { $event.preventDefault(); slashNav(-1); }"
                                          @keydown.escape="slashOff = true"
                                          @input="autoGrow(); slashOff = false; slashIndex = 0" rows="1" :placeholder="templateGate ? 'Waiting on their reply — send an approved template to reopen the chat' : 'Type a message…  (Enter to send · type / for quick replies)'"
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
                                <span class="mt-2 inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold" :class="qualityTone(active.lead_quality).pill">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="qualityTone(active.lead_quality).dot"></span>
                                    <span x-text="qualityLabels[active.lead_quality] || active.lead_quality"></span>
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
                                {{-- Client's current local time (from their country's timezone) --}}
                                <div class="flex items-start gap-2.5" x-show="active.timezone">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 1.5"/></svg>
                                    <div>
                                        <dt class="text-[10px] uppercase tracking-wide text-gray-400">Local time</dt>
                                        <dd class="font-semibold text-[var(--color-heading)]">
                                            <span x-text="localTime(active.timezone)"></span>
                                            <span class="ml-1 text-[10px] font-normal text-gray-400" x-text="tzLabel(active.timezone)"></span>
                                        </dd>
                                    </div>
                                </div>
                            </dl>
                        </div>

                        {{-- Group members (groups only) --}}
                        <div x-show="active.is_group" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                            <div class="mb-3 flex items-center justify-between">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Members <span x-show="members.length" x-text="'(' + members.length + ')'"></span></p>
                                <button type="button" @click="loadMembers()" :disabled="membersLoading" class="text-[11px] font-semibold text-emerald-600 hover:underline disabled:opacity-50" x-text="membersLoading ? 'Loading…' : 'Refresh'"></button>
                            </div>
                            <p x-show="!members.length && !membersLoading" class="text-xs text-gray-400">No member info loaded.</p>
                            <ul class="space-y-2.5">
                                <template x-for="mb in members" :key="mb.id">
                                    <li class="flex items-center gap-2.5">
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-50 text-[11px] font-bold text-emerald-700" x-text="mb.country ? mb.country.flag : (mb.name ? mb.name.trim().charAt(0).toUpperCase() : '👤')"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-[var(--color-heading)]" x-text="mb.name || mb.phone || 'Group member'"></p>
                                            <p class="truncate text-[10px] text-gray-400">
                                                <span x-show="mb.name && mb.phone" x-text="mb.phone"></span>
                                                <span x-show="mb.country" x-text="(mb.name && mb.phone ? ' · ' : '') + (mb.country ? mb.country.name : '')"></span>
                                                <span x-show="mb.timezone" x-text="' · ' + localTime(mb.timezone)"></span>
                                                <span x-show="!mb.name && !mb.phone">Hidden by privacy</span>
                                                <span x-show="mb.admin" class="ml-1 rounded bg-emerald-50 px-1 font-semibold text-emerald-600">admin</span>
                                            </p>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        {{-- Group settings (groups only) --}}
                        @if ($canReply)
                            <div x-show="active.is_group" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                                <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-gray-400">Group settings</p>
                                <label class="mb-1 block text-xs font-medium text-gray-500">Group name</label>
                                <input type="text" x-model="form.name" @keydown.enter.prevent="saveDetails()" placeholder="Group name"
                                       class="h-9 w-full rounded-lg border-gray-200 text-sm focus:border-emerald-400 focus:ring-emerald-400">
                                <button type="button" @click="saveDetails()" :disabled="savingDetails"
                                        class="mt-3 w-full rounded-lg bg-emerald-500 py-2 text-xs font-semibold text-white transition hover:bg-emerald-600 disabled:opacity-50">
                                    <span x-show="!savingDetails">Save name</span>
                                    <span x-show="savingDetails" x-cloak>Saving…</span>
                                </button>
                                <p class="mt-2 text-[10px] text-gray-400">Use the camera on the photo to change the group picture. Updating the name or photo requires you to be a group admin.</p>
                            </div>
                        @endif

                        {{-- Lead info (editable) --}}
                        <div x-show="!active.is_group" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
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
                                {{-- Lead quality. Saves on change: it is one choice with no second
                                     field to fill in, and hiding it behind a button meant a chat
                                     was left marked wrong whenever someone picked and moved on. --}}
                                <div>
                                    <label class="mb-1 flex items-center justify-between text-xs font-medium text-gray-500">
                                        <span>Lead quality</span>
                                        <span x-show="savingQuality" x-cloak class="text-[10px] font-normal text-emerald-600">Saving…</span>
                                        <span x-show="!savingQuality && qualitySaved" x-cloak class="text-[10px] font-normal text-emerald-600">Saved</span>
                                    </label>
                                    <select x-model="form.lead_quality" @change="saveQuality()"
                                            class="h-9 w-full rounded-lg border-gray-200 text-sm focus:border-emerald-400 focus:ring-emerald-400">
                                        <option value="">— Not set —</option>
                                        @foreach ($leadQualities as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                                    </select>
                                    {{-- Qualified/Unqualified makes the lead by itself. Say so when it
                                         could not — a silent no-op looks exactly like success. --}}
                                    <p x-show="leadWarning" x-cloak class="mt-1 text-[10px] text-amber-600" x-text="leadWarning"></p>
                                </div>
                                {{-- Interested in — the same Product Category / Sub-category the Lead,
                                     Deal and Client forms use, so a converted chat carries them across.
                                     Sub-category only appears when the chosen category has one. --}}
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-500">Interested in</label>
                                    <select x-model="form.product_category" @change="form.product_sub_category = ''"
                                            class="h-9 w-full rounded-lg border border-gray-200 bg-white px-2 text-sm focus:border-emerald-400 focus:outline-none">
                                        <option value="">— Select a category —</option>
                                        <template x-if="form.product_category && !Object.keys(categoryTree).includes(form.product_category)">
                                            <option :value="form.product_category" x-text="form.product_category"></option>
                                        </template>
                                        @foreach (array_keys($categoryTree) as $cat)<option value="{{ $cat }}">{{ $cat }}</option>@endforeach
                                    </select>
                                    @if (! count($categoryTree))
                                        <p class="mt-1 text-[10px] text-gray-300">None yet — add them in Settings › CRM Settings › Product Categories.</p>
                                    @endif
                                </div>
                                <div x-show="subCategories.length" x-cloak>
                                    <label class="mb-1 block text-xs font-medium text-gray-500">Sub-category</label>
                                    <select x-model="form.product_sub_category"
                                            class="h-9 w-full rounded-lg border border-gray-200 bg-white px-2 text-sm focus:border-emerald-400 focus:outline-none">
                                        <option value="">— Select a sub-category —</option>
                                        <template x-for="s in subCategories" :key="s">
                                            <option :value="s" x-text="s"></option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Only once something is actually different. A button that is always
                                     there says nothing about whether it needs pressing. --}}
                                <button type="button" x-show="detailsDirty" x-cloak @click="saveDetails()" :disabled="savingDetails"
                                        class="w-full rounded-lg bg-emerald-500 py-2 text-xs font-semibold text-white transition hover:bg-emerald-600 disabled:opacity-50">
                                    <span x-show="!savingDetails">Save details</span>
                                    <span x-show="savingDetails" x-cloak>Saving…</span>
                                </button>

                                @if (auth()->user()->hasPermission('leads.create') || auth()->user()->isAdmin())
                                    {{-- Convert to CRM lead (or open the linked lead) --}}
                                    <div class="mt-1 border-t border-gray-100 pt-3" x-show="!active.is_group">
                                        <template x-if="active.lead">
                                            <a :href="active.lead.url" class="flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 3h7l5 5v13H7z"/></svg>
                                                View Lead <span x-text="active.lead.code"></span>
                                            </a>
                                        </template>
                                        <template x-if="!active.lead">
                                            <button type="button" @click="convertLead()" :disabled="convertingLead"
                                                    class="flex w-full items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 disabled:opacity-50">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                                <span x-show="!convertingLead">Convert to Lead</span>
                                                <span x-show="convertingLead" x-cloak>Converting…</span>
                                            </button>
                                        </template>
                                    </div>
                                @endif
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
        /* Guaranteed spin for the Sync button (works even if the Tailwind utility isn't compiled). */
        @keyframes waSpin { to { transform: rotate(360deg); } }
        .wa-spin { animation: waSpin .9s linear infinite; }

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
                chats: [], active: null, messages: [], draft: '', noteDraft: '', sending: false, showQuick: false, attachOpen: false,
                // Cloud API numbers only: outside 24h from the customer's last message, WhatsApp
                // accepts an approved template and nothing else.
                templateGate: false, templates: [], templateOpen: false, pickedTemplate: null, templateVars: [],
                retryingId: null,
                showInfo: false, search: '', filter: 'all',
                form: { name: '', phone: '', lead_quality: '', product_category: '', product_sub_category: '' }, savingDetails: false, uploadingAvatar: false, convertingLead: false, _chatReq: 0, nowTick: 0,
                // What the form looked like when the chat was opened, so the Save button can appear
                // only once something is genuinely different rather than sitting there always.
                formBaseline: { name: '', phone: '', product_category: '', product_sub_category: '' },
                savingQuality: false, qualitySaved: false, leadWarning: '',
                qualityLabels: @js(\App\Models\WhatsappChat::LEAD_QUALITIES),
                // Shared Product Category tree from Settings > CRM Settings.
                categoryTree: @js($categoryTree),
                // Lead quality is excluded — it saves itself the moment it changes.
                get detailsDirty() {
                    const b = this.formBaseline;
                    return this.form.name !== b.name
                        || this.form.phone !== b.phone
                        || this.form.product_category !== b.product_category
                        || this.form.product_sub_category !== b.product_sub_category;
                },
                qualityTone(q) {
                    if (q === 'qualified') return { pill: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' };
                    if (q === 'conversational') return { pill: 'bg-blue-50 text-blue-700', dot: 'bg-blue-500' };
                    return { pill: 'bg-rose-100 text-rose-700', dot: 'bg-red-500' };
                },
                get subCategories() {
                    const list = this.categoryTree[this.form.product_category] ? [...this.categoryTree[this.form.product_category]] : [];
                    // a value saved before its sub-category was removed must still be visible
                    if (this.form.product_sub_category && !list.includes(this.form.product_sub_category)) list.push(this.form.product_sub_category);
                    return list;
                },
                newChat: { open: false, number: '', busy: false, error: '' }, members: [], membersLoading: false,
                mentionOpen: false, mentionJids: [],
                lightbox: { open: false, index: 0, items: [] }, lbTouch: 0, replyTo: null,
                dragOver: false, pending: null,
                accMenu: false, syncingId: null, dragId: null, hasMore: false, loadingOlder: false,
                // The chosen number survives a reload by living in the URL. First number only
                // when the URL names none — landing back on number one from number nine every
                // reload made the picker feel broken.
                accountId: @js(($accounts->firstWhere('id', (int) request('account')) ?? $accounts->first())->id ?? null),
                accountsList: @js($accounts->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'number' => $a->display_number, 'connected' => $a->isConnected(), 'cloud' => $a->isCloudApi(), 'unread' => $accountUnreads[$a->id] ?? 0])->values()),
                currentAccount() { return this.accountsList.find(a => a.id === this.accountId) || {}; },
                quickReplies: @js($quickReplies->map(fn ($q) => ['shortcut' => $q->shortcut, 'body' => $q->body, 'account_id' => $q->account_id])->values()),
                // Quick replies are per-number — show only the selected number's own set.
                visibleQuickReplies() { return this.quickReplies.filter(q => q.account_id === this.accountId); },
                // Type "/" in the composer → live quick-reply picker (filtered by the shortcut typed).
                slashIndex: 0, slashOff: false,
                slashMatches() {
                    const t = this.draft.replace(/^\//, '').toLowerCase();
                    return this.visibleQuickReplies().filter(q => q.shortcut && q.shortcut.replace(/^\//, '').toLowerCase().startsWith(t));
                },
                slashOpen() { return !this.slashOff && this.draft.startsWith('/') && !/\s/.test(this.draft) && this.slashMatches().length > 0; },
                slashNav(dir) { const n = this.slashMatches().length; if (n) this.slashIndex = (this.slashIndex + dir + n) % n; },
                pickSlash(qr) { if (!qr) return; this.draft = qr.body; this.slashIndex = 0; this.$nextTick(() => { this.autoGrow(); if (this.$refs.composer) this.$refs.composer.focus(); }); },
                editingId: null, editDraft: '',
                quickEmojis: ['👍', '❤️', '😂', '😮', '😢', '🙏'],
                emojiList: ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙','😋','😛','😜','🤪','😝','🤗','🤭','🤫','🤔','😐','😑','😶','😏','😒','🙄','😬','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤠','🥳','😎','🤓','🧐','😕','😟','🙁','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','😈','💀','💩','👍','👎','👌','✌️','🤞','🤟','🤘','👈','👉','👆','👇','☝️','✋','🤚','🖐️','👋','🤙','💪','🙏','👏','🙌','👐','🤝','❤️','🧡','💛','💚','💙','💜','🖤','🤍','💯','🔥','⭐','🎉','🎊','✅','❌','⚡','💡','📌','🚀'],
                unreadCount: {{ $stats['unread'] }},
                filters: [
                    { key: 'all', label: 'All' }, { key: 'unread', label: 'Unread' },
                    { key: 'single', label: 'Single' }, { key: 'group', label: 'Group' },
                    // The only way back to a blocked chat once it leaves the list.
                    { key: 'blocked', label: 'Blocked' },
                ],
                chatMenu: { open: false, x: 0, y: 0, chat: null },
                labelMenu: false, pickedLabels: [],
                labels: @js($labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->values()),
                labelCounts: @js($labelCounts),
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
                    // Deep-link: open a specific chat when arrived from a CRM lead (?chat=ID).
                    const wanted = new URLSearchParams(window.location.search).get('chat');
                    if (wanted) { this.showInfo = true; this.openChat(parseInt(wanted, 10)); }
                    // Keep the contact's local clock ticking.
                    setInterval(() => { this.nowTick++; }, 20000);
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
                    if (this.accountId) p.set('account', this.accountId);
                    if (this.search) p.set('search', this.search);
                    if (this.filter === 'mine') p.set('mine', '1');
                    else if (this.filter === 'single' || this.filter === 'group') p.set('type', this.filter);
                    else if (this.filter !== 'all') p.set('status', this.filter);
                    this.pickedLabels.forEach(id => p.append('labels[]', id));
                    return p.toString();
                },
                setFilter(k) { this.filter = k; this.loadChats(); },
                switchAccount(id) {
                    this.accountId = id;
                    this.accMenu = false;
                    this.active = null;
                    const url = new URL(window.location);
                    url.searchParams.set('account', id);
                    window.history.replaceState({}, '', url);
                    this.loadChats();
                },
                get pickedLabelObjects() { return this.labels.filter(l => this.pickedLabels.includes(l.id)); },
                labelPillText() {
                    const picked = this.pickedLabelObjects;
                    if (!picked.length) return 'Labels';
                    // One is worth naming; past that the name would crowd out the list itself.
                    return picked.length === 1 ? picked[0].name : picked.length + ' labels';
                },
                toggleLabelFilter(id) {
                    this.pickedLabels = this.pickedLabels.includes(id)
                        ? this.pickedLabels.filter(x => x !== id)
                        : [...this.pickedLabels, id];
                    this.loadChats();
                },
                /**
                 * Right-click menu, placed at the pointer and nudged back on screen.
                 *
                 * A row near the bottom of a long list would otherwise open its menu below the
                 * fold, where the actions cannot be reached at all.
                 */
                openChatMenu(e, c) {
                    this.chatMenu = { open: true, x: e.clientX, y: e.clientY, chat: c };

                    // Read now: currentTarget is null again by the time $nextTick runs.
                    const panel = e.currentTarget.closest('aside');

                    this.$nextTick(() => {
                        const m = this.$refs.chatMenu;
                        if (!m || !panel) return;
                        const pad = 8;
                        const w = m.offsetWidth || 208;
                        const h = m.offsetHeight || 0;
                        const rect = panel.getBoundingClientRect();

                        // Slide the menu until it fits INSIDE the chat list, never flip past its
                        // left edge: the admin navigation sits there, and a menu straddling the
                        // two panels is the broken look this replaces.
                        this.chatMenu.x = Math.max(rect.left + pad, Math.min(this.chatMenu.x, rect.right - w - pad));
                        if (h && this.chatMenu.y + h > window.innerHeight - pad) {
                            this.chatMenu.y = Math.max(pad, window.innerHeight - h - pad);
                        }
                    });
                },
                async menuPin() {
                    const c = this.chatMenu.chat;
                    if (!c) return;
                    this.chatMenu.open = false;
                    const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + c.id + '/pin', {});
                    if (!r.ok) { alert('Could not pin the chat — please reload and try again.'); return; }
                    this.loadChats();
                },
                async menuUnread() {
                    const c = this.chatMenu.chat;
                    if (!c) return;
                    this.chatMenu.open = false;
                    const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + c.id + '/unread', {});
                    if (!r.ok) { alert('Could not mark the chat unread — please reload and try again.'); return; }
                    // Leaving it open would mark it read again the moment anyone looked at it.
                    if (this.active && this.active.id === c.id) this.active = null;
                    this.loadChats();
                },
                async menuBlock() {
                    const c = this.chatMenu.chat;
                    if (!c) return;
                    this.chatMenu.open = false;
                    if (!c.blocked && !confirm('Block ' + c.name + '?\n\nThe chat leaves this list and cannot be replied to. WhatsApp is not told, so their messages still arrive — find the chat again under the Blocked filter.')) return;
                    const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + c.id + '/block', {});
                    if (r.ok && this.active && this.active.id === c.id) this.active = null;
                    this.loadChats();
                },
                async loadChats() {
                    const token = ++this._chatReq;
                    const r = await fetch(@js(route('admin.whatsapp.chats')) + '?' + this.params());
                    const data = await r.json();
                    if (token !== this._chatReq) return; // a newer search superseded this response
                    this.chats = data.chats;
                    if (data.label_counts) this.labelCounts = data.label_counts;
                    if (typeof data.unread === 'number') this.unreadCount = data.unread;
                    if (data.account_unreads) {
                        this.accountsList.forEach(a => { a.unread = data.account_unreads[a.id] || 0; });
                    }
                },
                otherUnread() { return this.accountsList.filter(a => a.id !== this.accountId).reduce((n, a) => n + (a.unread || 0), 0); },
                async syncAccount(id) {
                    id = id || this.accountId;
                    if (this.syncingId || !id) return;
                    this.syncingId = id;
                    try {
                        const r = await this.post(@js(url('admin/whatsapp/accounts')) + '/' + id + '/resync', {});
                        if (!r.ok) { alert((await r.json()).error || 'Sync failed.'); this.syncingId = null; return; }
                        // Reconnect takes a few seconds; refresh the inbox afterwards (only if it's the open number).
                        setTimeout(() => { if (id === this.accountId) { this.loadChats(); if (this.active) this.openChat(this.active.id, true); } this.syncingId = null; }, 6000);
                    } catch { alert('Sync failed.'); this.syncingId = null; }
                },
                // Drag & drop reorder of the numbers — persisted per employee.
                dropOnNumber(targetId) {
                    if (this.dragId == null || this.dragId === targetId) { this.dragId = null; return; }
                    const list = this.accountsList;
                    const from = list.findIndex(a => a.id === this.dragId);
                    const to = list.findIndex(a => a.id === targetId);
                    this.dragId = null;
                    if (from < 0 || to < 0) return;
                    const [moved] = list.splice(from, 1);
                    list.splice(to, 0, moved);
                    this.post(@js(route('admin.whatsapp.number-order')), { order: list.map(a => a.id) }).catch(() => {});
                },
                async openChat(id, silent = false) {
                    const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + id);
                    const d = await r.json();
                    const atBottom = silent ? this.isAtBottom() : true;
                    this.active = d.chat; this.messages = d.messages; this.hasMore = !!d.has_more;
                    // Load member list for group chats.
                    this.members = [];
                    if (d.chat.is_group) this.loadMembers();
                    // Seed the editable lead form (strip the leading + so the input holds plain digits).
                    this.form = {
                        name: d.chat.raw_name || '',
                        phone: (d.chat.phone || '').replace(/^\+/, ''),
                        lead_quality: d.chat.lead_quality || '',
                        product_category: d.chat.product_category || '',
                        product_sub_category: d.chat.product_sub_category || '',
                    };
                    this.rebaseForm();
                    this.qualitySaved = false; this.leadWarning = '';
                    if (!silent) { this.replyTo = null; const c = this.chats.find(x => x.id === id); if (c) c.unread = 0; this.loadTemplates(id); }
                    // Always land at the newest message when opening; on live refresh only if already at bottom.
                    if (atBottom) this.scrollBottom();
                },
                async loadOlder() {
                    if (this.loadingOlder || !this.active || !this.messages.length) return;
                    this.loadingOlder = true;
                    const beforeId = this.messages[0].id;
                    const t = this.$refs.thread;
                    const prevHeight = t ? t.scrollHeight : 0;
                    try {
                        const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/older?before_id=' + beforeId);
                        const d = await r.json();
                        if (d.messages && d.messages.length) {
                            this.messages = [...d.messages, ...this.messages];
                            this.hasMore = !!d.has_more;
                            // Keep the same message in view after older ones are prepended.
                            this.$nextTick(() => { const t2 = this.$refs.thread; if (t2) t2.scrollTop = t2.scrollHeight - prevHeight; });
                        } else { this.hasMore = false; }
                    } catch {} finally { this.loadingOlder = false; }
                },
                isAtBottom() {
                    const t = this.$refs.thread;
                    return !t || (t.scrollHeight - t.scrollTop - t.clientHeight < 80);
                },
                // Ask whether this chat is inside the 24-hour window, and what may be sent if not.
                async loadTemplates(id) {
                    this.templateGate = false; this.templates = []; this.pickedTemplate = null; this.templateOpen = false;
                    try {
                        const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + id + '/templates');
                        if (!r.ok) return;
                        const d = await r.json();
                        this.templateGate = !!d.needs_template;
                        this.templates = d.templates || [];
                    } catch { /* a picker that fails to load must not break the thread */ }
                },

                pickTemplate(t) {
                    this.pickedTemplate = t;
                    this.templateVars = Array.from({ length: t.variables || 0 }, () => '');
                },

                // What the customer will actually read, so the thread shows that and not the raw name.
                templatePreview() {
                    if (!this.pickedTemplate) return '';
                    let body = this.pickedTemplate.body || '';
                    this.templateVars.forEach((v, i) => {
                        body = body.replace(new RegExp('\\{\\{\\s*' + (i + 1) + '\\s*\\}\\}', 'g'), v || '');
                    });
                    return body;
                },

                async sendTemplate() {
                    if (!this.pickedTemplate || this.sending) return;
                    this.sending = true;
                    try {
                        const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/template', {
                            method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({
                                template: this.pickedTemplate.name,
                                language: this.pickedTemplate.language,
                                variables: this.templateVars,
                                preview: this.templatePreview(),
                            }),
                        });
                        if (r.ok) {
                            this.messages.push((await r.json()).message);
                            this.scrollBottom(true); this.loadChats();
                            // Sending a template reopens the conversation, so the gate lifts.
                            this.templateGate = false; this.templateOpen = false; this.pickedTemplate = null;
                        } else {
                            alert((await r.json()).error || 'Could not send the template.');
                        }
                    } catch { alert('Could not send the template.'); } finally { this.sending = false; }
                },

                /**
                 * Turn links in a message into anchors.
                 *
                 * This is text a stranger typed, so it is escaped FIRST and only then are URL
                 * shapes replaced — building HTML from it any other way is an XSS hole. Only
                 * http/https and bare www. are linked; anything else stays text.
                 */
                /**
                 * A message as WhatsApp itself would show it: *asterisks* mean bold.
                 *
                 * The panel printed the asterisks raw, so anything a customer or an agent
                 * formatted on their phone arrived here looking like it had stray punctuation —
                 * and the emphasis they meant was lost.
                 *
                 * Links are turned into anchors first and then stepped over, so an asterisk that
                 * belongs to a URL stays part of the URL instead of eating the rest of the line.
                 */
                // The opening * must not sit against a word, or "2*3*4" turns bold, and the text
                // between them cannot start or end with a space — that is how WhatsApp tells
                // formatting from an asterisk someone simply typed.
                boldRe: /(^|[^\w*])\*(?=\S)([^*\n]*[^\s*])\*(?![\w*])/g,
                formatBody(text) {
                    return this.linkify(text)
                        .split(/(<a\b[^>]*>.*?<\/a>)/gi)
                        .map((part, i) => i % 2 ? part : part.replace(this.boldRe, '$1<b>$2</b>'))
                        .join('');
                },
                /**
                 * The one-line preview in the chat list, with the markers taken out rather than
                 * shown. It is grey, truncated and a single line tall, so bold would say nothing
                 * there — but the bare asterisks read as typos, which is how the whole Lisa
                 * opening script looked. WhatsApp drops them in its own list for the same reason.
                 */
                plainPreview(text) {
                    return String(text || '').replace(this.boldRe, '$1$2');
                },
                linkify(text) {
                    const escaped = String(text)
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

                    return escaped.replace(
                        /\b((?:https?:\/\/|www\.)[^\s<]+)/gi,
                        (match) => {
                            // Trailing punctuation almost always belongs to the sentence, not the
                            // URL — an asterisk included, since *https://…* is someone emphasising
                            // the link, and it was ending up inside the address.
                            const trail = match.match(/[.,;:!?)\]*]+$/);
                            const url = trail ? match.slice(0, -trail[0].length) : match;
                            const href = url.startsWith('http') ? url : 'https://' + url;

                            // noopener/noreferrer: the target is a stranger's link, and it must not
                            // get a handle on this window or our URL.
                            return '<a href="' + href + '" target="_blank" rel="noopener noreferrer nofollow" ' +
                                   'class="underline hover:opacity-80" style="text-underline-offset:2px">' + url + '</a>' +
                                   (trail ? trail[0] : '');
                        },
                    );
                },

                // Send a failed message again, in place.
                async retry(m) {
                    if (this.retryingId) return;
                    this.retryingId = m.id;
                    try {
                        const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/messages/' + m.id + '/retry', {
                            method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        });
                        const d = await r.json();
                        if (r.ok) {
                            // Replace the row rather than appending — same message, second attempt.
                            const i = this.messages.findIndex(x => x.id === m.id);
                            if (i > -1) this.messages[i] = d.message;
                            this.loadChats();
                        } else {
                            if (d.needs_template) { this.templateGate = true; this.templateOpen = true; }
                            const i = this.messages.findIndex(x => x.id === m.id);
                            if (i > -1) this.messages[i] = { ...this.messages[i], error: d.error };
                        }
                    } catch { /* leave the row failed so it can be tried again */ } finally { this.retryingId = null; }
                },

                async send() {
                    if (!this.draft.trim() || this.sending) return;
                    // The same text already sent into this chat is almost always a slip — the
                    // quick-reply scripts make it an easy one. Warn, but let a deliberate
                    // resend through.
                    const dup = this.messages.find(m =>
                        m.direction === 'out' && !m.deleted && (m.body || '').trim() === this.draft.trim());
                    if (dup && !confirm('You already sent this message in this chat. Send it again?')) return;
                    this.sending = true;
                    const body = this.draft; this.draft = '';
                    // Only keep mentions whose token still appears in the text.
                    const mentions = this.mentionJids.filter(j => body.includes('@' + String(j).split('@')[0]) || body.includes('@everyone'));
                    this.mentionJids = [];
                    const replyId = this.replyTo?.id || null; this.replyTo = null;
                    try {
                        const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/send', {
                            method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ body, mentions, reply_to: replyId }),
                        });
                        if (r.ok) { this.messages.push((await r.json()).message); this.scrollBottom(true); this.loadChats(); this.$nextTick(() => this.autoGrow()); }
                        else {
                            const err = await r.json();
                            this.draft = body;
                            if (err.needs_template) { this.templateGate = true; this.templateOpen = true; }
                            alert(err.error || 'Could not send.');
                        }
                    } catch { this.draft = body; } finally { this.sending = false; }
                },
                startEdit(m) {
                    if (m.type !== 'text' || m.deleted) return;
                    this.editingId = m.id;
                    this.editDraft = m.body || '';
                },
                async saveEdit(m) {
                    const body = this.editDraft.trim();
                    if (!body) return;
                    const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/messages/' + m.id + '/edit', { body });
                    if (r.ok) {
                        const d = (await r.json()).message;
                        const idx = this.messages.findIndex(x => x.id === m.id);
                        if (idx > -1) this.messages[idx] = d;
                        this.editingId = null;
                        this.loadChats();
                    } else { alert((await r.json()).error || 'Could not edit the message.'); }
                },
                async deleteMsg(m) {
                    if (!confirm('Delete this message for everyone?')) return;
                    try {
                        const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/messages/' + m.id, {
                            method: 'DELETE', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        });
                        if (r.ok) {
                            const d = (await r.json()).message;
                            const idx = this.messages.findIndex(x => x.id === m.id);
                            if (idx > -1) this.messages[idx] = d;
                            this.loadChats();
                        } else { alert((await r.json()).error || 'Could not delete the message.'); }
                    } catch { alert('Could not delete the message.'); }
                },
                // WhatsApp's own rules, which differ by how the number is connected. A paired
                // number can edit for 15 minutes and delete for ~60 hours; a Cloud API number can
                // do neither — Meta's API has no edit or delete at all. The panel mirrors this
                // rather than offering a button that fails after the confirm.
                canModify(m) { return !this.currentAccount().cloud && m.ts && (Date.now() / 1000 - m.ts) < 900; },
                canDelete(m) { return !this.currentAccount().cloud && m.ts && (Date.now() / 1000 - m.ts) < 60 * 3600; },
                modifyHint(kind) {
                    if (this.currentAccount().cloud) return 'This number runs on the WhatsApp Cloud API, which does not allow ' + kind + ' sent messages';
                    return kind === 'editing'
                        ? 'WhatsApp allows editing only within 15 minutes of sending'
                        : 'WhatsApp allows deleting for everyone within about 2\u00bd days';
                },
                // Live local time in the contact's timezone (re-runs each tick).
                localTime(tz) {
                    this.nowTick; // reactive dependency so the clock refreshes
                    if (!tz) return '';
                    try { return new Intl.DateTimeFormat('en-US', { timeZone: tz, hour: 'numeric', minute: '2-digit', hour12: true }).format(new Date()); }
                    catch { return ''; }
                },
                tzLabel(tz) { return tz ? tz.split('/').pop().replace(/_/g, ' ') : ''; },
                openLightbox(m) {
                    const items = this.messages.filter(x => x.type === 'image' && x.media && !x.deleted).map(x => ({ media: x.media, name: x.media_name }));
                    const idx = items.findIndex(x => x.media === m.media);
                    this.lightbox = { open: true, index: idx < 0 ? 0 : idx, items };
                },
                closeLightbox() { this.lightbox.open = false; },
                lightboxPrev() { if (this.lightbox.index > 0) this.lightbox.index--; },
                lightboxNext() { if (this.lightbox.index < this.lightbox.items.length - 1) this.lightbox.index++; },
                lbSwipe(endX) {
                    const dx = endX - this.lbTouch;
                    if (Math.abs(dx) > 50) { dx < 0 ? this.lightboxNext() : this.lightboxPrev(); }
                },
                async sendReaction(m, emoji) {
                    // Toggle off if our own reaction is the same emoji.
                    const value = (m.my_reaction === emoji) ? '' : emoji;
                    const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/messages/' + m.id + '/react', { emoji: value });
                    if (r.ok) {
                        const d = (await r.json()).message;
                        const idx = this.messages.findIndex(x => x.id === m.id);
                        if (idx > -1) this.messages[idx] = d;
                    } else { alert((await r.json()).error || 'Could not react.'); }
                },
                // File chosen from the attach menu → preview with a caption (same flow as drag-drop).
                sendFile(e) {
                    const file = e.target.files[0];
                    if (file) this.pickFile(file);
                    e.target.value = '';
                },
                onDrop(e) {
                    this.dragOver = false;
                    const file = e.dataTransfer.files && e.dataTransfer.files[0];
                    if (file) this.pickFile(file);
                },
                pickFile(file) {
                    if (!this.active) return;
                    const type = file.type.startsWith('image/') ? 'image' : (file.type.startsWith('video/') ? 'video' : 'file');
                    this.pending = { file, name: file.name, type, previewUrl: (type === 'image' || type === 'video') ? URL.createObjectURL(file) : null, caption: this.draft || '' };
                },
                cancelPending() {
                    if (this.pending?.previewUrl) URL.revokeObjectURL(this.pending.previewUrl);
                    this.pending = null;
                },
                async sendPending() {
                    if (!this.pending || this.sending || !this.active) return;
                    this.sending = true;
                    const { file, caption } = this.pending;
                    const fd = new FormData();
                    fd.append('file', file);
                    if (caption && caption.trim()) fd.append('caption', caption);
                    try {
                        const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/media', {
                            method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' }, body: fd,
                        });
                        if (r.ok) { this.messages.push((await r.json()).message); this.draft = ''; this.cancelPending(); this.scrollBottom(true); this.loadChats(); this.$nextTick(() => this.autoGrow()); }
                        else { alert((await r.json()).error || 'Could not send the file.'); }
                    } catch { alert('Could not send the file.'); }
                    finally { this.sending = false; }
                },
                async post(url, data) {
                    return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(data) });
                },
                setStatus(s) { this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/status', { status: s }); this.active.status = s; this.loadChats(); },
                async toggleLabel(id) {
                    const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/label', { label_id: id });
                    if (r.ok) { this.active.label_ids = (await r.json()).labels.map(l => l.id); this.loadChats(); }
                },
                async startNewChat() {
                    if (this.newChat.busy || !this.newChat.number.trim()) return;
                    this.newChat.busy = true; this.newChat.error = '';
                    try {
                        const r = await this.post(@js(url('admin/whatsapp/new-chat')), { phone: this.newChat.number, account_id: this.accountId });
                        const d = await r.json();
                        if (r.ok) { this.newChat.open = false; this.newChat.number = ''; await this.loadChats(); this.openChat(d.id); }
                        else { this.newChat.error = d.error || 'Could not start chat.'; }
                    } catch { this.newChat.error = 'Could not start chat.'; }
                    finally { this.newChat.busy = false; }
                },
                _appendMention(token) {
                    const sep = this.draft && !this.draft.endsWith(' ') ? ' ' : '';
                    this.draft = this.draft + sep + token + ' ';
                    this.mentionOpen = false;
                    this.$nextTick(() => { if (this.$refs.composer) this.$refs.composer.focus(); this.autoGrow(); });
                },
                insertMention(mb) {
                    if (!this.mentionJids.includes(mb.id)) this.mentionJids.push(mb.id);
                    this._appendMention('@' + String(mb.id).split('@')[0]);
                },
                mentionEveryone() {
                    this.members.forEach(m => { if (!this.mentionJids.includes(m.id)) this.mentionJids.push(m.id); });
                    this._appendMention('@everyone');
                },
                async loadMembers() {
                    if (!this.active || !this.active.is_group) return;
                    this.membersLoading = true;
                    try {
                        const r = await fetch(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/members');
                        if (r.ok) this.members = (await r.json()).members || [];
                    } catch {}
                    finally { this.membersLoading = false; }
                },
                async convertLead() {
                    if (this.convertingLead || !this.active) return;
                    this.convertingLead = true;
                    try {
                        const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/convert-lead', {});
                        if (r.ok) { this.active.lead = (await r.json()).lead; }
                        else { alert((await r.json()).error || 'Could not convert to lead.'); }
                    } catch { alert('Could not convert to lead.'); }
                    finally { this.convertingLead = false; }
                },
                /**
                 * Take on the lead the save made, if it made one.
                 *
                 * Qualified and Unqualified create the lead on their own, so the panel has to swap
                 * "Convert to Lead" for "View Lead" without a reload — otherwise the button offers
                 * to do something that has already happened.
                 */
                applyLead(d) {
                    if (d.lead) this.active.lead = d.lead;
                    this.leadWarning = d.lead_warning || '';
                },
                rebaseForm() {
                    this.formBaseline = {
                        name: this.form.name, phone: this.form.phone,
                        product_category: this.form.product_category,
                        product_sub_category: this.form.product_sub_category,
                    };
                },
                /**
                 * Save the lead quality on its own, the moment it is picked.
                 *
                 * Sends only that field, so it cannot carry a half-typed name or phone along with
                 * it — those still wait for the button.
                 */
                async saveQuality() {
                    if (!this.active) return;
                    this.savingQuality = true; this.qualitySaved = false;
                    try {
                        const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/details', {
                            name: this.formBaseline.name, phone: this.formBaseline.phone,
                            lead_quality: this.form.lead_quality,
                            product_category: this.formBaseline.product_category,
                            product_sub_category: this.formBaseline.product_sub_category,
                        });
                        if (r.ok) {
                            const d = await r.json();
                            this.active.lead_quality = d.lead_quality;
                            this.applyLead(d);
                            this.qualitySaved = true;
                            setTimeout(() => { this.qualitySaved = false; }, 2000);
                            this.loadChats();
                        } else { alert((await r.json()).message || 'Could not save.'); }
                    } catch { alert('Could not save.'); } finally { this.savingQuality = false; }
                },
                async saveDetails() {
                    if (this.savingDetails || !this.active) return;
                    this.savingDetails = true;
                    try {
                        const r = await this.post(@js(url('admin/whatsapp/chats')) + '/' + this.active.id + '/details', {
                            name: this.form.name, phone: this.form.phone, lead_quality: this.form.lead_quality,
                            product_category: this.form.product_category, product_sub_category: this.form.product_sub_category,
                        });
                        if (r.ok) {
                            const d = await r.json();
                            this.active.name = d.name; this.active.initials = d.initials;
                            this.active.phone = d.phone; this.active.country = d.country;
                            this.active.lead_quality = d.lead_quality;
                            this.active.product_category = d.product_category; this.active.product_sub_category = d.product_sub_category;
                            this.applyLead(d);
                            this.rebaseForm();
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
