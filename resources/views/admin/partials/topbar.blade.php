<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-100 bg-white px-4 sm:px-6">
    {{-- Mobile: open sidebar --}}
    <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-50 lg:hidden" @click="sidebar = true" aria-label="Open menu">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
    {{-- Desktop: collapse / expand sidebar --}}
    <button type="button" class="hidden rounded-lg p-2 text-gray-500 hover:bg-gray-50 lg:inline-flex" @click="(collapsed || forceCollapse) ? (collapsed = false, forceCollapse = false) : (collapsed = true)" :title="(collapsed || forceCollapse) ? 'Open sidebar' : 'Collapse sidebar'" :aria-label="(collapsed || forceCollapse) ? 'Open sidebar' : 'Collapse sidebar'">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>

    <h1 class="text-base font-bold text-[var(--color-heading)] sm:text-lg">@yield('title', 'Dashboard')</h1>

    <div class="ml-auto flex items-center gap-2">
        @php $me = auth()->user(); @endphp

        {{-- ───── Running task timer (global — only one can run at a time) ───── --}}
        @php
            $liveTimer = \App\Models\ProjectTaskTimer::with('task:id,title,project_id')
                ->where('user_id', $me->id)->whereNotNull('started_at')->first();
        @endphp
        @if ($liveTimer && $liveTimer->task)
            <div class="mr-1 flex items-center gap-2 rounded-xl border border-gray-100 bg-white px-3 py-1.5 shadow-sm">
                <a href="{{ route('admin.tasks.show', $liveTimer->task_id) }}" class="flex min-w-0 items-center gap-2" title="{{ $liveTimer->task->title }}">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-amber-50 text-amber-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                    </span>
                    <span class="hidden min-w-0 sm:block">
                        <span class="block truncate text-[11px] font-semibold text-[var(--color-muted)]" style="max-width:8rem">{{ $liveTimer->task->title }}</span>
                        <span class="block text-sm font-bold text-[var(--color-heading)]"
                              x-data="topbarTicker({{ $liveTimer->elapsedSeconds() }})" x-text="clock" x-init="run()">{{ $liveTimer->clock() }}</span>
                    </span>
                    <span class="text-sm font-bold text-[var(--color-heading)] sm:hidden"
                          x-data="topbarTicker({{ $liveTimer->elapsedSeconds() }})" x-text="clock" x-init="run()">{{ $liveTimer->clock() }}</span>
                </a>
                <form method="POST" action="{{ route('admin.tasks.timer.pause', $liveTimer->task_id) }}" data-turbo="false">
                    @csrf
                    <button title="Pause" class="grid h-8 w-8 place-items-center rounded-lg border border-gray-200 text-[var(--color-primary)] transition hover:bg-gray-50">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><rect x="7" y="5" width="3.5" height="14" rx="1"/><rect x="13.5" y="5" width="3.5" height="14" rx="1"/></svg>
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.tasks.timer.stop', $liveTimer->task_id) }}" data-turbo="false">
                    @csrf
                    <button title="Stop and log" class="grid h-8 w-8 place-items-center rounded-lg bg-red-500 text-white transition hover:opacity-80">
                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><rect x="5" y="5" width="14" height="14" rx="2"/></svg>
                    </button>
                </form>
            </div>
        @endif

        {{-- ───── WhatsApp (only for users with access; badge scoped to their numbers) ───── --}}
        @if ($me->hasPermission('whatsapp.view'))
            @php
                $waIds = \App\Models\WhatsappAccount::accessibleBy($me)->pluck('id');
                $waUnread = $waIds->isEmpty() ? 0 : \App\Models\WhatsappChat::whereIn('account_id', $waIds)->where('unread_count', '>', 0)->count();
            @endphp
            <a href="{{ route('admin.whatsapp.index') }}" class="relative grid h-10 w-10 place-items-center rounded-lg text-emerald-600 hover:bg-emerald-50" title="WhatsApp" aria-label="WhatsApp">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm5.5 14.2c-.2.6-1.2 1.2-1.7 1.2-.4 0-1 .1-3.4-.9-2.9-1.2-4.7-4.1-4.9-4.3-.1-.2-1.1-1.5-1.1-2.8 0-1.3.7-2 .9-2.2.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6c-.2.2-.3.4-.1.7.2.3.9 1.4 1.9 2.3 1.3 1.1 2.3 1.5 2.6 1.6.2.1.4.1.6-.1l.7-.9c.2-.2.4-.2.6-.1l1.9.9c.3.1.5.2.5.4.1.2.1.9-.1 1.5Z"/></svg>
                <span data-nav-badge="whatsapp" class="{{ $waUnread ? '' : 'hidden' }} absolute -right-0.5 -top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-emerald-500 px-1 text-[10px] font-bold text-white ring-2 ring-white">{{ $waUnread > 99 ? '99+' : $waUnread }}</span>
            </a>
        @endif

        {{-- ───── Teams message notifications (live + sound) ───── --}}
        @php
            $chatUnread = \App\Http\Controllers\Admin\ChatController::unreadTotal($me);
            $chatRecent = \App\Http\Controllers\Admin\ChatController::recentUnread($me);
        @endphp
        <div class="relative" x-data="chatBell({ count: {{ (int) $chatUnread }}, items: @js($chatRecent) })" x-init="init()">
            <button type="button" @click="open = !open" class="relative grid h-10 w-10 place-items-center rounded-lg text-gray-500 hover:bg-gray-50" aria-label="Messages">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v10Z"/></svg>
                <span x-show="count > 0" x-cloak x-text="count > 99 ? '99+' : count"
                      class="absolute -right-0.5 -top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white ring-2 ring-white"></span>
            </button>

            <div x-show="open" @click.outside="open = false" x-cloak x-transition
                 class="absolute right-0 mt-2 w-80 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                    <p class="text-sm font-bold text-[var(--color-heading)]">Messages</p>
                    <a href="{{ route('admin.chat.index') }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">Open Messenger</a>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <template x-for="it in items" :key="it.id">
                        <a :href="it.url" class="flex items-start gap-3 border-b border-gray-50 px-4 py-3 hover:bg-gray-50">
                            <template x-if="it.avatar">
                                <img :src="it.avatar" class="h-9 w-9 shrink-0 rounded-full object-cover" alt="">
                            </template>
                            <template x-if="!it.avatar">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]"
                                      x-text="(it.title || '?').charAt(0).toUpperCase()"></span>
                            </template>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-semibold text-[var(--color-heading)]" x-text="it.title"></span>
                                    <span class="shrink-0 text-[11px] text-gray-400" x-text="it.time"></span>
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-[var(--color-muted)]">
                                    <span x-show="it.is_group" class="font-medium" x-text="(it.author ? it.author + ': ' : '')"></span><span x-text="it.preview"></span>
                                </span>
                            </span>
                            <span x-show="it.unread > 0" class="mt-1 grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-red-500 px-1.5 text-[11px] font-bold text-white" x-text="it.unread"></span>
                        </a>
                    </template>
                    <p x-show="items.length === 0" class="px-4 py-8 text-center text-sm text-[var(--color-muted)]">No new messages.</p>
                </div>
            </div>
        </div>

        <div class="relative" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-gray-50">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]">
                    {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                </span>
                <span class="hidden text-left sm:block">
                    <span class="block text-sm font-semibold leading-tight text-[var(--color-heading)]">{{ auth()->user()->name ?? 'Admin' }}</span>
                    <span class="block text-xs leading-tight text-gray-400">{{ auth()->user()->email ?? '' }}</span>
                </span>
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak
                 class="absolute right-0 mt-2 w-48 overflow-hidden rounded-xl border border-gray-100 bg-white py-1 shadow-lg">
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 hover:bg-red-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0 4-4m-4 4 4 4M13 16v1a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v1"/></svg>
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<script>
    // Top-bar message bell: live updates + notification sound, driven by the global Reverb ping.
    function chatBell(initial) {
        return {
            open: false,
            count: initial.count || 0,
            items: initial.items || [],
            chatBase: @js(url('admin/chat')),

            init() {
                window.Razin = window.Razin || {};
                window.Razin.onChatPing = (d) => this.onPing(d);
                // Opening a conversation clears its unread everywhere (row + bell + sidebar).
                window.Razin.markConversationRead = (cid) => this.clearConversation(cid);
                this.syncNav();
            },

            beep() {
                if (window.Razin && typeof window.Razin.playMessageSound === 'function') window.Razin.playMessageSound();
            },

            // Keep the sidebar "Teams" nav badge in lock-step with the bell count.
            syncNav() {
                const b = document.querySelector('[data-nav-badge="teams"]');
                if (!b) return;
                b.textContent = this.count > 99 ? '99+' : this.count;
                b.classList.toggle('hidden', this.count === 0);
            },

            recount() {
                this.count = this.items.reduce((s, it) => s + (it.unread || 0), 0);
                this.syncNav();
            },

            clearConversation(cid) {
                this.items = this.items.filter((it) => Number(it.id) !== Number(cid));
                this.recount();
            },

            onPing(d) {
                const cid = Number(d.conversation_id);
                const isGroup = d.conv_type === 'group';
                const title = isGroup ? (d.conv_name || 'Group') : (d.author || 'Direct message');
                // Upsert this conversation at the top of the list.
                const existing = this.items.find((it) => Number(it.id) === cid);
                if (existing) {
                    existing.author = d.author;
                    existing.preview = d.preview || existing.preview;
                    existing.time = 'now';
                    existing.unread = (existing.unread || 0) + 1;
                    this.items = [existing, ...this.items.filter((it) => Number(it.id) !== cid)];
                } else {
                    this.items = [{
                        id: cid, title, is_group: isGroup, author: d.author,
                        avatar: isGroup ? null : (d.avatar || null),
                        preview: d.preview || '', time: 'now', unread: 1,
                        url: this.chatBase + '/' + cid,
                    }, ...this.items];
                }
                this.recount();
                this.beep();
            },
        };
    }
</script>

<script>
    // Live clock for the topbar timer.
    function topbarTicker(startSeconds) {
        return {
            clock: '00:00:00',
            run() {
                const from = Date.now();
                const paint = () => {
                    const s = startSeconds + Math.floor((Date.now() - from) / 1000);
                    const p = n => String(n).padStart(2, '0');
                    this.clock = p(Math.floor(s / 3600)) + ':' + p(Math.floor(s / 60) % 60) + ':' + p(s % 60);
                };
                paint();
                setInterval(paint, 1000);
            },
        };
    }
</script>
