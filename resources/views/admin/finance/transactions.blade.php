@extends('admin.layouts.app')
@section('title', match ($only) { 'income' => 'Income', 'expense' => 'Expenses', default => 'Transactions' })

@php
    $title = match ($only) { 'income' => 'Income', 'expense' => 'Expenses', default => 'Transactions' };
    $me = auth()->user();
    $canCreate = $me->allows('finance', 'create');
    $canEdit = $me->allows('finance', 'edit');
    $canDelete = $me->allows('finance', 'delete');
    $routeName = match ($only) { 'income' => 'admin.finance.income', 'expense' => 'admin.finance.expenses', default => 'admin.finance.transactions' };
    // What "Add" defaults to on this page.
    $defaultType = $only === 'income' ? 'income' : ($only === 'expense' ? 'expense' : 'expense');
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $title }}</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                @if ($only === 'income')
                    Invoice payments land here automatically; add interest, refunds and other income by hand.
                @elseif ($only === 'expense')
                    Every expense is tied to a wallet or bank account and a category.
                @else
                    Every movement of money. Removed rows stay in the audit trail — nothing is ever purged.
                @endif
            </p>
        </div>
        @if ($canCreate)
            <button type="button" @click="$dispatch('open-tx')" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add {{ $only === 'income' ? 'Income' : ($only === 'expense' ? 'Expense' : 'Transaction') }}
            </button>
        @endif
    </div>

    @include('admin.finance._nav')

    {{-- Totals for the current filter --}}
    <div class="mb-4 flex flex-wrap gap-3">
        <div class="rounded-xl border border-gray-100 bg-white px-5 py-3 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-400">Money in</p>
            <p class="text-lg font-extrabold text-emerald-600">{{ number_format($totals['in'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white px-5 py-3 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-400">Money out</p>
            <p class="text-lg font-extrabold text-red-600">{{ number_format($totals['out'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white px-5 py-3 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-400">Net</p>
            <p class="text-lg font-extrabold text-[var(--color-heading)]">{{ number_format($totals['in'] - $totals['out'], 2) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Reference or note…" class="h-10 w-48 rounded-lg border-gray-200 text-sm">
        @unless ($only)
            <select name="type" class="h-10 rounded-lg border-gray-200 text-sm">
                <option value="">All types</option>
                @foreach (\App\Models\FinanceTransaction::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach
            </select>
        @endunless
        <select name="account" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="">All accounts</option>
            @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((int) request('account') === $a->id)>{{ $a->name }}</option>@endforeach
        </select>
        <select name="category" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="">All categories</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) request('category') === $c->id)>{{ $c->name }}</option>@endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="h-10 rounded-lg border-gray-200 text-sm">
        <input type="date" name="to" value="{{ request('to') }}" class="h-10 rounded-lg border-gray-200 text-sm">
        <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Filter</button>
        @if (request()->hasAny(['search', 'type', 'account', 'category', 'from', 'to']))
            <a href="{{ route($routeName) }}" class="text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">Clear</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm" style="min-width:900px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Date</th>
                    <th class="px-5 py-3 font-semibold">Type</th>
                    <th class="px-5 py-3 font-semibold">Category</th>
                    <th class="px-5 py-3 font-semibold">Account</th>
                    <th class="px-5 py-3 font-semibold">Reference</th>
                    <th class="px-5 py-3 text-right font-semibold">Amount</th>
                    <th class="px-5 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" x-data="{ editing: null }">
                @forelse ($rows as $t)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $t->occurred_on?->format('d M Y') }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $t->direction === 'in' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">{{ $t->typeLabel() }}</span>
                            @if ($t->isAutomatic())
                                <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500" title="Mirrored from an invoice payment">auto</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $t->category?->name ?? '—' }}</td>
                        <td class="px-5 py-3">
                            @if ($t->account)
                                {{ $t->account->name }}
                                @if ($t->counterAccount)<span class="text-xs text-gray-400"> → {{ $t->counterAccount->name }}</span>@endif
                            @else
                                <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-semibold text-amber-700" title="Not tied to a wallet or bank yet">Unassigned</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">
                            @if ($t->invoice)
                                <a href="{{ route('admin.invoices.show', $t->client_invoice_id) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $t->invoice->invoice_number }}</a>
                            @else
                                {{ $t->reference ?: '—' }}
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right font-semibold {{ $t->direction === 'in' ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $t->direction === 'in' ? '+' : '−' }} {{ $t->symbol() }}{{ number_format((float) $t->amount, 2) }}
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-1">
                                @if ($t->receipt)
                                    <a href="{{ \App\Http\Resources\ProductResource::media($t->receipt) }}" target="_blank" rel="noopener" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Receipt">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21.44 11.05 12 20.5a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-3-3l8-8"/></svg>
                                    </a>
                                @endif
                                @if ($canEdit && ! $t->isAutomatic() && ! $t->transfer_group)
                                    <button type="button" @click="editing = editing === {{ $t->id }} ? null : {{ $t->id }}" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </button>
                                @endif
                                @if ($canDelete)
                                    <form method="POST" action="{{ route('admin.finance.transactions.destroy', $t) }}" onsubmit="return confirm('{{ $t->transfer_group ? 'Reverse this transfer (both sides)?' : 'Remove this transaction? It stays in the audit trail.' }}')">
                                        @csrf @method('DELETE')
                                        <button class="rounded p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if ($canEdit && ! $t->isAutomatic() && ! $t->transfer_group)
                        <tr x-show="editing === {{ $t->id }}" x-cloak class="bg-gray-50/60">
                            <td colspan="7" class="px-5 py-3">
                                <form method="POST" action="{{ route('admin.finance.transactions.update', $t) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf @method('PUT')
                                    <select name="account_id" class="h-9 rounded-lg border-gray-200 text-sm">
                                        @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($t->account_id === $a->id)>{{ $a->name }}</option>@endforeach
                                    </select>
                                    <select name="category_id" class="h-9 rounded-lg border-gray-200 text-sm">
                                        <option value="">No category</option>
                                        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($t->category_id === $c->id)>{{ $c->name }}</option>@endforeach
                                    </select>
                                    <input name="amount" type="number" step="0.01" min="0.01" value="{{ (float) $t->amount }}" class="h-9 w-32 rounded-lg border-gray-200 text-sm">
                                    <input name="occurred_on" type="date" value="{{ $t->occurred_on?->format('Y-m-d') }}" class="h-9 rounded-lg border-gray-200 text-sm">
                                    <input name="reference" maxlength="120" value="{{ $t->reference }}" placeholder="Reference" class="h-9 w-40 rounded-lg border-gray-200 text-sm">
                                    <input name="notes" maxlength="1000" value="{{ $t->notes }}" placeholder="Notes" class="h-9 flex-1 rounded-lg border-gray-200 text-sm">
                                    <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                                    <button type="button" @click="editing = null" class="px-2 text-xs text-gray-400">Cancel</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-gray-300">Nothing recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>

    {{-- Add dialog --}}
    @if ($canCreate)
        <div x-data="{ open: false, type: '{{ $defaultType }}' }" @open-tx.window="open = true" @keydown.escape.window="open = false">
            <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="open = false"></div>
            <div x-show="open" x-cloak x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-20" @click.self="open = false">
                <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="text-base font-bold text-[var(--color-heading)]">Record transaction</h3>
                        <button type="button" @click="open = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('admin.finance.transactions.store') }}" enctype="multipart/form-data" class="space-y-4 p-5">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Type <span class="text-red-500">*</span></label>
                                <select name="type" x-model="type" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    @foreach (['income' => 'Income', 'expense' => 'Expense', 'deposit' => 'Deposit', 'withdrawal' => 'Withdrawal', 'refund' => 'Refund', 'adjustment' => 'Manual Adjustment'] as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="type === 'refund' || type === 'adjustment'" x-cloak>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Direction</label>
                                <select name="direction" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    <option value="in">Money in</option>
                                    <option value="out">Money out</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Wallet / Bank <span class="text-red-500">*</span></label>
                                <select name="account_id" required class="h-11 w-full rounded-lg border-gray-200 text-sm" x-ref="acct" @change="$refs.cur.value = $refs.acct.selectedOptions[0].dataset.cur || $refs.cur.value">
                                    <option value="">Select account</option>
                                    @foreach ($accounts as $a)<option value="{{ $a->id }}" data-cur="{{ $a->currency }}">{{ $a->name }} ({{ $a->currency }})</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Category</label>
                                <select name="category_id" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    <option value="">No category</option>
                                    @foreach (\App\Models\FinanceCategory::orderBy('kind')->orderBy('name')->get() as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->kind }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Amount <span class="text-red-500">*</span></label>
                                <input name="amount" type="number" step="0.01" min="0.01" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Currency</label>
                                <select name="currency" x-ref="cur" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    @foreach ($currencies as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Date <span class="text-red-500">*</span></label>
                                <input name="occurred_on" type="date" required value="{{ today()->format('Y-m-d') }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Reference</label>
                                <input name="reference" maxlength="120" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Receipt</label>
                            <input type="file" name="receipt" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Notes</label>
                            <textarea name="notes" rows="2" maxlength="1000" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
