@extends('admin.layouts.app')
@section('title', 'Deal — '.$deal->title)

@php
    $stageBadge = [
        'new' => 'bg-gray-100 text-gray-600', 'qualified' => 'bg-indigo-50 text-indigo-700',
        'proposal' => 'bg-orange-50 text-orange-700', 'negotiation' => 'bg-amber-50 text-amber-700',
        'won' => 'bg-emerald-50 text-emerald-700', 'lost' => 'bg-red-50 text-red-600',
    ];
    $sym = \App\Models\Currency::symbolMap();
    $cur = $sym[$deal->currency] ?? '';
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.deals.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Deals
            </a>
            <h1 class="mt-2 flex items-center gap-3 text-xl font-bold text-[var(--color-heading)]">
                {{ $deal->title }}
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $stageBadge[$deal->stage] ?? '' }}">{{ \App\Models\Deal::stages()[$deal->stage] ?? $deal->stage }}</span>
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="$dispatch('open-followup', { action: '{{ route('admin.deals.follow-up', $deal) }}', dealTitle: @js($deal->title) })" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg> Add Follow-up
            </button>
            <a href="{{ route('admin.deals.edit', $deal) }}" class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Edit</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-wide text-gray-400">Deal Value</p>
                        <p class="mt-1 text-3xl font-extrabold text-[var(--color-heading)]">{{ $cur }}{{ number_format($deal->value, 2) }} <span class="text-base font-medium text-gray-400">{{ $deal->currency }}</span></p>
                    </div>
                    {{-- Quick stage change --}}
                    <form method="POST" action="{{ route('admin.deals.stage', $deal) }}" data-turbo="false" class="shrink-0">
                        @csrf
                        <label class="mb-1 block text-[11px] uppercase tracking-wide text-gray-400">Move stage</label>
                        <select name="stage" onchange="this.form.submit()" class="h-10 rounded-lg border-gray-200 text-sm">
                            @foreach (\App\Models\Deal::stages() as $sk => $sl)<option value="{{ $sk }}" @selected($deal->stage === $sk)>{{ $sl }}</option>@endforeach
                        </select>
                    </form>
                </div>

                <dl class="mt-6 grid gap-x-6 gap-y-4 border-t border-gray-100 pt-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Expected Close</dt>
                        <dd class="mt-0.5 text-sm font-medium {{ $deal->isOverdue() ? 'text-red-600' : 'text-[var(--color-heading)]' }}">
                            {{ $deal->expected_close_date?->format('d M Y') ?? '—' }}
                            @if ($deal->isOverdue())<span class="ml-1 text-xs font-semibold">(overdue)</span>@endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Owner</dt>
                        <dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $deal->assignee?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Created</dt>
                        <dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $deal->created_at->format('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Last Updated</dt>
                        <dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $deal->updated_at->diffForHumans() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Project Type</dt>
                        <dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $deal->project_type ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Priority</dt>
                        <dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ \App\Models\Deal::PRIORITIES[$deal->priority] ?? ucfirst($deal->priority) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Win Probability</dt>
                        <dd class="mt-1 flex items-center gap-2">
                            <div class="h-2 w-40 overflow-hidden rounded-full bg-gray-100"><div class="h-full bg-[var(--color-primary)]" style="width: {{ $deal->effective_probability }}%"></div></div>
                            <span class="text-sm font-semibold text-[var(--color-heading)]">{{ $deal->effective_probability }}%</span>
                            <span class="text-xs text-gray-400">· forecast {{ $cur }}{{ number_format($deal->weighted_value, 0) }}</span>
                        </dd>
                    </div>
                    @if ($deal->stage === 'lost' && $deal->lost_reason)
                        <div class="sm:col-span-2">
                            <dt class="text-xs uppercase tracking-wide text-gray-400">Lost Reason</dt>
                            <dd class="mt-0.5 text-sm font-medium text-red-600">{{ $deal->lost_reason }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Description + Attachments --}}
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm" x-data="{ editing: false }">
                {{-- Description --}}
                <div class="border-b border-gray-100 px-6 py-5">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="flex items-center gap-2 text-sm font-bold text-[var(--color-heading)]">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h10"/></svg></span>
                            Description
                        </h2>
                        <button type="button" x-show="!editing" @click="editing = true" class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-[var(--color-muted)] transition hover:bg-gray-50">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                            {{ $deal->notes ? 'Edit' : 'Add' }}
                        </button>
                    </div>

                    <div x-show="!editing">
                        @if ($deal->notes)
                            <p class="whitespace-pre-wrap text-sm leading-relaxed text-[var(--color-muted)]">{{ $deal->notes }}</p>
                        @else
                            <div class="rounded-lg border border-dashed border-gray-200 px-4 py-6 text-center">
                                <p class="text-sm text-gray-400">No description yet.</p>
                                <button type="button" @click="editing = true" class="mt-1 text-xs font-semibold text-[var(--color-primary)] hover:underline">Add a description</button>
                            </div>
                        @endif
                    </div>

                    <form x-show="editing" x-cloak method="POST" action="{{ route('admin.deals.description', $deal) }}" data-turbo="false">
                        @csrf @method('PUT')
                        <textarea name="notes" rows="4" maxlength="5000" placeholder="Describe the deal, requirements, scope…"
                                  class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm transition focus:border-[var(--color-primary)] focus:bg-white focus:ring-2 focus:ring-[var(--color-primary)]/20">{{ $deal->notes }}</textarea>
                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" @click="editing = false" class="rounded-lg border border-gray-200 px-3.5 py-2 text-xs font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                        </div>
                    </form>
                </div>

                {{-- Attachments --}}
                <div class="px-6 py-5">
                    <div class="mb-3 flex items-center gap-2">
                        <h3 class="flex items-center gap-2 text-sm font-bold text-[var(--color-heading)]">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="m21.4 11.05-8.5 8.49a5 5 0 0 1-7.07-7.07l8.49-8.49a3.33 3.33 0 0 1 4.71 4.71l-8.5 8.49a1.67 1.67 0 0 1-2.36-2.36l7.79-7.78"/></svg></span>
                            Attachments
                        </h3>
                        @if ($deal->attachments->isNotEmpty())<span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ $deal->attachments->count() }}</span>@endif
                    </div>

                    {{-- Dropzone --}}
                    <form method="POST" action="{{ route('admin.deals.attachments.store', $deal) }}" enctype="multipart/form-data" data-turbo="false"
                          x-data="{ name: '' }" class="mb-3">
                        @csrf
                        <label class="group flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-lg border-2 border-dashed border-gray-200 px-4 py-6 text-center transition hover:border-[var(--color-primary)] hover:bg-[var(--color-primary-soft)]/40">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-gray-100 text-gray-400 transition group-hover:bg-white group-hover:text-[var(--color-primary)]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                            </span>
                            <span class="text-sm font-semibold text-[var(--color-heading)]" x-text="name || 'Click to upload a file'"></span>
                            <span class="text-xs text-gray-400" x-show="!name">Image, PDF or document · up to 10MB</span>
                            <input type="file" name="file" required class="hidden" @change="name = $event.target.files[0]?.name || ''; $el.closest('form').requestSubmit()">
                        </label>
                    </form>

                    @if ($deal->attachments->isEmpty())
                        <p class="text-center text-xs text-gray-400">No files attached yet.</p>
                    @else
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($deal->attachments as $file)
                                <div class="group flex items-center gap-3 rounded-lg border border-gray-100 p-2.5 transition hover:border-gray-200 hover:bg-gray-50">
                                    @if ($file->isImage())
                                        <a href="{{ $file->url }}" target="_blank" class="shrink-0"><img src="{{ $file->url }}" alt="" class="h-10 w-10 rounded-lg object-cover"></a>
                                    @else
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-red-50 text-red-500"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" d="M7 3h7l5 5v13H7zM14 3v5h5"/></svg></span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ $file->url }}" target="_blank" class="block truncate text-sm font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]" title="{{ $file->name }}">{{ $file->name }}</a>
                                        <p class="text-xs text-gray-400">{{ $file->readable_size }} · {{ $file->created_at->format('d M Y') }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-0.5">
                                        <a href="{{ $file->url }}" target="_blank" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Open"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg></a>
                                        <form method="POST" action="{{ route('admin.deals.attachments.destroy', [$deal, $file]) }}" onsubmit="return confirm('Remove this attachment?')" data-turbo="false">
                                            @csrf @method('DELETE')
                                            <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-600" title="Remove"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg></button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Follow-up history --}}
            <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Follow-up History</h2>
                    <button type="button" @click="$dispatch('open-followup', { action: '{{ route('admin.deals.follow-up', $deal) }}', dealTitle: @js($deal->title) })" class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-primary-soft)] px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:opacity-80">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add
                    </button>
                </div>
                <div class="p-6">
                    @forelse ($deal->followUps as $fu)
                        <div class="flex gap-3 {{ ! $loop->last ? 'mb-4 border-b border-gray-50 pb-4' : '' }}">
                            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full {{ $fu->isDone() ? 'bg-emerald-50 text-emerald-600' : ($fu->isDue() ? 'bg-amber-100 text-amber-700' : 'bg-[var(--color-primary-soft)] text-[var(--color-primary)]') }}">
                                @if ($fu->isDone())
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>
                                @else
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-[var(--color-heading)] {{ $fu->isDone() ? 'line-through opacity-60' : '' }}">{{ $fu->title ?: 'Follow-up' }}</p>
                                    @if ($fu->isDone())
                                        <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">DONE</span>
                                    @elseif ($fu->isDue())
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">DUE</span>
                                    @else
                                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-500">PENDING</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    <svg class="mr-0.5 inline h-3 w-3 align-[-1px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    {{ $fu->due_at->format('d M Y, g:i A') }}
                                    @if ($fu->isDone())· done {{ $fu->completed_at->diffForHumans() }}@endif
                                </p>
                                @if ($fu->note)
                                    <p class="mt-1 whitespace-pre-wrap text-sm text-[var(--color-muted)]">{{ $fu->note }}</p>
                                @endif
                                <p class="mt-1 text-[11px] text-gray-400">Set by {{ $fu->user?->name ?? 'System' }} · {{ $fu->created_at->diffForHumans() }}</p>
                            </div>
                            <div class="flex shrink-0 items-start gap-1">
                                <form method="POST" action="{{ route('admin.deals.follow-up.complete', [$deal, $fu]) }}" data-turbo="false">
                                    @csrf
                                    <button class="grid h-8 w-8 place-items-center rounded-lg {{ $fu->isDone() ? 'text-gray-400 hover:bg-gray-100' : 'text-emerald-600 hover:bg-emerald-50' }}" title="{{ $fu->isDone() ? 'Reopen' : 'Mark done' }}">
                                        @if ($fu->isDone())
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 2v6h6M21 12a9 9 0 1 1-3-6.7L21 8"/></svg>
                                        @else
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>
                                        @endif
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.deals.follow-up.destroy', [$deal, $fu]) }}" onsubmit="return confirm('Remove this follow-up?')" data-turbo="false">
                                    @csrf @method('DELETE')
                                    <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg></button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-[var(--color-muted)]">No follow-ups yet — use “Add” to schedule the first one.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Related records --}}
        <div class="space-y-4">
            {{-- Next follow-up --}}
            <div class="rounded-xl border {{ $deal->isFollowUpDue() ? 'border-amber-300 ring-1 ring-amber-200' : 'border-gray-100' }} bg-white p-6 shadow-sm">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-[var(--color-heading)]">
                    <svg class="h-4 w-4 text-[var(--color-primary)]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
                    Next Follow-up
                </h2>
                @if ($deal->next_follow_up_at)
                    @if ($deal->follow_up_title)
                        <p class="mb-0.5 text-sm font-bold text-[var(--color-heading)]">{{ $deal->follow_up_title }}</p>
                    @endif
                    <p class="text-sm font-semibold {{ $deal->isFollowUpDue() ? 'text-amber-700' : 'text-[var(--color-heading)]' }}">
                        {{ $deal->next_follow_up_at->format('d M Y, g:i A') }}
                        <span class="text-xs font-normal text-gray-400">· {{ $deal->next_follow_up_at->diffForHumans() }}</span>
                        @if ($deal->isFollowUpDue())<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">DUE</span>@endif
                    </p>
                    @if ($deal->follow_up_note)
                        <p class="mt-1.5 whitespace-pre-wrap text-sm text-[var(--color-muted)]">{{ $deal->follow_up_note }}</p>
                    @endif
                @else
                    <p class="mb-3 text-sm text-[var(--color-muted)]">No pending follow-up.</p>
                @endif
                <button type="button" @click="$dispatch('open-followup', { action: '{{ route('admin.deals.follow-up', $deal) }}', dealTitle: @js($deal->title) })" class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Follow-up
                </button>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Client</h2>
                @if ($deal->client)
                    <a href="{{ route('admin.clients.show', $deal->client_id) }}" class="flex items-center gap-3 hover:opacity-80">
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]">{{ strtoupper(substr($deal->client->name, 0, 1)) }}</span>
                        <div>
                            <p class="font-semibold text-[var(--color-primary)] hover:underline">{{ $deal->client->name }}</p>
                            <p class="text-xs text-[var(--color-muted)]">{{ $deal->client->email }}</p>
                        </div>
                    </a>
                @else
                    <p class="text-sm text-[var(--color-muted)]">No client linked.</p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Source Lead</h2>
                @if ($deal->lead)
                    <a href="{{ route('admin.leads.show', $deal->lead_id) }}" class="block hover:opacity-80">
                        <p class="font-semibold text-[var(--color-primary)] hover:underline">{{ $deal->lead->full_name }}</p>
                        <p class="text-xs text-[var(--color-muted)]">{{ $deal->lead->lead_code }} · {{ \App\Models\Lead::STATUSES[$deal->lead->lead_status] ?? $deal->lead->lead_status }}</p>
                    </a>
                @else
                    <p class="text-sm text-[var(--color-muted)]">Not created from a lead.</p>
                @endif
            </div>
        </div>
    </div>

    @include('admin.deals._followup-modal')
@endsection
