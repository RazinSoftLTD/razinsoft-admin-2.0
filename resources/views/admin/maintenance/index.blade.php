@extends('admin.layouts.app')
@section('title', 'Maintenance')

@section('content')
@php
    $can = fn ($a) => auth()->user()->allows('maintenance', $a);
    $tabs = [
        'attention' => 'Needs attention',
        'all' => 'All',
        'active' => 'Active',
        'expiring' => 'Expiring soon',
        'expired' => 'Expired',
        'ended' => 'Ended',
    ];
    $tone = [
        'Active' => 'bg-emerald-50 text-emerald-700',
        'Expiring soon' => 'bg-amber-50 text-amber-700',
        'Expired' => 'bg-red-50 text-red-600',
        'Paused' => 'bg-gray-100 text-gray-500',
        'Ended' => 'bg-gray-100 text-gray-500',
    ];
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Maintenance</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Workspace &rsaquo; Maintenance &rsaquo; contracts, plans and renewals</p>
    </div>

    <div class="flex items-center gap-2">
        <form method="GET" class="relative" style="min-width: 15rem">
            <input type="hidden" name="view" value="{{ $view }}">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
            <input name="search" type="text" value="{{ request('search') }}" placeholder="Search contract or client…"
                   class="h-11 w-full rounded-lg border border-gray-200 pl-11 pr-4 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
        </form>

        @if ($can('create'))
            <a href="{{ route('admin.maintenance.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                New Maintenance
            </a>
        @endif
    </div>
</div>

{{-- "Needs attention" first and by default: the reason to open this page is that something is
     overdue or a contract is running out, not to browse the list. --}}
<div class="mb-4 flex flex-wrap gap-2">
    @foreach ($tabs as $key => $label)
        <a href="{{ route('admin.maintenance.index', array_filter(['view' => $key, 'search' => request('search')])) }}"
           class="rounded-lg px-3.5 py-2 text-xs font-semibold {{ $view === $key ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-[var(--color-muted)] hover:bg-gray-200' }}">
            {{ $label }} ({{ $counts[$key] }})
        </a>
    @endforeach
</div>

<div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-4 py-3 text-left">Contract</th>
                    <th class="px-4 py-3 text-left">Client</th>
                    <th class="px-4 py-3 text-left">Plan</th>
                    <th class="px-4 py-3 text-center">Due now</th>
                    <th class="px-4 py-3 text-left">Runs out</th>
                    <th class="px-4 py-3 text-left">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $m)
                    @php
                        $dueList = $m->dueTasks();
                        $late = $dueList->max('days_late') ?? 0;
                        $health = $m->healthLabel();
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.maintenance.show', $m) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $m->title }}</a>
                            <p class="text-xs text-gray-400">{{ $m->code }}@if ($m->project) &middot; {{ $m->project->name }}@endif</p>
                        </td>
                        <td class="px-4 py-3 text-[var(--color-muted)]">{{ $m->client?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-[var(--color-muted)]">
                            {{ $m->tasks->where('is_active', true)->count() }} {{ Str::plural('task', $m->tasks->where('is_active', true)->count()) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($dueList->isEmpty())
                                <span class="text-xs text-gray-300">—</span>
                            @else
                                <span class="rounded-lg px-2 py-1 text-xs font-bold {{ $late > 0 ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600' }}">
                                    {{ $dueList->count() }}{{ $late > 0 ? ' · '.$late.'d late' : '' }}
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-[var(--color-muted)]">
                            {{ $m->ends_on->format('d M Y') }}
                            <span class="block text-xs {{ $m->daysLeft() < 0 ? 'text-red-500' : ($m->needsRenewal() ? 'text-amber-600' : 'text-gray-400') }}">
                                {{ $m->daysLeft() < 0 ? abs($m->daysLeft()).' days ago' : 'in '.$m->daysLeft().' days' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-lg px-2 py-1 text-xs font-semibold {{ $tone[$health] ?? 'bg-gray-100 text-gray-500' }}">{{ $health }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">
                        @if ($view === 'attention')
                            Nothing needs attention — every plan is up to date and no contract is running out.
                        @else
                            No maintenance contracts here.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
