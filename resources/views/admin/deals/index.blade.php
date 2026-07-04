@extends('admin.layouts.app')
@section('title', 'Deals')

@php
    $stageBadge = [
        'new' => 'bg-gray-100 text-gray-600', 'qualified' => 'bg-indigo-50 text-indigo-700',
        'proposal' => 'bg-orange-50 text-orange-700', 'negotiation' => 'bg-amber-50 text-amber-700',
        'won' => 'bg-emerald-50 text-emerald-700', 'lost' => 'bg-red-50 text-red-600',
    ];
    $cur = ['USD' => '$', 'BDT' => '৳', 'EUR' => '€', 'GBP' => '£'];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Deals</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; Deals</p>
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

    {{-- Stats --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($stats as $s)
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <p class="text-xs text-[var(--color-muted)]">{{ $s['label'] }}</p>
                <p class="mt-1 text-xl font-bold text-[var(--color-heading)]">{{ $s['value'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($view === 'board')
        {{-- Kanban board --}}
        <div class="flex gap-4 overflow-x-auto pb-4">
            @foreach (\App\Models\Deal::STAGES as $key => $label)
                @php $col = $byStage->get($key, collect()); @endphp
                <div class="w-72 shrink-0">
                    <div class="mb-3 flex items-center justify-between px-1">
                        <span class="text-sm font-bold text-[var(--color-heading)]">{{ $label }}</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ $col->count() }}</span>
                    </div>
                    <div class="space-y-2">
                        @forelse ($col as $deal)
                            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                                <a href="{{ route('admin.deals.edit', $deal) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $deal->title }}</a>
                                <p class="mt-1 text-lg font-bold text-[var(--color-heading)]">{{ $cur[$deal->currency] ?? '' }}{{ number_format($deal->value, 0) }}</p>
                                <div class="mt-2 flex items-center justify-between text-xs text-[var(--color-muted)]">
                                    <span>{{ $deal->client?->name ?? '—' }}</span>
                                    @if ($deal->assignee)<span class="grid h-6 w-6 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[10px] font-bold text-[var(--color-primary)]">{{ strtoupper(substr($deal->assignee->name, 0, 1)) }}</span>@endif
                                </div>
                                <div class="mt-3 flex items-center gap-1 border-t border-gray-50 pt-2">
                                    <form method="POST" action="{{ route('admin.deals.stage', $deal) }}" class="flex-1">
                                        @csrf
                                        <select name="stage" onchange="this.form.submit()" class="h-7 w-full rounded border-gray-200 text-[11px]">
                                            @foreach (\App\Models\Deal::STAGES as $sk => $sl)<option value="{{ $sk }}" @selected($deal->stage === $sk)>{{ $sl }}</option>@endforeach
                                        </select>
                                    </form>
                                    @if ($deal->isWon())
                                        <form method="POST" action="{{ route('admin.deals.invoice', $deal) }}">@csrf
                                            <button class="rounded p-1 text-emerald-600 hover:bg-emerald-50" title="Create invoice"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M7 3h7l5 5v13H7zM14 3v5h5"/></svg></button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-200 py-6 text-center text-xs text-gray-300">No deals</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- List --}}
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr><th class="px-5 py-3 font-semibold">Deal</th><th class="px-5 py-3 font-semibold">Client</th><th class="px-5 py-3 text-right font-semibold">Value</th><th class="px-5 py-3 font-semibold">Stage</th><th class="px-5 py-3 font-semibold">Close</th><th class="px-5 py-3 font-semibold">Owner</th><th class="px-5 py-3 text-right font-semibold">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($deals as $deal)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $deal->title }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $deal->client?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right font-medium text-[var(--color-heading)]">{{ $cur[$deal->currency] ?? '' }}{{ number_format($deal->value, 2) }}</td>
                                <td class="px-5 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $stageBadge[$deal->stage] ?? '' }}">{{ \App\Models\Deal::STAGES[$deal->stage] ?? $deal->stage }}</span></td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $deal->expected_close_date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $deal->assignee?->name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($deal->isWon())<form method="POST" action="{{ route('admin.deals.invoice', $deal) }}">@csrf<button class="rounded-lg p-2 text-emerald-600 hover:bg-emerald-50" title="Create invoice"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M7 3h7l5 5v13H7zM14 3v5h5"/></svg></button></form>@endif
                                        <a href="{{ route('admin.deals.edit', $deal) }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Edit"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></a>
                                        <form method="POST" action="{{ route('admin.deals.destroy', $deal) }}" onsubmit="return confirm('Delete this deal?')">@csrf @method('DELETE')<button class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg></button></form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">No deals yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $deals->links() }}</div>
    @endif
@endsection
