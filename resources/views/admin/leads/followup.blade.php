@extends('admin.layouts.app')
@section('title', 'Lead Follow-up')

@php
    $groups = [
        ['key' => 'overdue', 'label' => 'Overdue', 'rows' => $overdue, 'tone' => 'text-red-600', 'dot' => 'bg-red-500'],
        ['key' => 'today', 'label' => 'Today', 'rows' => $today, 'tone' => 'text-amber-600', 'dot' => 'bg-amber-500'],
        ['key' => 'upcoming', 'label' => 'Upcoming', 'rows' => $upcoming, 'tone' => 'text-emerald-600', 'dot' => 'bg-emerald-500'],
    ];
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Lead Follow-up</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; Leads &rsaquo; Follow-up</p>
    </div>

    <div class="space-y-6">
        @foreach ($groups as $g)
            <div>
                <div class="mb-3 flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full {{ $g['dot'] }}"></span>
                    <h2 class="text-sm font-bold {{ $g['tone'] }}">{{ $g['label'] }}</h2>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ $g['rows']->count() }}</span>
                </div>

                @if ($g['rows']->isEmpty())
                    <div class="rounded-xl border border-dashed border-gray-200 py-6 text-center text-sm text-gray-300">Nothing {{ strtolower($g['label']) }}.</div>
                @else
                    <div class="space-y-2">
                        @foreach ($g['rows'] as $lead)
                            <div class="flex flex-wrap items-center gap-4 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('admin.leads.show', $lead) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $lead->full_name }}</a>
                                    <p class="text-xs text-[var(--color-muted)]">{{ $lead->company_name ?: $lead->email ?: $lead->phone }} · {{ \App\Models\Lead::STATUSES[$lead->lead_status] ?? $lead->lead_status }}</p>
                                </div>
                                <div class="text-sm">
                                    <p class="text-xs text-gray-400">Follow up</p>
                                    <p class="font-medium {{ $g['tone'] }}">{{ $lead->next_follow_up_at->format('d M Y') }}</p>
                                </div>
                                <div class="text-sm">
                                    <p class="text-xs text-gray-400">Assigned</p>
                                    <p class="font-medium text-[var(--color-heading)]">{{ $lead->assignee?->name ?? '—' }}</p>
                                </div>
                                <form method="POST" action="{{ route('admin.leads.mark-contacted', $lead) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="date" name="next_follow_up_at" class="h-9 rounded-lg border border-gray-200 px-2 text-sm" title="Next follow-up (blank = done)">
                                    <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white hover:bg-[var(--color-primary-hover)]" title="Mark contacted now">Mark Contacted</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endsection
