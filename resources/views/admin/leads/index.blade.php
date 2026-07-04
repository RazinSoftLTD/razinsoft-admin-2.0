@extends('admin.layouts.app')
@section('title', 'All Leads')

@php
    $statusBadge = [
        'new' => 'bg-emerald-50 text-emerald-700',
        'contacted' => 'bg-blue-50 text-blue-700',
        'qualified' => 'bg-indigo-50 text-indigo-700',
        'proposal' => 'bg-orange-50 text-orange-700',
        'negotiation' => 'bg-amber-50 text-amber-700',
        'won' => 'bg-emerald-50 text-emerald-700',
        'lost' => 'bg-red-50 text-red-600',
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
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.leads.import.form') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 15V3m0 0 4 4m-4-4-4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg> Import Leads
            </a>
            <a href="{{ route('admin.leads.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add New Lead
            </a>
        </div>
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

    {{-- Filter bar --}}
    <form method="GET" class="mt-5 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <input name="search" value="{{ request('search') }}" placeholder="Search name, email, phone…" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none lg:col-span-2">
            <select name="status" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm">
                <option value="">All Status</option>
                @foreach (\App\Models\Lead::STATUSES as $k => $label)
                    <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="source" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm">
                <option value="">All Sources</option>
                @foreach (\App\Models\Lead::SOURCES as $s)
                    <option value="{{ $s }}" @selected(request('source') === $s)>{{ $s }}</option>
                @endforeach
            </select>
            <select name="assigned" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm">
                <option value="">All Users</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected(request('assigned') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
            <select name="priority" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm">
                <option value="">All Priority</option>
                @foreach (\App\Models\Lead::PRIORITIES as $k => $label)
                    <option value="{{ $k }}" @selected(request('priority') === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="mt-3 flex items-center justify-end gap-2">
            <a href="{{ route('admin.leads.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Reset</a>
            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Filter</button>
        </div>
    </form>

    {{-- Results meta --}}
    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-[var(--color-muted)]">
            Showing {{ $leads->count() ? $leads->firstItem() : 0 }} to {{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }} results
        </p>
        <div class="flex items-center gap-2">
            <form method="GET" class="flex items-center gap-2 text-sm text-[var(--color-muted)]">
                @foreach (request()->except('per_page', 'page') as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                Show
                <select name="per_page" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                    @foreach ([10, 25, 50, 100] as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
                entries
            </form>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                Export
            </a>
        </div>
    </div>

    {{-- Table --}}
    <div class="mt-3 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Lead ID</th>
                        <th class="px-4 py-3 font-semibold">Lead / Company</th>
                        <th class="px-4 py-3 font-semibold">Contact</th>
                        <th class="px-4 py-3 font-semibold">Phone</th>
                        <th class="px-4 py-3 font-semibold">Source</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Priority</th>
                        <th class="px-4 py-3 font-semibold">Assigned To</th>
                        <th class="px-4 py-3 font-semibold">Created At</th>
                        <th class="px-4 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($leads as $lead)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-[var(--color-heading)]">{{ $lead->lead_code }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[var(--color-heading)]">{{ $lead->company_name ?: $lead->full_name }}</p>
                                @if ($lead->industry)<p class="text-xs text-[var(--color-muted)]">{{ $lead->industry }}</p>@endif
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-[var(--color-heading)]">{{ $lead->full_name }}</p>
                                <p class="text-xs text-[var(--color-muted)]">{{ $lead->job_title ?: ($lead->email ?: '—') }}</p>
                            </td>
                            <td class="px-4 py-3 text-[var(--color-muted)]">{{ $lead->phone }}</td>
                            <td class="px-4 py-3 text-[var(--color-muted)]">{{ $lead->lead_source }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge[$lead->lead_status] ?? 'bg-gray-100 text-gray-600' }}">{{ \App\Models\Lead::STATUSES[$lead->lead_status] ?? $lead->lead_status }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityBadge[$lead->priority] ?? 'bg-gray-100 text-gray-600' }}">{{ \App\Models\Lead::PRIORITIES[$lead->priority] ?? $lead->priority }}</span>
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
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.leads.show', $lead) }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="View">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                    </a>
                                    <a href="{{ route('admin.leads.edit', $lead) }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('admin.leads.destroy', $lead) }}" onsubmit="return confirm('Delete this lead?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-12 text-center text-gray-400">No leads yet — <a href="{{ route('admin.leads.create') }}" class="font-semibold text-[var(--color-primary)] hover:underline">add your first lead</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $leads->links() }}</div>
@endsection
