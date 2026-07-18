@extends('admin.layouts.app')
@section('title', 'WhatsApp Activity · '.$account->name)

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.whatsapp-activity') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> All numbers
            </a>
            <h1 class="mt-1 text-xl font-bold text-[var(--color-heading)]">{{ $account->name }}
                <span class="text-sm font-normal text-gray-400">{{ $account->display_number ? '+'.$account->display_number : '' }}</span>
            </h1>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-semibold {{ $account->isConnected() ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
            <span class="h-2 w-2 rounded-full {{ $account->isConnected() ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
            {{ $account->isConnected() ? 'Active' : 'Inactive' }}
        </span>
    </div>

    <div x-data="waActivity()" class="flex h-[calc(100dvh-13rem)] overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        {{-- chats --}}
        <aside class="flex w-80 shrink-0 flex-col border-r border-gray-100">
            <div class="border-b border-gray-100 p-3">
                <input type="text" x-model="q" placeholder="Search…" class="h-9 w-full rounded-lg border-gray-200 text-sm focus:border-emerald-400 focus:ring-emerald-400">
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                @foreach ($chats as $c)
                    <button type="button" @click="open({{ $c->id }}, @js($c->displayName()))"
                            x-show="match(@js(mb_strtolower($c->displayName().' '.$c->wa_id.' '.$c->last_message_preview)))"
                            class="flex w-full items-start gap-3 border-b border-gray-50 px-4 py-3 text-left transition hover:bg-gray-50"
                            :class="activeId === {{ $c->id }} ? 'bg-emerald-50' : ''">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-100 text-[11px] font-bold text-emerald-700">{{ \Illuminate\Support\Str::of($c->displayName())->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('') }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-bold text-[var(--color-heading)]">{{ $c->displayName() }}</span>
                                <span class="shrink-0 text-[10px] text-gray-400">{{ $c->last_message_at?->diffForHumans(short: true) }}</span>
                            </span>
                            <span class="mt-0.5 block truncate text-xs text-gray-500">{{ $c->last_message_preview ?: '—' }}</span>
                        </span>
                    </button>
                @endforeach
                @if ($chats->isEmpty())<p class="py-10 text-center text-sm text-gray-300">No conversations.</p>@endif
            </div>
        </aside>

        {{-- messages (read-only) --}}
        <section class="flex min-w-0 flex-1 flex-col bg-[#efeae2]">
            <template x-if="!activeId">
                <div class="grid flex-1 place-items-center text-sm text-gray-400">Select a conversation to view its history.</div>
            </template>
            <template x-if="activeId">
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="flex items-center gap-2 border-b border-gray-200 bg-white px-5 py-3">
                        <span class="text-sm font-bold text-[var(--color-heading)]" x-text="activeName"></span>
                        <span class="text-xs text-gray-400" x-text="activeWaId"></span>
                        <span class="ml-auto text-[11px] font-medium text-gray-400">Read-only</span>
                    </div>
                    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto px-6 py-5" x-ref="thread">
                        <template x-if="loading"><p class="text-center text-xs text-gray-400">Loading…</p></template>
                        <template x-for="m in messages" :key="m.id">
                            <div class="flex" :class="m.direction === 'out' ? 'justify-end' : 'justify-start'">
                                <div class="max-w-[70%] rounded-lg px-3 py-1.5 text-sm shadow-sm" :class="m.direction === 'out' ? 'bg-[#e7ffdb] text-gray-800' : 'bg-white text-gray-800'" style="max-width:70%">
                                    <template x-if="m.sender_name && m.direction === 'in'"><span class="mb-0.5 block text-xs font-bold text-indigo-600" x-text="m.sender_name"></span></template>
                                    <template x-if="m.media && m.type === 'image'"><a :href="m.media" target="_blank"><img :src="m.media" class="mb-1 max-h-64 rounded" style="max-width:220px"></a></template>
                                    <template x-if="m.media && (m.type === 'video' || m.type === 'audio' || m.type === 'document')"><a :href="m.media" target="_blank" class="mb-1 block text-xs font-medium text-emerald-700 underline" x-text="m.media_name || ('Open ' + m.type)"></a></template>
                                    <span x-show="m.deleted" class="text-sm italic text-gray-400">Deleted message</span>
                                    <span x-show="m.body" x-text="m.body" class="whitespace-pre-line break-words"></span>
                                    <span class="mt-0.5 block text-right text-[10px] text-gray-400">
                                        <span x-show="m.edited" class="italic">edited · </span>
                                        <span x-show="m.agent" x-text="m.agent + ' · '"></span>
                                        <span x-text="m.at"></span>
                                        <span x-show="m.reaction || m.my_reaction" class="ml-1" x-text="(m.my_reaction || '') + (m.reaction || '')"></span>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </section>
    </div>

    <script>
        function waActivity() {
            return {
                q: '', activeId: null, activeName: '', activeWaId: '', messages: [], loading: false,
                match(hay) { return !this.q || hay.includes(this.q.toLowerCase()); },
                async open(id, name) {
                    this.activeId = id; this.activeName = name; this.messages = []; this.loading = true;
                    try {
                        const r = await fetch(@js(url('admin/whatsapp-activity/'.$account->id.'/chats')) + '/' + id);
                        const d = await r.json();
                        this.activeWaId = d.wa_id; this.messages = d.messages;
                        this.$nextTick(() => { const t = this.$refs.thread; if (t) t.scrollTop = t.scrollHeight; });
                    } catch {} finally { this.loading = false; }
                },
            };
        }
    </script>
@endsection
