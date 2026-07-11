<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-100 bg-white px-4 sm:px-6">
    {{-- Mobile: open sidebar --}}
    <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-50 lg:hidden" @click="sidebar = true" aria-label="Open menu">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
    {{-- Desktop: collapse / expand sidebar --}}
    <button type="button" class="hidden rounded-lg p-2 text-gray-500 hover:bg-gray-50 lg:inline-flex" @click="collapsed = !collapsed" :title="collapsed ? 'Open sidebar' : 'Collapse sidebar'" :aria-label="collapsed ? 'Open sidebar' : 'Collapse sidebar'">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>

    <h1 class="text-base font-bold text-[var(--color-heading)] sm:text-lg">@yield('title', 'Dashboard')</h1>

    <div class="ml-auto flex items-center gap-2">
        <a href="{{ config('app.frontend_url', config('services.frontend_url')) }}" target="_blank" rel="noopener"
           class="hidden items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-muted)] hover:bg-gray-50 sm:flex">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5h5v5M19 5l-9 9M19 13v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/></svg>
            View site
        </a>

        {{-- ───── Teams message notifications (live + sound) ───── --}}
        @php
            $me = auth()->user();
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
                    <a href="{{ route('admin.chat.index') }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">Open Teams</a>
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
