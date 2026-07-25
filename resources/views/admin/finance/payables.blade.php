@extends('admin.layouts.app')
@section('title', 'Payables')

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
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Payables</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Bills we owe — hosting, subscriptions, office bills, suppliers, reimbursements.</p>
        </div>
        @if ($canCreate)
            <button type="button" @click="$dispatch('open-payable')" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Bill
            </button>
        @endif
    </div>

    @include('admin.finance._nav')

    @if (count($totals))
        <div class="mb-5 flex flex-wrap gap-3">
            @foreach ($totals as $cur => $due)
                <div class="rounded-xl border border-gray-100 bg-white px-5 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-400">{{ $cur }} outstanding</p>
                    <p class="text-xl font-extrabold text-red-600">{{ $sym[$cur] ?? '' }}{{ number_format((float) $due, 2) }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Vendor…" class="h-10 w-56 rounded-lg border-gray-200 text-sm">
        <select name="status" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="">All statuses</option>
            @foreach (\App\Models\FinancePayable::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
        </select>
        <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Filter</button>
        @if (request()->hasAny(['search', 'status']))
            <a href="{{ route('admin.finance.payables') }}" class="text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">Clear</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm" style="min-width:900px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Vendor</th>
                    <th class="px-5 py-3 font-semibold">Category</th>
                    <th class="px-5 py-3 font-semibold">Due date</th>
                    <th class="px-5 py-3 text-right font-semibold">Amount</th>
                    <th class="px-5 py-3 text-right font-semibold">Paid</th>
                    <th class="px-5 py-3 text-right font-semibold">Outstanding</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" x-data="{ paying: null }">
                @forelse ($rows as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-[var(--color-heading)]">{{ $p->vendor }}</p>
                            @if ($p->reference)<p class="text-xs text-gray-400">{{ $p->reference }}</p>@endif
                        </td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->category?->name ?? '—' }}</td>
                        <td class="px-5 py-3 {{ $p->isOverdue() ? 'font-semibold text-red-600' : 'text-[var(--color-muted)]' }}">
                            {{ $p->due_date?->format('d M Y') ?? '—' }}{{ $p->isOverdue() ? ' · overdue' : '' }}
                        </td>
                        <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ $p->symbol() }}{{ number_format((float) $p->amount, 2) }}</td>
                        <td class="px-5 py-3 text-right text-emerald-600">{{ $p->symbol() }}{{ number_format((float) $p->amount_paid, 2) }}</td>
                        <td class="px-5 py-3 text-right font-bold {{ $p->due() > 0 ? 'text-red-600' : 'text-gray-300' }}">{{ $p->symbol() }}{{ number_format($p->due(), 2) }}</td>
                        <td class="px-5 py-3">
                            @php $chip = ['unpaid' => 'bg-red-50 text-red-600', 'partial' => 'bg-amber-50 text-amber-700', 'paid' => 'bg-emerald-50 text-emerald-700'][$p->status] ?? 'bg-gray-100 text-gray-500'; @endphp
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $chip }}">{{ \App\Models\FinancePayable::STATUSES[$p->status] ?? $p->status }}</span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-1">
                                @if ($canEdit && $p->due() > 0)
                                    <button type="button" @click="paying = paying === {{ $p->id }} ? null : {{ $p->id }}" class="rounded-lg bg-[var(--color-primary-soft)] px-2.5 py-1 text-xs font-semibold text-[var(--color-primary)] hover:opacity-80">Pay</button>
                                @endif
                                @if ($canDelete)
                                    <form method="POST" action="{{ route('admin.finance.payables.destroy', $p) }}" onsubmit="return confirm('Remove this bill?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if ($canEdit && $p->due() > 0)
                        <tr x-show="paying === {{ $p->id }}" x-cloak class="bg-gray-50/60">
                            <td colspan="8" class="px-5 py-3">
                                <form method="POST" action="{{ route('admin.finance.payables.pay', $p) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Pay from</label>
                                        <select name="account_id" required class="h-9 rounded-lg border-gray-200 text-sm">
                                            <option value="">Select account</option>
                                            @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->currency }})</option>@endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Amount</label>
                                        <input name="amount" type="number" step="0.01" min="0.01" value="{{ $p->due() }}" required class="h-9 w-32 rounded-lg border-gray-200 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Date</label>
                                        <input name="occurred_on" type="date" value="{{ today()->format('Y-m-d') }}" required class="h-9 rounded-lg border-gray-200 text-sm">
                                    </div>
                                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-white">Record payment</button>
                                    <button type="button" @click="paying = null" class="px-2 text-xs text-gray-400">Cancel</button>
                                    <p class="w-full text-xs text-[var(--color-muted)]">This also records an expense against the chosen account.</p>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-gray-300">No bills recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>

    @if ($canCreate)
        <div x-data="{ open: false }" @open-payable.window="open = true" @keydown.escape.window="open = false">
            <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="open = false"></div>
            <div x-show="open" x-cloak x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-20" @click.self="open = false">
                <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="text-base font-bold text-[var(--color-heading)]">Add Bill</h3>
                        <button type="button" @click="open = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('admin.finance.payables.store') }}" enctype="multipart/form-data" class="space-y-4 p-5">
                        @csrf
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Vendor <span class="text-red-500">*</span></label>
                            <input name="vendor" required maxlength="150" placeholder="e.g. DigitalOcean" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Category</label>
                                <select name="category_id" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    <option value="">No category</option>
                                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Currency <span class="text-red-500">*</span></label>
                                <select name="currency" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    @foreach ($currencies as $c)<option value="{{ $c }}" @selected($c === 'USD')>{{ $c }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Amount <span class="text-red-500">*</span></label>
                                <input name="amount" type="number" step="0.01" min="0" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Already paid</label>
                                <input name="amount_paid" type="number" step="0.01" min="0" value="0" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Bill date</label>
                                <input name="bill_date" type="date" class="h-11 w-full rounded-lg border-gray-200 text-sm">
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
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Attachment</label>
                            <input type="file" name="attachment" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
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
