@extends('admin.layouts.app')
@section('title', 'Lead — '.$lead->full_name)

@php
    $statusBadge = [
        'new' => 'bg-emerald-50 text-emerald-700', 'contacted' => 'bg-blue-50 text-blue-700',
        'qualified' => 'bg-indigo-50 text-indigo-700', 'proposal' => 'bg-orange-50 text-orange-700',
        'negotiation' => 'bg-amber-50 text-amber-700', 'won' => 'bg-emerald-50 text-emerald-700', 'lost' => 'bg-red-50 text-red-600',
    ];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.leads.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to All Leads
            </a>
            <h1 class="mt-2 text-xl font-bold text-[var(--color-heading)]">{{ $lead->full_name }} <span class="text-sm font-normal text-[var(--color-muted)]">LEAD-{{ $lead->id }}</span></h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.leads.edit', $lead) }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Edit</a>
            @if ($lead->isConverted())
                <a href="{{ route('admin.clients.edit', $lead->converted_client_id) }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg> View Client
                </a>
            @else
                <form method="POST" action="{{ route('admin.leads.convert', $lead) }}" onsubmit="return confirm('Convert this lead into a client?')">
                    @csrf
                    <button class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M22 11h-6"/></svg>
                        Convert to Client
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($lead->isConverted())
        <div class="mb-5 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>
            Converted to client <strong>{{ $lead->convertedClient?->name }}</strong> ({{ $lead->convertedClient?->client_code }}) on {{ $lead->converted_at?->format('d M Y, h:i A') }}.
        </div>
    @endif

    @php
        $rows = [
            'Company' => $lead->company_name, 'Job Title' => $lead->job_title, 'Email' => $lead->email, 'Phone' => $lead->phone,
            'Website' => $lead->website, 'Industry' => $lead->industry, 'Lead Source' => $lead->lead_source,
            'Address' => collect([$lead->address, $lead->city, $lead->state, $lead->country, $lead->zip])->filter()->join(', '),
        ];
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-2">
            <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Lead Details</h2>
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach ($rows as $label => $value)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm text-[var(--color-heading)]">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($lead->notes)
                <div class="mt-5 border-t border-gray-100 pt-4">
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Notes</dt>
                    <dd class="mt-1 text-sm leading-relaxed text-[var(--color-muted)]">{{ $lead->notes }}</dd>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Status</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between"><span class="text-gray-400">Status</span>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge[$lead->lead_status] ?? 'bg-gray-100 text-gray-600' }}">{{ \App\Models\Lead::STATUSES[$lead->lead_status] ?? $lead->lead_status }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-400">Priority</span><span class="font-medium capitalize text-[var(--color-heading)]">{{ \App\Models\Lead::PRIORITIES[$lead->priority] ?? $lead->priority }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-400">Assigned To</span><span class="font-medium text-[var(--color-heading)]">{{ $lead->assignee?->name ?? '—' }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-400">Team</span><span class="font-medium text-[var(--color-heading)]">{{ $lead->team ?? '—' }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-400">Created</span><span class="font-medium text-[var(--color-heading)]">{{ $lead->created_at->format('d M Y') }}</span></div>
                </div>
            </div>
        </div>
    </div>
@endsection
