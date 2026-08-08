@extends('admin.layouts.app')
@section('title', 'WhatsApp Button')

@section('content')
    @include('admin.whatsapp._activity-tabs', ['active' => 'button'])

    {{-- Build a link --}}
    <div class="mb-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-bold text-[var(--color-heading)]">Create a link</h2>
        <p class="mt-1 text-xs text-[var(--color-muted)]">
            The link points at this panel and forwards to WhatsApp — that hop is what makes the clicks countable.
            A plain wa.me address goes straight to WhatsApp and tells us nothing.
        </p>

        <form method="POST" action="{{ route('admin.whatsapp-links.store') }}" class="mt-4 grid gap-3 sm:grid-cols-12">
            @csrf
            <div class="sm:col-span-4">
                <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">WhatsApp number <span class="text-red-500">*</span></label>
                <input type="text" name="number" required value="{{ old('number') }}" placeholder="01711257498"
                       class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
            </div>
            <div class="sm:col-span-8">
                <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Label</label>
                <input type="text" name="label" value="{{ old('label') }}" placeholder="Facebook ad — August"
                       class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
            </div>

            <div class="sm:col-span-12">
                <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">First message</label>
                @include('admin.whatsapp-links._message-editor', ['value' => old('message')])
            </div>

            <div class="sm:col-span-12">
                <button class="h-11 rounded-lg bg-[var(--color-primary)] px-6 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    Create link
                </button>
            </div>
        </form>
    </div>

    {{-- Window --}}
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-2">
        <div>
            <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Period</label>
            <select name="range" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                @foreach ($ranges as $key => $label)
                    <option value="{{ $key }}" @selected($range === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="h-10 rounded-lg border border-gray-200 px-2 text-sm">
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="h-10 rounded-lg border border-gray-200 px-2 text-sm">
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Link</label>
            <select name="link" class="h-10 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                <option value="">Every link</option>
                @foreach ($links as $l)
                    <option value="{{ $l->id }}" @selected(request('link') == $l->id)>{{ $l->label ?: $l->number }}</option>
                @endforeach
            </select>
        </div>
        {{-- Dates only apply on a custom range; the select posts the rest. --}}
        <button name="range" value="custom" class="h-10 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply dates</button>
        <a href="{{ route('admin.whatsapp-links') }}" class="h-10 rounded-lg border border-gray-200 px-4 text-sm font-semibold leading-10 text-[var(--color-muted)] hover:bg-gray-50">Clear</a>
    </form>

    {{-- Totals --}}
    <div class="mb-6 flex flex-wrap gap-4">
        <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2a10 10 0 0 0-8.6 15L2 22l5-1.4A10 10 0 1 0 12 2Z"/></svg>
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Clicks — {{ $ranges[$range] ?? 'window' }}</p>
                <p class="text-lg font-bold text-[var(--color-heading)]">{{ number_format($totalClicks) }}</p>
                @if ($from || $to)
                    <p class="text-xs text-[var(--color-muted)]">
                        {{ optional($from)->format('d M Y') ?: 'start' }} — {{ optional($to)->format('d M Y') ?: 'now' }}
                    </p>
                @endif
            </div>
        </div>

        @foreach ($byDevice as $device => $hits)
            <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-gray-100 text-gray-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M11 18h2"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ucfirst($device ?: 'unknown') }}</p>
                    <p class="text-lg font-bold text-[var(--color-heading)]">{{ number_format($hits) }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Clicks per day --}}
    @if ($byDay->count())
        <div class="mb-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Clicks per day</h2>
            @php $peak = max(1, (int) $byDay->max('hits')); @endphp
            <div class="flex items-end gap-1 overflow-x-auto" style="height: 140px;">
                @foreach ($byDay as $d)
                    <div class="flex min-w-[14px] flex-1 flex-col items-center justify-end gap-1" title="{{ $d->day }} — {{ $d->hits }} click(s)">
                        <span class="text-[10px] font-semibold text-[var(--color-muted)]">{{ $d->hits }}</span>
                        <span class="w-full rounded-t bg-[var(--color-primary)]" style="height: {{ max(4, round($d->hits / $peak * 100)) }}px"></span>
                        <span class="text-[9px] text-gray-400">{{ \Illuminate\Support\Carbon::parse($d->day)->format('d/m') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Links --}}
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm lg:col-span-2" x-data="{ editing: null }">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Your links</h2>
                <p class="text-xs text-[var(--color-muted)]">
                    Copy a link and put it in an ad, a post or an email signature. The first one is the site's own
                    floating button — edit it and razinsoft.com follows.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Link</th>
                            <th class="px-5 py-3 font-semibold">Goes to</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ $ranges[$range] ?? 'Window' }}</th>
                            <th class="px-5 py-3 text-right font-semibold">All time</th>
                            <th class="px-5 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($links as $l)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <span class="block font-medium text-[var(--color-heading)]">{{ $l->label ?: 'Untitled' }}</span>
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ $l->shortUrl() }}'); this.textContent='Copied ✓'; setTimeout(()=>this.textContent='{{ $l->shortUrl() }}',1500);"
                                            class="mt-0.5 block font-mono text-xs text-[var(--color-primary)] hover:underline" title="Click to copy">{{ $l->shortUrl() }}</button>
                                    @if ($l->isSiteButton())
                                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-600">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12a9.5 9.5 0 1 0 19 0 9.5 9.5 0 0 0-19 0Zm0 0h19M12 2.5c2.5 2.6 2.5 16.4 0 19M12 2.5c-2.5 2.6-2.5 16.4 0 19"/></svg>
                                            Website button — default
                                        </span>
                                    @endif
                                    @unless ($l->is_active)
                                        <span class="mt-1 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500">Retired — not counting</span>
                                    @endunless
                                </td>
                                <td class="px-5 py-3">
                                    <span class="block text-[var(--color-heading)]">{{ $l->number }}</span>
                                    @if ($l->message)
                                        <span class="block max-w-xs truncate text-xs text-[var(--color-muted)]">“{{ $l->message }}”</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span class="inline-flex rounded-full bg-[var(--color-primary-soft)] px-2.5 py-0.5 text-xs font-bold text-[var(--color-primary)]">{{ number_format($l->clicks_window) }}</span>
                                </td>
                                <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ number_format($l->clicks_total) }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ $l->whatsappUrl() }}" target="_blank" rel="noopener" title="Open the chat"
                                           class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-emerald-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2a10 10 0 0 0-8.6 15L2 22l5-1.4A10 10 0 1 0 12 2Z"/></svg>
                                        </a>
                                        <button type="button" @click="editing = {{ $l->id }}" title="Edit number or message — the link stays the same"
                                                class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                                        </button>
                                        @unless ($l->isSiteButton())
                                        <form method="POST" action="{{ route('admin.whatsapp-links.toggle', $l) }}">
                                            @csrf
                                            <button class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="{{ $l->is_active ? 'Stop counting' : 'Start counting' }}">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 6v6l4 2"/><circle cx="12" cy="12" r="9"/></svg>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.whatsapp-links.destroy', $l) }}"
                                              onsubmit="return confirm('Delete this link and its {{ $l->clicks_total }} recorded click(s)?')">
                                            @csrf @method('DELETE')
                                            <button class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                            </button>
                                        </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-12 text-center text-gray-400">No links yet — create one above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Edit: changes where the link points, never the link itself. --}}
            @foreach ($links as $l)
                <div x-show="editing === {{ $l->id }}" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                     @click.self="editing = null" @keydown.escape.window="editing = null">
                    <div class="max-h-full w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                        <h3 class="text-lg font-bold text-[var(--color-heading)]">Edit link</h3>
                        <p class="mt-1 text-xs text-[var(--color-muted)]">
                            The short link stays <span class="font-mono text-[var(--color-primary)]">{{ $l->shortUrl() }}</span> —
                            anything already sharing it keeps working, it just points somewhere new.
                        </p>
                        @if ($l->isSiteButton())
                            <p class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700">
                                This is the floating button on every page of razinsoft.com. Saving changes it there straight away.
                            </p>
                        @endif

                        <form method="POST" action="{{ route('admin.whatsapp-links.update', $l) }}" class="mt-4 space-y-3">
                            @csrf @method('PUT')
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">WhatsApp number <span class="text-red-500">*</span></label>
                                <input type="text" name="number" required value="{{ $l->number }}"
                                       class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Label</label>
                                <input type="text" name="label" value="{{ $l->label }}"
                                       class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">First message</label>
                                @include('admin.whatsapp-links._message-editor', ['value' => $l->message])
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" @click="editing = null"
                                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                                <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Countries --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-1 text-sm font-bold text-[var(--color-heading)]">Where the clicks came from</h2>
            <p class="mb-4 text-xs text-[var(--color-muted)]">Top countries in this period.</p>
            @php $topHits = max(1, (int) ($byCountry->max('hits') ?? 1)); @endphp
            <div class="space-y-3">
                @forelse ($byCountry as $c)
                    <div>
                        <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-medium text-[var(--color-heading)]">{{ $c->country }}</span>
                            <span class="font-bold text-[var(--color-heading)]">{{ number_format($c->hits) }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ round($c->hits / $topHits * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No clicks in this period.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Individual clicks --}}
    <div class="mt-6 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Click log</h2>
            <p class="text-xs text-[var(--color-muted)]">Every click in this period, newest first.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">When</th>
                        <th class="px-5 py-3 font-semibold">Link</th>
                        <th class="px-5 py-3 font-semibold">Country</th>
                        <th class="px-5 py-3 font-semibold">Device</th>
                        <th class="px-5 py-3 font-semibold">Came from</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recent as $click)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-[var(--color-muted)]">
                                {{ optional($click->clicked_at)->format('d M Y, h:i A') }}
                                <span class="block text-xs text-gray-400">{{ optional($click->clicked_at)->diffForHumans() }}</span>
                            </td>
                            <td class="px-5 py-3 text-[var(--color-heading)]">{{ $click->link?->label ?: $click->link?->code ?: '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $click->country ?: '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ ucfirst($click->device ?: '—') }}</td>
                            <td class="px-5 py-3">
                                @if ($click->referrer)
                                    <span class="block max-w-xs truncate font-mono text-xs text-[var(--color-muted)]">{{ $click->referrer }}</span>
                                @else
                                    <span class="text-xs text-gray-400">direct</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-gray-400">No clicks in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $recent->links() }}</div>
@endsection
