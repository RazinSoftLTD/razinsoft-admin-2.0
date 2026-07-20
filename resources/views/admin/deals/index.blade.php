@extends('admin.layouts.app')
@section('title', 'Deals')

@php
    $cur = \App\Models\Currency::symbolMap();
    // Stage colours are configurable-stage-aware (open stages cycle a palette; Won/Lost fixed).
    $stageColors = \App\Models\Deal::stageColors();
    $stageBar = array_map(fn ($c) => $c[0], $stageColors);
    $stageBadge = array_map(fn ($c) => $c[1], $stageColors);
    $priorityDot = ['high' => 'bg-red-500', 'medium' => 'bg-amber-400', 'low' => 'bg-gray-300'];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Deals Pipeline</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; Deals — drag a card between columns to move it.</p>
        </div>
        <div class="flex items-center gap-2">
            <div class="flex rounded-lg border border-gray-200 p-0.5 text-sm">
                <a href="{{ route('admin.deals.index', ['view' => 'board']) }}" class="rounded-md px-3 py-1.5 font-semibold {{ $view === 'board' ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)]' }}">Board</a>
                <a href="{{ route('admin.deals.index', ['view' => 'list']) }}" class="rounded-md px-3 py-1.5 font-semibold {{ $view === 'list' ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)]' }}">List</a>
            </div>
            <a href="{{ route('admin.deals.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> New Deal
            </a>
        </div>
    </div>

    @if ($view === 'board')
        {{-- Drag-and-drop Kanban --}}
        <div x-data="dealBoard()" class="flex gap-4 overflow-x-auto pb-4">
            @foreach (\App\Models\Deal::stages() as $key => $label)
                @php $col = $byStage->get($key, collect()); @endphp
                <div class="w-72 shrink-0" data-stage="{{ $key }}">
                    <div class="mb-2 flex items-center justify-between rounded-lg border border-gray-100 bg-white px-3 py-2">
                        <span class="flex items-center gap-2 text-sm font-bold text-[var(--color-heading)]">
                            <span class="h-2.5 w-2.5 rounded-full {{ $stageBar[$key] }}"></span>{{ $label }}
                            <span data-count class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ $col->count() }}</span>
                        </span>
                        <span class="text-xs font-semibold text-[var(--color-muted)]"><span data-total>{{ number_format($col->sum('value'), 0) }}</span></span>
                    </div>
                    <div data-dropzone="{{ $key }}" @dragover.prevent @dragenter.prevent="$el.classList.add('ring-2','ring-[var(--color-primary)]')" @dragleave="$el.classList.remove('ring-2','ring-[var(--color-primary)]')" @drop="drop($event, '{{ $key }}')"
                         class="min-h-[8rem] space-y-2 rounded-xl bg-gray-50/60 p-2 transition">
                        @foreach ($col as $deal)
                            <div draggable="true" @dragstart="dragStart($event, {{ $deal->id }})" @dragend="dragEnd($event)"
                                 data-deal="{{ $deal->id }}" data-value="{{ (float) $deal->value }}"
                                 class="group cursor-grab rounded-xl border bg-white p-3 shadow-sm hover:shadow active:cursor-grabbing {{ $deal->isFollowUpDue() ? 'border-amber-300 ring-1 ring-amber-200' : 'border-gray-100' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('admin.deals.show', $deal) }}" class="text-sm font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)] hover:underline">{{ $deal->title }}</a>
                                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $priorityDot[$deal->priority] ?? 'bg-gray-300' }}" title="{{ ucfirst($deal->priority) }} priority"></span>
                                </div>
                                @if ($deal->project_type)<span class="mt-1.5 inline-block rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500">{{ $deal->project_type }}</span>@endif
                                @if ($deal->isOverdue())<span class="ml-1 inline-flex items-center gap-1 align-middle text-[10px] font-semibold text-red-600"><span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>Overdue</span>@endif
                                <p class="mt-2 text-lg font-bold text-[var(--color-heading)]">{{ $cur[$deal->currency] ?? '' }}{{ number_format($deal->value, 0) }}</p>
                                @if ($deal->next_follow_up_at)
                                    <div class="mt-2 inline-flex max-w-full items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-semibold {{ $deal->isFollowUpDue() ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500' }}" @if ($deal->follow_up_note) title="{{ $deal->follow_up_note }}" @endif>
                                        <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
                                        <span class="truncate">{{ $deal->follow_up_title ?: 'Follow-up' }} · {{ $deal->next_follow_up_at->format('d M, g:i A') }}{{ $deal->isFollowUpDue() ? ' · due' : '' }}</span>
                                    </div>
                                @endif
                                <div class="mt-2 flex items-center justify-between border-t border-gray-50 pt-2 text-xs text-[var(--color-muted)]">
                                    <span class="truncate">{{ $deal->client?->name ?? ($deal->lead?->full_name ?? '—') }}</span>
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('admin.deals.show', $deal) }}" class="rounded p-1 text-gray-400 opacity-0 transition group-hover:opacity-80 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Open details"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="2.5"/></svg></a>
                                        @if ($deal->canInvoice())
                                            <form method="POST" action="{{ route('admin.deals.invoice', $deal) }}">@csrf
                                                <button class="rounded p-1 text-emerald-600 hover:bg-emerald-50" title="Create invoice"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M7 3h7l5 5v13H7zM14 3v5h5"/></svg></button>
                                            </form>
                                        @endif
                                        @if ($deal->assignee)<span class="grid h-6 w-6 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[10px] font-bold text-[var(--color-primary)]" title="{{ $deal->assignee->name }}">{{ strtoupper(substr($deal->assignee->name, 0, 1)) }}</span>@endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <div data-empty class="{{ $col->count() ? 'hidden' : '' }} rounded-lg border border-dashed border-gray-200 py-6 text-center text-xs text-gray-300">Drop deals here</div>
                    </div>
                </div>
            @endforeach
        </div>

        <script>
            function dealBoard() {
                return {
                    dragId: null,
                    csrf: document.querySelector('meta[name="csrf-token"]').content,
                    base: @js(url('admin/deals')),
                    dragStart(e, id) { this.dragId = id; e.dataTransfer.effectAllowed = 'move'; e.target.classList.add('opacity-40'); },
                    dragEnd(e) { e.target.classList.remove('opacity-40'); },
                    drop(e, stage) {
                        e.currentTarget.classList.remove('ring-2', 'ring-[var(--color-primary)]');
                        const card = document.querySelector('[data-deal="' + this.dragId + '"]');
                        const zone = e.currentTarget;
                        if (!card || zone.contains(card)) return;
                        zone.insertBefore(card, zone.querySelector('[data-empty]'));
                        this.recount();
                        fetch(this.base + '/' + this.dragId + '/stage', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ stage }),
                        }).catch(() => location.reload());
                    },
                    recount() {
                        document.querySelectorAll('[data-stage]').forEach(col => {
                            const zone = col.querySelector('[data-dropzone]');
                            const cards = zone.querySelectorAll('[data-deal]');
                            col.querySelector('[data-count]').textContent = cards.length;
                            let total = 0; cards.forEach(c => total += parseFloat(c.dataset.value || 0));
                            col.querySelector('[data-total]').textContent = total.toLocaleString();
                            const ph = zone.querySelector('[data-empty]');
                            if (ph) ph.classList.toggle('hidden', cards.length > 0);
                        });
                    },
                };
            }
        </script>
    @else
        {{-- List view --}}
        <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr><th class="px-5 py-3 font-semibold">Deal</th><th class="px-5 py-3 font-semibold">Client</th><th class="px-5 py-3 text-right font-semibold">Value</th><th class="px-5 py-3 font-semibold">Stage</th><th class="px-5 py-3 font-semibold">Win %</th><th class="px-5 py-3 font-semibold">Close</th><th class="px-5 py-3 font-semibold">Owner</th><th class="px-5 py-3 text-right font-semibold">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($deals as $deal)
                        <tr class="hover:bg-gray-50 {{ $deal->isFollowUpDue() ? 'bg-amber-50/50' : '' }}">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.deals.show', $deal) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $deal->title }}</a>
                                <p class="text-xs text-[var(--color-muted)]">{{ $deal->project_type ?: 'Deal' }}@if ($deal->lead) · from {{ $deal->lead->full_name }}@endif</p>
                                @if ($deal->next_follow_up_at)
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-semibold {{ $deal->isFollowUpDue() ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500' }}" @if ($deal->follow_up_note) title="{{ $deal->follow_up_note }}" @endif>
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
                                        {{ $deal->follow_up_title ?: 'Follow-up' }} · {{ $deal->next_follow_up_at->format('d M Y, g:i A') }}{{ $deal->isFollowUpDue() ? ' · due' : '' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $deal->client?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right font-medium text-[var(--color-heading)]">{{ $cur[$deal->currency] ?? '' }}{{ number_format($deal->value, 0) }}</td>
                            <td class="px-5 py-3">
                                {{-- Quick stage change (like the screenshot's stage dropdown) --}}
                                <form method="POST" action="{{ route('admin.deals.stage', $deal) }}" data-turbo="false">
                                    @csrf
                                    <select name="stage" onchange="this.form.submit()" class="h-9 rounded-lg border-gray-200 text-xs font-semibold {{ $stageBadge[$deal->stage] ?? '' }} focus:ring-[var(--color-primary)]">
                                        @foreach (\App\Models\Deal::stages() as $sk => $sl)<option value="{{ $sk }}" @selected($deal->stage === $sk)>{{ $sl }}</option>@endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $deal->effective_probability }}%</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $deal->expected_close_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $deal->assignee?->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @php $me = auth()->user(); @endphp
                                <div class="relative inline-block text-left" x-data="{ open: false, followUp: false }">
                                    <button type="button" @click="open = !open" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-gray-100" title="Actions">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>
                                    <div x-show="open" @click.outside="open = false; followUp = false" x-cloak
                                         class="absolute right-0 z-20 mt-1 w-56 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 text-left shadow-lg">
                                        <a href="{{ route('admin.deals.show', $deal) }}" class="flex items-center gap-2.5 px-3 py-2 text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="2.5"/></svg> View
                                        </a>
                                        @if ($me->allows('deals', 'edit'))
                                            <a href="{{ route('admin.deals.edit', $deal) }}" class="flex items-center gap-2.5 px-3 py-2 text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg> Edit
                                            </a>

                                            {{-- Add Follow Up: opens the shared popup modal --}}
                                            <button type="button" @click="open = false; $dispatch('open-followup', { action: '{{ route('admin.deals.follow-up', $deal) }}', dealTitle: @js($deal->title) })" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg> Add Follow Up
                                            </button>
                                        @endif
                                        @if ($me->allows('deals', 'delete'))
                                            <div class="my-1 border-t border-gray-100"></div>
                                            <form method="POST" action="{{ route('admin.deals.destroy', $deal) }}" onsubmit="return confirm('Delete this deal?')" data-turbo="false">
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
                        <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400">No deals yet — <a href="{{ route('admin.deals.create') }}" class="font-semibold text-[var(--color-primary)] hover:underline">create your first deal</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $deals->links() }}</div>

        @include('admin.deals._followup-modal')
    @endif
@endsection
