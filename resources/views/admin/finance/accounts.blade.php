@extends('admin.layouts.app')
@section('title', $type === 'bank' ? 'Bank Accounts' : 'Wallets')

@php
    $isBank = $type === 'bank';
    $title = $isBank ? 'Bank Accounts' : 'Wallets';
    $me = auth()->user();
    $canCreate = $me->allows('finance', 'create');
    $canEdit = $me->allows('finance', 'edit');
    $canDelete = $me->allows('finance', 'delete');
    $sym = \App\Models\Currency::symbolMap();
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $title }}</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                {{ $isBank ? 'Company bank accounts and their balances.' : 'Payoneer, Wise, Stripe, Mercury, Cash…' }}
                Balances update themselves from transactions.
            </p>
        </div>
        @if ($canCreate)
            <button type="button" @click="$dispatch('open-account')" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                Add {{ $isBank ? 'Bank Account' : 'Wallet' }}
            </button>
        @endif
    </div>

    @include('admin.finance._nav')

    {{-- Totals per currency --}}
    @if (count($totals))
        <div class="mb-5 flex flex-wrap gap-3">
            @foreach ($totals as $cur => $amount)
                <div class="rounded-xl border border-gray-100 bg-white px-5 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-400">{{ $cur }} total</p>
                    <p class="text-xl font-extrabold text-[var(--color-heading)]">{{ $sym[$cur] ?? '' }}{{ number_format($amount, 2) }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search {{ strtolower($title) }}…" class="h-10 w-56 rounded-lg border-gray-200 text-sm">
        <select name="status" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="">All statuses</option>
            @foreach (\App\Models\FinanceAccount::STATUSES as $k => $v)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Filter</button>
        @if (request()->hasAny(['search', 'status']))
            <a href="{{ route($isBank ? 'admin.finance.banks' : 'admin.finance.wallets') }}" class="text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">Clear</a>
        @endif
    </form>

    {{-- Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" x-data="{ editing: null }">
        @forelse ($accounts as $account)
            <div class="rounded-xl border {{ $account->isActive() ? 'border-gray-100' : 'border-dashed border-gray-200 opacity-70' }} bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-bold text-[var(--color-heading)]">{{ $account->name }}</p>
                        <p class="text-xs text-[var(--color-muted)]">
                            {{ $account->provider ?: ($isBank ? 'Bank' : 'Wallet') }} · {{ $account->currency }}
                            @if ($account->account_number) · {{ $account->account_number }} @endif
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $account->isActive() ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ \App\Models\FinanceAccount::STATUSES[$account->status] ?? $account->status }}
                    </span>
                </div>

                <p class="mt-4 text-2xl font-extrabold text-[var(--color-heading)]">
                    {{ $account->symbol() }}{{ number_format((float) $account->current_balance, 2) }}
                </p>
                <p class="text-xs text-[var(--color-muted)]">Opening {{ $account->symbol() }}{{ number_format((float) $account->opening_balance, 2) }}</p>

                <div class="mt-4 flex items-center gap-2 border-t border-gray-50 pt-3">
                    <a href="{{ route('admin.finance.transactions', ['account' => $account->id]) }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">Transactions</a>
                    @if ($canEdit)
                        <button type="button" @click="editing = editing === {{ $account->id }} ? null : {{ $account->id }}" class="ml-auto rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Edit">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        </button>
                    @endif
                    @if ($canDelete)
                        <form method="POST" action="{{ route('admin.finance.accounts.destroy', $account) }}" onsubmit="return confirm('Remove “{{ $account->name }}”?')">
                            @csrf @method('DELETE')
                            <button class="rounded-lg p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                            </button>
                        </form>
                    @endif
                </div>

                @if ($canEdit)
                    <form x-show="editing === {{ $account->id }}" x-cloak method="POST" action="{{ route('admin.finance.accounts.update', $account) }}" class="mt-3 space-y-2 border-t border-gray-50 pt-3">
                        @csrf @method('PUT')
                        <input type="hidden" name="type" value="{{ $account->type }}">
                        <input name="name" required maxlength="120" value="{{ $account->name }}" class="h-9 w-full rounded-lg border-gray-200 text-sm" placeholder="Name">
                        <div class="grid grid-cols-2 gap-2">
                            <input name="provider" maxlength="60" value="{{ $account->provider }}" class="h-9 w-full rounded-lg border-gray-200 text-sm" placeholder="Provider">
                            <select name="currency" class="h-9 w-full rounded-lg border-gray-200 text-sm">
                                @foreach ($currencies as $c)<option value="{{ $c }}" @selected($account->currency === $c)>{{ $c }}</option>@endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <input name="opening_balance" type="number" step="0.01" value="{{ (float) $account->opening_balance }}" class="h-9 w-full rounded-lg border-gray-200 text-sm" placeholder="Opening">
                            <select name="status" class="h-9 w-full rounded-lg border-gray-200 text-sm">
                                @foreach (\App\Models\FinanceAccount::STATUSES as $k => $v)<option value="{{ $k }}" @selected($account->status === $k)>{{ $v }}</option>@endforeach
                            </select>
                        </div>
                        <input name="account_number" maxlength="60" value="{{ $account->account_number }}" class="h-9 w-full rounded-lg border-gray-200 text-sm" placeholder="Account number (optional)">
                        <div class="flex gap-2">
                            <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                            <button type="button" @click="editing = null" class="px-2 text-xs text-gray-400">Cancel</button>
                        </div>
                    </form>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center sm:col-span-2 lg:col-span-2">
                <p class="text-sm text-gray-400">No {{ strtolower($title) }} yet.</p>
            </div>
        @endforelse
    </div>

    {{-- Add dialog --}}
    @if ($canCreate)
        <div x-data="{ open: false }" @open-account.window="open = true" @keydown.escape.window="open = false">
            <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="open = false"></div>
            <div x-show="open" x-cloak x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-20" @click.self="open = false">
                <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="text-base font-bold text-[var(--color-heading)]">Add {{ $isBank ? 'Bank Account' : 'Wallet' }}</h3>
                        <button type="button" @click="open = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('admin.finance.accounts.store') }}" class="space-y-4 p-5">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Name <span class="text-red-500">*</span></label>
                            <input name="name" required maxlength="120" placeholder="{{ $isBank ? 'e.g. DBBL — Current' : 'e.g. Payoneer USD' }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Provider</label>
                                <input name="provider" list="fin-providers" maxlength="60" class="h-11 w-full rounded-lg border-gray-200 text-sm" placeholder="{{ $isBank ? 'DBBL' : 'Payoneer' }}">
                                <datalist id="fin-providers">@foreach ($providers as $p)<option value="{{ $p }}">@endforeach</datalist>
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
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Opening balance</label>
                                <input name="opening_balance" type="number" step="0.01" value="0" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Status</label>
                                <select name="status" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                    @foreach (\App\Models\FinanceAccount::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        @if ($isBank)
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Account number</label>
                                <input name="account_number" maxlength="60" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                        @endif
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
