@extends('admin.layouts.app')
@section('title', 'VAT & Tax')

@php
    $me = auth()->user();
    $canCreate = $me->allows('finance', 'create');
    $canEdit = $me->allows('finance', 'edit');
    $canDelete = $me->allows('finance', 'delete');
    $sym = \App\Models\Currency::symbolMap();
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">VAT &amp; Tax</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">VAT, tax and withholding amounts by month, with what is still pending.</p>
        </div>
        @if ($canCreate)
            <button type="button" @click="$dispatch('open-tax')" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Entry
            </button>
        @endif
    </div>

    @include('admin.finance._nav')

    {{-- Monthly summary --}}
    @if ($monthly->isNotEmpty())
        <div class="mb-5 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-2.5 font-semibold">Month</th>
                        @foreach (\App\Models\FinanceTax::KINDS as $k => $label)<th class="px-5 py-2.5 text-right font-semibold">{{ $label }}</th>@endforeach
                        <th class="px-5 py-2.5 text-right font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($monthly as $month => $byKind)
                        <tr>
                            <td class="px-5 py-2.5 font-semibold text-[var(--color-heading)]">{{ \Carbon\Carbon::parse($month.'-01')->format('M Y') }}</td>
                            @foreach (\App\Models\FinanceTax::KINDS as $k => $label)
                                <td class="px-5 py-2.5 text-right text-[var(--color-muted)]">{{ number_format((float) ($byKind[$k] ?? 0), 2) }}</td>
                            @endforeach
                            <td class="px-5 py-2.5 text-right font-bold text-[var(--color-heading)]">{{ number_format((float) collect($byKind)->sum(), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <select name="kind" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="">All kinds</option>
            @foreach (\App\Models\FinanceTax::KINDS as $k => $v)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="status" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="">All statuses</option>
            @foreach (\App\Models\FinanceTax::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
        </select>
        <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Filter</button>
        @if (request()->hasAny(['kind', 'status']))
            <a href="{{ route('admin.finance.taxes') }}" class="text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">Clear</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm" style="min-width:820px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Period</th>
                    <th class="px-5 py-3 font-semibold">Kind</th>
                    <th class="px-5 py-3 font-semibold">Title</th>
                    <th class="px-5 py-3 font-semibold">Due</th>
                    <th class="px-5 py-3 text-right font-semibold">Amount</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" x-data="{ editing: null }">
                @forelse ($rows as $t)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $t->period?->format('M Y') }}</td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ \App\Models\FinanceTax::KINDS[$t->kind] ?? $t->kind }}</td>
                        <td class="px-5 py-3 text-[var(--color-heading)]">{{ $t->title }}</td>
                        <td class="px-5 py-3 {{ $t->isOverdue() ? 'font-semibold text-red-600' : 'text-[var(--color-muted)]' }}">
                            {{ $t->due_date?->format('d M Y') ?? '—' }}{{ $t->isOverdue() ? ' · overdue' : '' }}
                        </td>
                        <td class="px-5 py-3 text-right font-bold text-[var(--color-heading)]">{{ $t->symbol() }}{{ number_format((float) $t->amount, 2) }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $t->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                {{ \App\Models\FinanceTax::STATUSES[$t->status] ?? $t->status }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-1">
                                @if ($canEdit)
                                    <button type="button" @click="editing = editing === {{ $t->id }} ? null : {{ $t->id }}" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </button>
                                @endif
                                @if ($canDelete)
                                    <form method="POST" action="{{ route('admin.finance.taxes.destroy', $t) }}" onsubmit="return confirm('Remove this entry?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if ($canEdit)
                        <tr x-show="editing === {{ $t->id }}" x-cloak class="bg-gray-50/60">
                            <td colspan="7" class="px-5 py-3">
                                <form method="POST" action="{{ route('admin.finance.taxes.update', $t) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf @method('PUT')
                                    <select name="kind" class="h-9 rounded-lg border-gray-200 text-sm">
                                        @foreach (\App\Models\FinanceTax::KINDS as $k => $v)<option value="{{ $k }}" @selected($t->kind === $k)>{{ $v }}</option>@endforeach
                                    </select>
                                    <input name="title" required maxlength="150" value="{{ $t->title }}" class="h-9 flex-1 rounded-lg border-gray-200 text-sm">
                                    <input name="amount" type="number" step="0.01" min="0" value="{{ (float) $t->amount }}" class="h-9 w-28 rounded-lg border-gray-200 text-sm">
                                    <select name="currency" class="h-9 rounded-lg border-gray-200 text-sm">
                                        @foreach ($currencies as $c)<option value="{{ $c }}" @selected($t->currency === $c)>{{ $c }}</option>@endforeach
                                    </select>
                                    <input name="period" type="date" value="{{ $t->period?->format('Y-m-d') }}" class="h-9 rounded-lg border-gray-200 text-sm">
                                    <input name="due_date" type="date" value="{{ $t->due_date?->format('Y-m-d') }}" class="h-9 rounded-lg border-gray-200 text-sm">
                                    <select name="status" class="h-9 rounded-lg border-gray-200 text-sm">
                                        @foreach (\App\Models\FinanceTax::STATUSES as $k => $v)<option value="{{ $k }}" @selected($t->status === $k)>{{ $v }}</option>@endforeach
                                    </select>
                                    <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                                    <button type="button" @click="editing = null" class="px-2 text-xs text-gray-400">Cancel</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-gray-300">No tax entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>

    @if ($canCreate)
        <div x-data="{ open: false }" @open-tax.window="open = true" @keydown.escape.window="open = false">
            <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="open = false"></div>
            <div x-show="open" x-cloak x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-20" @click.self="open = false">
                <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="text-base font-bold text-[var(--color-heading)]">Add VAT / Tax entry</h3>
                        <button type="button" @click="open = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('admin.finance.taxes.store') }}" class="space-y-4 p-5">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Kind <span class="text-red-500">*</span></label>
                                <select name="kind" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    @foreach (\App\Models\FinanceTax::KINDS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Status</label>
                                <select name="status" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    @foreach (\App\Models\FinanceTax::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Title <span class="text-red-500">*</span></label>
                            <input name="title" required maxlength="150" placeholder="e.g. July VAT return" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Amount <span class="text-red-500">*</span></label>
                                <input name="amount" type="number" step="0.01" min="0" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Currency</label>
                                <select name="currency" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    @foreach ($currencies as $c)<option value="{{ $c }}" @selected($c === 'BDT')>{{ $c }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Period (month) <span class="text-red-500">*</span></label>
                                <input name="period" type="date" required value="{{ today()->startOfMonth()->format('Y-m-d') }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Due date</label>
                                <input name="due_date" type="date" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Reference</label>
                            <input name="reference" maxlength="120" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
