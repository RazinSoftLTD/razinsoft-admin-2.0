@extends('admin.layouts.app')
@section('title', 'WhatsApp Activity')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">WhatsApp Activity</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Oversight of every connected number — status and full conversation history (read-only).</p>
    </div>

    {{-- Today: what arrived, and how much of it anyone has judged --}}
    @php
        $tone = [
            'conversational' => ['bg-blue-50', 'text-blue-700', 'bg-blue-500'],
            'qualified' => ['bg-emerald-100', 'text-emerald-700', 'bg-emerald-500'],
            'unqualified' => ['bg-rose-100', 'text-rose-700', 'bg-red-500'],
            'unset' => ['bg-gray-100', 'text-gray-500', 'bg-gray-300'],
        ];
        $qualityLabels = \App\Models\WhatsappChat::LEAD_QUALITIES + ['unset' => 'Not judged yet'];
    @endphp

    <div class="mb-6 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-[var(--color-heading)]">{{ $periodLabel }}</h2>
                <p class="mt-0.5 text-xs text-gray-400">
                    @if ($periodFrom->isSameDay($periodTo))
                        {{ $periodFrom->format('l, d F Y') }}
                    @else
                        {{ $periodFrom->format('d M Y') }} – {{ $periodTo->format('d M Y') }}
                        <span class="text-gray-300">·</span> {{ (int) floor($periodFrom->diffInDays($periodTo)) + 1 }} days
                    @endif
                </p>
            </div>

            {{-- The window this whole card covers. A plain GET, so a range can be bookmarked
                 or sent to someone and it opens showing exactly the same figures. --}}
            <div x-data="{ custom: @js($periodKey === 'custom') }" class="flex flex-wrap items-center justify-end gap-1.5">
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'year' => 'This year'] as $key => $label)
                    <a href="{{ route('admin.whatsapp-activity', ['period' => $key]) }}"
                       class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition {{ $periodKey === $key ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)] text-[var(--color-primary)]' : 'border-gray-200 text-[var(--color-muted)] hover:bg-gray-50' }}">{{ $label }}</a>
                @endforeach
                <button type="button" @click="custom = !custom"
                        class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition {{ $periodKey === 'custom' ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)] text-[var(--color-primary)]' : 'border-gray-200 text-[var(--color-muted)] hover:bg-gray-50' }}">Custom</button>

                <form x-show="custom" x-cloak method="GET" action="{{ route('admin.whatsapp-activity') }}" class="flex flex-wrap items-center gap-1.5">
                    <input type="hidden" name="period" value="custom">
                    <input type="date" name="from" value="{{ $periodFrom->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}"
                           class="h-8 rounded-lg border-gray-200 text-xs">
                    <span class="text-xs text-gray-400">to</span>
                    <input type="date" name="to" value="{{ $periodTo->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}"
                           class="h-8 rounded-lg border-gray-200 text-xs">
                    <button class="rounded-lg bg-[var(--color-primary)] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[var(--color-primary-hover)]">Show</button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-100 px-4 py-3">
                <p class="text-2xl font-bold text-[var(--color-heading)]">{{ number_format($today['new_chats']) }}</p>
                <p class="text-[11px] uppercase tracking-wide text-gray-400">New conversations</p>
                <p class="mt-0.5 text-[11px] text-gray-400">first time these numbers wrote</p>
            </div>
            <div class="rounded-xl border border-gray-100 px-4 py-3">
                <p class="text-2xl font-bold text-[var(--color-heading)]">{{ number_format($today['active_chats']) }}</p>
                <p class="text-[11px] uppercase tracking-wide text-gray-400">Chats active</p>
                <p class="mt-0.5 text-[11px] text-gray-400">said anything in this period</p>
            </div>
            <div class="rounded-xl border border-gray-100 px-4 py-3">
                <p class="text-2xl font-bold text-[var(--color-heading)]">{{ number_format($today['messages_in']) }}</p>
                <p class="text-[11px] uppercase tracking-wide text-gray-400">Messages received</p>
            </div>
            <div class="rounded-xl border border-gray-100 px-4 py-3">
                <p class="text-2xl font-bold text-[var(--color-heading)]">{{ number_format($today['messages_out']) }}</p>
                <p class="text-[11px] uppercase tracking-wide text-gray-400">Replies sent</p>
            </div>
        </div>

        {{-- Quality: today beside the whole inbox, since one without the other says nothing
             about whether a quiet day is unusual. --}}
        <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($qualityLabels as $key => $label)
                @php [$bg, $fg, $dot] = $tone[$key]; @endphp
                <div class="rounded-xl px-4 py-3 {{ $bg }}">
                    <p class="flex items-baseline gap-1.5">
                        <span class="h-2 w-2 rounded-full {{ $dot }}"></span>
                        <span class="text-2xl font-bold {{ $fg }}">{{ number_format($todayQuality[$key]['today']) }}</span>
                        <span class="text-[11px] {{ $fg }}">of these</span>
                    </p>
                    <p class="mt-0.5 text-[11px] font-bold uppercase tracking-wide {{ $fg }}">{{ $label }}</p>
                    <p class="text-[11px] {{ $fg }}">{{ number_format($todayQuality[$key]['total']) }} in the whole inbox</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- The conversations themselves, so the numbers above can be checked rather than trusted --}}
    @php
        // Keep the chosen window when switching quality, and vice versa.
        $periodQuery = array_filter([
            'period' => $periodKey,
            'from' => $periodKey === 'custom' ? $periodFrom->format('Y-m-d') : null,
            'to' => $periodKey === 'custom' ? $periodTo->format('Y-m-d') : null,
        ]);
    @endphp

    <div class="mb-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">New conversations — {{ strtolower($periodLabel) }}</h2>
            <div class="flex flex-wrap items-center gap-1.5">
                <a href="{{ route('admin.whatsapp-activity', $periodQuery) }}"
                   class="rounded-full px-2.5 py-1 text-[11px] font-semibold transition {{ $qualityFilter === 'all' ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">All ({{ number_format($newTotal) }})</a>
                @foreach ($qualityLabels as $key => $label)
                    <a href="{{ route('admin.whatsapp-activity', $periodQuery + ['quality' => $key]) }}"
                       class="rounded-full px-2.5 py-1 text-[11px] font-semibold transition {{ $qualityFilter === $key ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">{{ $label }} ({{ number_format($todayQuality[$key]['today']) }})</a>
                @endforeach
            </div>
        </div>

        @if ($todayChats->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-gray-400">Nobody new wrote in this period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-2 font-semibold">Time</th>
                            <th class="px-5 py-2 font-semibold">Contact</th>
                            <th class="px-5 py-2 font-semibold">Number</th>
                            <th class="px-5 py-2 font-semibold">Quality</th>
                            <th class="px-5 py-2 font-semibold">Last message</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($todayChats as $c)
                            @php
                                $key = $c->lead_quality ?: 'unset';
                                [$bg, $fg, $dot] = $tone[$key];
                            @endphp
                            @php $inbox = route('admin.whatsapp.index', ['account' => $c->account_id, 'chat' => $c->id]); @endphp
                            <tr class="cursor-pointer hover:bg-gray-50" onclick="window.location='{{ $inbox }}'" title="Open this conversation in WhatsApp">
                                <td class="whitespace-nowrap px-5 py-2.5 text-gray-400">{{ $c->created_at?->format('d M, h:i A') }}</td>
                                <td class="px-5 py-2.5">
                                    <a href="{{ $inbox }}" onclick="event.stopPropagation()" class="font-semibold text-[var(--color-heading)] hover:underline">{{ $c->displayName() }}</a>
                                    <p class="text-[11px] text-gray-400">{{ $c->account?->name }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-2.5 text-[var(--color-muted)]">{{ $c->phoneLabel() }}</td>
                                <td class="px-5 py-2.5">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $bg }} {{ $fg }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
                                        {{ $qualityLabels[$key] }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5">
                                    <p class="max-w-md truncate text-[var(--color-muted)]">{{ \Illuminate\Support\Str::limit($c->last_message_preview, 70) ?: '—' }}</p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($todayChats->hasPages())
                <div class="border-t border-gray-100 px-5 py-3">
                    {{ $todayChats->onEachSide(1)->links() }}
                </div>
            @else
                <p class="border-t border-gray-100 px-5 py-2.5 text-[11px] text-gray-400">{{ $todayChats->count() }} shown</p>
            @endif
        @endif
    </div>

    @if ($accounts->isEmpty())
        <div class="rounded-xl border border-gray-100 bg-white p-10 text-center text-sm text-gray-400">No WhatsApp numbers yet.</div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($accounts as $acc)
                @php $s = $stats[$acc->id]; @endphp
                <a href="{{ route('admin.whatsapp-activity.show', $acc) }}" class="block rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-emerald-200 hover:shadow-lg">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-white" style="background: {{ $acc->color }}">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Z"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-[var(--color-heading)]">{{ $acc->name }}</p>
                            <p class="truncate text-xs text-gray-400">{{ $acc->display_number ? '+'.$acc->display_number : 'not connected' }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $acc->isConnected() ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $acc->isConnected() ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                            {{ $acc->isConnected() ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-gray-50 py-2">
                            <p class="text-base font-bold text-[var(--color-heading)]">{{ number_format($s['chats']) }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-gray-400">Chats</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 py-2">
                            <p class="text-base font-bold text-[var(--color-heading)]">{{ number_format($s['messages']) }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-gray-400">Messages</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 py-2">
                            <p class="text-base font-bold {{ $s['unread'] ? 'text-emerald-600' : 'text-[var(--color-heading)]' }}">{{ number_format($s['unread']) }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-gray-400">Unread</p>
                        </div>
                    </div>

                    {{-- Team responsiveness --}}
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <div class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2">
                            <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4l2.5 1.5"/></svg>
                            <span class="min-w-0">
                                <span class="block text-[10px] uppercase tracking-wide text-gray-400">Avg. response</span>
                                <span class="block text-sm font-bold text-[var(--color-heading)]">{{ $s['avg_response'] ?? '—' }}</span>
                            </span>
                        </div>
                        <div class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2">
                            <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                            <span class="min-w-0">
                                <span class="block text-[10px] uppercase tracking-wide text-gray-400">Response rate</span>
                                <span class="block text-sm font-bold {{ isset($s['response_rate']) && $s['response_rate'] >= 70 ? 'text-emerald-600' : 'text-[var(--color-heading)]' }}">{{ isset($s['response_rate']) ? $s['response_rate'].'%' : '—' }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between text-[11px] text-gray-400">
                        <span>{{ $acc->users->count() }} agent{{ $acc->users->count() === 1 ? '' : 's' }}</span>
                        <span>Last: {{ $s['last_at'] ? \Illuminate\Support\Carbon::parse($s['last_at'])->diffForHumans() : '—' }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
