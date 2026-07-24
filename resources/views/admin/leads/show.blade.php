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
            <h1 class="mt-2 text-xl font-bold text-[var(--color-heading)]">{{ $lead->full_name }} <span class="text-sm font-normal text-[var(--color-muted)]">{{ $lead->lead_code }}</span></h1>
        </div>
        <div class="flex items-center gap-2">
            @if (! empty($whatsappChat) && auth()->user()->hasPermission('whatsapp.view'))
                <a href="{{ route('admin.whatsapp.index', ['chat' => $whatsappChat->id]) }}" class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2Zm5.5 14.2c-.2.6-1.2 1.2-1.7 1.2-.4 0-1 .1-3.4-.9-2.9-1.2-4.7-4.1-4.9-4.3-.1-.2-1.1-1.5-1.1-2.8 0-1.3.7-2 .9-2.2.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6c-.2.2-.3.4-.1.7.2.3.9 1.4 1.9 2.3 1.3 1.1 2.3 1.5 2.6 1.6.2.1.4.1.6-.1l.7-.9c.2-.2.4-.2.6-.1l1.9.9c.3.1.5.2.5.4.1.2.1.9-.1 1.5Z"/></svg>
                    WhatsApp Chat
                </a>
            @endif
            <a href="{{ route('admin.leads.edit', $lead) }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Edit</a>
            <a href="{{ route('admin.deals.create', ['lead' => $lead->id]) }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Create Deal</a>
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
                        <form method="POST" action="{{ route('admin.leads.status', $lead) }}">
                            @csrf
                            <select name="lead_status" onchange="this.form.submit()" title="Change status"
                                    class="cursor-pointer appearance-none rounded-full border-0 py-1 pl-2.5 pr-6 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] {{ $statusBadge[$lead->lead_status] ?? 'bg-gray-100 text-gray-600' }}"
                                    style="background-image:url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%223%22 viewBox=%220 0 24 24%22><path d=%22m6 9 6 6 6-6%22/></svg>');background-repeat:no-repeat;background-position:right 0.4rem center;background-size:0.7em;">
                                @foreach (\App\Models\Lead::STATUSES as $sk => $sl)<option value="{{ $sk }}" @selected($lead->lead_status === $sk)>{{ $sl }}</option>@endforeach
                            </select>
                        </form></div>
                    <div class="flex items-center justify-between"><span class="text-gray-400">Priority</span><span class="font-medium capitalize text-[var(--color-heading)]">{{ \App\Models\Lead::PRIORITIES[$lead->priority] ?? $lead->priority }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-400">Assigned To</span><span class="font-medium text-[var(--color-heading)]">{{ $lead->assignee?->name ?? '—' }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-400">Team</span><span class="font-medium text-[var(--color-heading)]">{{ $lead->team ?? '—' }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-400">Last Contacted</span><span class="font-medium text-[var(--color-heading)]">{{ $lead->last_contacted_at?->format('d M Y') ?? '—' }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-400">Created</span><span class="font-medium text-[var(--color-heading)]">{{ $lead->created_at->format('d M Y') }}</span></div>
                </div>

                {{-- Next follow-up summary + quick add --}}
                @php $nextFu = $lead->followUps->where('status', 'pending')->sortBy('scheduled_at')->first(); @endphp
                <div class="mt-4 border-t border-gray-100 pt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Next Follow-up</p>
                        @if (auth()->user()->hasPermission('follow_ups.create'))
                            <button type="button" @click="$dispatch('open-schedule', { action: '{{ route('admin.leads.follow-ups.store', $lead) }}', leadName: @js($lead->full_name) })" class="inline-flex items-center gap-1 text-xs font-semibold text-[var(--color-primary)] hover:underline">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add
                            </button>
                        @endif
                    </div>
                    @if ($nextFu)
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                            <p class="text-sm font-semibold {{ $nextFu->isOverdue() ? 'text-red-600' : 'text-[var(--color-heading)]' }}">{{ $nextFu->scheduled_at->format('d M Y, h:i A') }}</p>
                            <p class="mt-1 flex items-center gap-1.5 text-xs text-[var(--color-muted)]">{{ $nextFu->typeLabel() }}<span class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 {{ $nextFu->statusBadge() }}">{{ $nextFu->statusLabel() }}</span></p>
                        </div>
                    @else
                        <p class="text-sm text-[var(--color-muted)]">No pending follow-up scheduled.</p>
                    @endif
                </div>
            </div>

            {{-- Deals from this lead --}}
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Deals</h2>
                    <a href="{{ route('admin.deals.create', ['lead' => $lead->id]) }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">+ New</a>
                </div>
                @php
                    $dealSym = \App\Models\Currency::symbolMap();
                    $dealStageBadge = ['new'=>'bg-gray-100 text-gray-600','qualified'=>'bg-indigo-50 text-indigo-700','proposal'=>'bg-orange-50 text-orange-700','negotiation'=>'bg-amber-50 text-amber-700','won'=>'bg-emerald-50 text-emerald-700','lost'=>'bg-red-50 text-red-600'];
                @endphp
                @forelse ($lead->deals->sortByDesc('id') as $deal)
                    <a href="{{ route('admin.deals.show', $deal) }}" class="flex items-center justify-between gap-2 border-t border-gray-50 py-2.5 first:border-t-0 hover:opacity-80">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-[var(--color-heading)]">{{ $deal->title }}</p>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $dealStageBadge[$deal->stage] ?? '' }}">{{ \App\Models\Deal::stages()[$deal->stage] ?? $deal->stage }}</span>
                        </div>
                        <span class="shrink-0 text-sm font-semibold text-[var(--color-heading)]">{{ $dealSym[$deal->currency] ?? '' }}{{ number_format($deal->value, 0) }}</span>
                    </a>
                @empty
                    <p class="text-sm text-[var(--color-muted)]">No deals yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ===== Follow-up Timeline ===== --}}
    @php
        $me = auth()->user();
        $canComplete = $me->hasPermission('follow_ups.complete');
        $canEdit = $me->hasPermission('follow_ups.edit');
        $canDelete = $me->hasPermission('follow_ups.delete');
        $timeline = $lead->followUps->sortBy('scheduled_at')->values();
        $typeDot = ['call' => 'bg-blue-500', 'whatsapp' => 'bg-emerald-500', 'meeting' => 'bg-indigo-500', 'email' => 'bg-amber-500', 'sms' => 'bg-purple-500', 'other' => 'bg-gray-400'];
    @endphp
    <div class="mt-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Follow-up Timeline</h2>
                <p class="mt-0.5 text-xs text-[var(--color-muted)]">Complete history — nothing is ever deleted.</p>
            </div>
            @if ($me->hasPermission('follow_ups.create'))
                <button type="button" @click="$dispatch('open-schedule', { action: '{{ route('admin.leads.follow-ups.store', $lead) }}', leadName: @js($lead->full_name) })" class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-primary-soft)] px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:opacity-80">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Follow-up
                </button>
            @endif
        </div>

        <ol class="relative space-y-5 border-l-2 border-gray-100 pl-6">
            {{-- Lead created anchor --}}
            <li class="relative">
                <span class="absolute -left-[31px] grid h-5 w-5 place-items-center rounded-full bg-[var(--color-primary)] ring-4 ring-white">
                    <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                </span>
                <p class="text-sm font-semibold text-[var(--color-heading)]">Lead Created</p>
                <p class="text-xs text-[var(--color-muted)]">{{ $lead->created_at->format('d M Y, h:i A') }}</p>
            </li>

            @foreach ($timeline as $fu)
                <li class="relative">
                    <span class="absolute -left-[31px] h-5 w-5 rounded-full ring-4 ring-white {{ $typeDot[$fu->type] ?? 'bg-gray-400' }}"></span>
                    <div class="rounded-xl border border-gray-100 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="flex items-center gap-2 text-sm font-semibold text-[var(--color-heading)] {{ $fu->isCancelled() ? 'line-through opacity-60' : '' }}">
                                    {{ $fu->typeLabel() }}
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 {{ $fu->statusBadge() }}">{{ $fu->statusLabel() }}</span>
                                </p>
                                <p class="mt-0.5 text-xs text-[var(--color-muted)]">{{ $fu->scheduled_at->format('d M Y, h:i A') }} · {{ $fu->priorityLabel() }} priority @if ($fu->assignee) · {{ $fu->assignee->name }} @endif</p>
                            </div>
                            @if ($canComplete && $fu->isPending())
                                <button type="button"
                                        @click="$dispatch('open-done', { action: '{{ route('admin.leads.follow-ups.complete', [$lead, $fu]) }}', leadName: @js($lead->full_name), followUpTitle: @js($fu->typeLabel().' · '.$fu->scheduled_at->format('d M Y')) })"
                                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg> Mark Done
                                </button>
                            @endif
                        </div>
                        @if ($fu->note)
                            <p class="mt-2 text-sm text-[var(--color-muted)]">{{ $fu->note }}</p>
                        @endif
                        @if ($fu->isDone() && $fu->completion_note)
                            <div class="mt-2 rounded-lg bg-emerald-50/70 px-3 py-2 text-xs text-emerald-800">
                                <span class="font-semibold">Outcome:</span> {{ $fu->completion_note }}
                                <span class="mt-0.5 block text-emerald-600/80">Completed {{ $fu->completed_at?->format('d M Y, h:i A') }}@if ($fu->completedBy) by {{ $fu->completedBy->name }}@endif</span>
                            </div>
                        @endif
                        @if (($canEdit || $canDelete) && $fu->isPending())
                            <div class="mt-2 flex items-center gap-3 border-t border-gray-50 pt-2">
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('admin.leads.follow-ups.cancel', [$lead, $fu]) }}" onsubmit="return confirm('Cancel this follow-up?')" data-turbo="false">
                                        @csrf
                                        <button class="text-xs font-semibold text-gray-500 hover:text-[var(--color-heading)]">Cancel</button>
                                    </form>
                                @endif
                                @if ($canDelete)
                                    <form method="POST" action="{{ route('admin.leads.follow-ups.destroy', [$lead, $fu]) }}" onsubmit="return confirm('Delete this pending follow-up?')" data-turbo="false">
                                        @csrf @method('DELETE')
                                        <button class="text-xs font-semibold text-red-600 hover:underline">Delete</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach

            @if ($timeline->isEmpty())
                <li class="relative">
                    <span class="absolute -left-[31px] h-5 w-5 rounded-full bg-gray-200 ring-4 ring-white"></span>
                    <p class="text-sm text-[var(--color-muted)]">No follow-ups yet — use “Add Follow-up” to schedule the first one.</p>
                </li>
            @endif
        </ol>
    </div>

    @include('admin.follow-ups._schedule-modal')
    @include('admin.follow-ups._done-modal')
@endsection
