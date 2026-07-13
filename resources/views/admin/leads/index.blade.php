@extends('admin.layouts.app')
@section('title', 'All Leads')

@php
    $statusBadge = [
        'new' => 'bg-blue-50 text-blue-700',
        'qualified' => 'bg-emerald-50 text-emerald-700',
        'unqualified' => 'bg-red-50 text-red-600',
    ];
    $priorityBadge = [
        'high' => 'bg-red-50 text-red-600',
        'medium' => 'bg-amber-50 text-amber-700',
        'low' => 'bg-gray-100 text-gray-600',
    ];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">All Leads</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; Leads &rsaquo; All Leads</p>
        </div>
        @if (auth()->user()->allows('leads', 'create'))
        <a href="{{ route('admin.leads.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add New Lead
        </a>
        @endif
    </div>

    @if (session('import_skipped') && count(session('import_skipped')))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <p class="font-semibold">Skipped rows:</p>
            <ul class="mt-1 list-inside list-disc">@foreach (session('import_skipped') as $s)<li>{{ $s }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Stat cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($stats as $s)
            <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg {{ $s['tone'] }}" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $s['icon'] }}"/></svg>
                </span>
                <div>
                    <p class="text-xs text-[var(--color-muted)]">{{ $s['label'] }}</p>
                    <p class="text-xl font-bold text-[var(--color-heading)]">{{ $s['value'] }}</p>
                    @if (!is_null($s['delta']))
                        <p class="text-xs {{ $s['delta'] >= 0 ? 'text-emerald-600' : 'text-red-500' }}">{{ $s['delta'] >= 0 ? '↑' : '↓' }} {{ abs($s['delta']) }}% vs last month</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Leads by Country — qualified vs unqualified breakdown --}}
    @if ($countryBreakdown->count())
        <div x-data="{ open: false }" class="mt-5 rounded-xl border border-gray-100 bg-white shadow-sm">
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between px-5 py-4">
                <span class="flex items-center gap-2 text-sm font-bold text-[var(--color-heading)]">
                    <svg class="h-4 w-4 text-[var(--color-primary)]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20ZM2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/></svg>
                    Leads by Country
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ $countryBreakdown->count() }}</span>
                </span>
                <svg class="h-4 w-4 text-gray-400 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
            </button>
            <div x-show="open" x-cloak class="max-h-72 overflow-y-auto border-t border-gray-100">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-2.5 font-semibold">Country</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Qualified</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Unqualified</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($countryBreakdown as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-2.5 font-medium text-[var(--color-heading)]">
                                    <a href="{{ request()->fullUrlWithQuery(['country' => $row->country_name === 'Unknown' ? '' : $row->country_name, 'page' => null]) }}" class="hover:text-[var(--color-primary)] hover:underline">{{ $row->country_name }}</a>
                                </td>
                                <td class="px-5 py-2.5 text-right"><span class="font-semibold text-emerald-600">{{ $row->qualified }}</span></td>
                                <td class="px-5 py-2.5 text-right"><span class="font-semibold text-red-500">{{ $row->unqualified }}</span></td>
                                <td class="px-5 py-2.5 text-right font-semibold text-[var(--color-heading)]">{{ $row->total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Filters: open/close right-side drawer --}}
    @php $activeFilters = count(array_filter(request()->only(['search', 'status', 'source', 'assigned', 'priority', 'date_range', 'from', 'to', 'country']), fn ($v) => $v !== null && $v !== '')); @endphp
    <div x-data="{ filtersOpen: false }" @keydown.escape.window="filtersOpen = false">
        {{-- Toolbar: Show entries · Export · Import · Filters — all on one line --}}
        <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-[var(--color-muted)]">
                Showing {{ $leads->count() ? $leads->firstItem() : 0 }} to {{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }} results
            </p>
            <div class="flex flex-wrap items-center gap-2">
                {{-- Show entries --}}
                <form method="GET" class="flex items-center gap-2 text-sm text-[var(--color-muted)]">
                    @foreach (request()->except('per_page', 'page') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    Show
                    <select name="per_page" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                        @foreach ([10, 25, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach
                    </select>
                    entries
                </form>
                {{-- Export --}}
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                    Export
                </a>
                {{-- Import --}}
                <a href="{{ route('admin.leads.import.form') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 15V3m0 0 4 4m-4-4-4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                    Import
                </a>
                {{-- Filters (opens drawer) --}}
                <button type="button" @click="filtersOpen = true"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M6 12h12M9 18h6"/></svg>
                    Filters
                    @if ($activeFilters)<span class="grid h-5 min-w-5 place-items-center rounded-full bg-[var(--color-primary)] px-1.5 text-[11px] font-bold text-white">{{ $activeFilters }}</span>@endif
                </button>
                @if ($activeFilters)
                    <a href="{{ route('admin.leads.index') }}" title="Clear filters" class="text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">Clear</a>
                @endif
            </div>
        </div>

        {{-- Drawer --}}
        <div x-show="filtersOpen" x-cloak class="fixed inset-0 z-40">
            <div x-show="filtersOpen" x-transition.opacity @click="filtersOpen = false" class="absolute inset-0 bg-black/30"></div>
            <div x-show="filtersOpen"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 class="absolute right-0 top-0 flex h-full w-80 max-w-full flex-col bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Filter Leads</h2>
                    <button type="button" @click="filtersOpen = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-gray-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <form id="lead-filters" method="GET" class="flex-1 space-y-4 overflow-y-auto p-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Search</label>
                        <input name="search" value="{{ request('search') }}" placeholder="Name, email, phone…" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Status</label>
                        <select name="status" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Status</option>
                            @foreach (\App\Models\Lead::STATUSES as $k => $label)
                                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Source</label>
                        <select name="source" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Sources</option>
                            @foreach (\App\Models\Lead::sourceOptions() as $s)
                                <option value="{{ $s }}" @selected(request('source') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Assigned To</label>
                        <select name="assigned" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Users</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(request('assigned') == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Priority</label>
                        <select name="priority" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Priority</option>
                            @foreach (\App\Models\Lead::PRIORITIES as $k => $label)
                                <option value="{{ $k }}" @selected(request('priority') === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Created-date filter --}}
                    <div class="border-t border-gray-100 pt-4">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Created Date</label>
                        <select name="date_range" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            @foreach (['' => 'All time', 'today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $rk => $rl)
                                <option value="{{ $rk }}" @selected(request('date_range') === $rk)>{{ $rl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Custom from</label>
                            <input type="date" name="from" value="{{ request('from') }}" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Custom to</label>
                            <input type="date" name="to" value="{{ request('to') }}" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Country</label>
                        <select name="country" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Countries</option>
                            @foreach ($countries as $c)
                                <option value="{{ $c }}" @selected(request('country') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="flex gap-2 border-t border-gray-100 px-5 py-4">
                    <a href="{{ route('admin.leads.index') }}" class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Reset</a>
                    <button type="submit" form="lead-filters" class="flex-1 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply Filters</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="mt-3 rounded-xl border border-gray-100 bg-white shadow-sm">
        <div>
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Lead ID</th>
                        <th class="px-4 py-3 font-semibold">Lead</th>
                        <th class="px-4 py-3 font-semibold">Phone</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Assigned To</th>
                        <th class="px-4 py-3 font-semibold">Created</th>
                        <th class="px-4 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($leads as $lead)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.leads.show', $lead) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $lead->lead_code }}</a>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.leads.show', $lead) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $lead->full_name }}</a>
                                <p class="text-xs text-[var(--color-muted)]">{{ $lead->company_name ?: ($lead->email ?: '—') }}</p>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $phoneDisplay = trim(($lead->dial_code ?? '').' '.$lead->phone);
                                    $waNumber = preg_replace('/\D/', '', ($lead->dial_code ?? '').$lead->phone);
                                @endphp
                                @if ($lead->is_whatsapp && $waNumber)
                                    <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-medium text-emerald-600 hover:underline" title="Open in WhatsApp">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1a10 10 0 0 1-3-1.8 11 11 0 0 1-2.3-2.9c-.5-.8-.6-1.5-.6-1.8 0-.5.5-1.2.8-1.5.2-.2.4-.2.6-.2h.5c.2 0 .4 0 .5.4l.7 1.7c.1.2 0 .4-.1.5l-.4.5c-.1.2-.3.3-.1.6.3.5.8 1.2 1.4 1.7.7.6 1.3.8 1.6 1 .2 0 .4 0 .5-.1l.6-.7c.2-.2.3-.2.5-.1l1.6.8c.2.1.4.2.4.3.1.2.1.6-.1 1.1Z"/></svg>
                                        {{ $phoneDisplay }}
                                    </a>
                                @else
                                    <span class="text-[var(--color-muted)]">{{ $phoneDisplay ?: '—' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.leads.status', $lead) }}">
                                    @csrf
                                    <select name="lead_status" onchange="this.form.submit()" title="Change status"
                                            class="cursor-pointer appearance-none rounded-full border-0 py-1 pl-2.5 pr-6 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] {{ $statusBadge[$lead->lead_status] ?? 'bg-gray-100 text-gray-600' }}"
                                            style="background-image:url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%223%22 viewBox=%220 0 24 24%22><path d=%22m6 9 6 6 6-6%22/></svg>');background-repeat:no-repeat;background-position:right 0.4rem center;background-size:0.7em;">
                                        @foreach (\App\Models\Lead::STATUSES as $sk => $sl)<option value="{{ $sk }}" @selected($lead->lead_status === $sk)>{{ $sl }}</option>@endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="px-4 py-3">
                                @if ($lead->assignee)
                                    <div class="flex items-center gap-2">
                                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[11px] font-bold text-[var(--color-primary)]">{{ strtoupper(substr($lead->assignee->name, 0, 1)) }}</span>
                                        <span class="text-[var(--color-heading)]">{{ $lead->assignee->name }}</span>
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[var(--color-muted)]">
                                <p>{{ $lead->created_at->format('d M Y') }}</p>
                                <p class="text-xs">{{ $lead->created_at->format('h:i A') }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @php $me = auth()->user(); @endphp
                                <div class="relative inline-block text-left" x-data="{ open: false }">
                                    <button type="button" @click="open = !open" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-gray-100" title="Actions">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>
                                    <div x-show="open" @click.outside="open = false" x-cloak
                                         class="absolute right-0 z-20 mt-1 w-48 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 text-left shadow-lg">
                                        <a href="{{ route('admin.leads.show', $lead) }}" class="flex items-center gap-2.5 px-3 py-2 text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="2.5"/></svg> View
                                        </a>
                                        @if ($me->allows('leads', 'edit'))
                                            <a href="{{ route('admin.leads.edit', $lead) }}" class="flex items-center gap-2.5 px-3 py-2 text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg> Edit
                                            </a>
                                        @endif
                                        @if ($me->allows('deals', 'create'))
                                            <form method="POST" action="{{ route('admin.leads.convert-deal', $lead) }}">
                                                @csrf
                                                <button type="submit" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l4-4 3 3 5-6"/></svg> Convert Deal
                                                </button>
                                            </form>
                                        @endif
                                        @if ($me->allows('leads', 'edit'))
                                            @if ($lead->isConverted())
                                                <span class="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg> Already a Client</span>
                                            @else
                                                <form method="POST" action="{{ route('admin.leads.convert', $lead) }}" onsubmit="return confirm('Convert this lead into a client?')">
                                                    @csrf
                                                    <button type="submit" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0"/></svg> Convert Client
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                        @if ($me->allows('leads', 'delete'))
                                            <div class="my-1 border-t border-gray-100"></div>
                                            <form method="POST" action="{{ route('admin.leads.destroy', $lead) }}" onsubmit="return confirm('Delete this lead?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg> Delete
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">No leads found — <a href="{{ route('admin.leads.create') }}" class="font-semibold text-[var(--color-primary)] hover:underline">add your first lead</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $leads->links() }}</div>
@endsection
