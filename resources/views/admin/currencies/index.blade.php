@extends('admin.layouts.app')
@section('title', 'Currencies')

@section('content')
    <div class="mb-5">
        <h1 class="text-lg font-bold text-[var(--color-heading)]">Currencies</h1>
        <p class="text-sm text-[var(--color-muted)]">Manage the currencies the invoice form offers. Active ones appear in the dropdown.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Add currency --}}
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Add currency</h2>
                <form method="POST" action="{{ route('admin.currencies.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Code <span class="text-red-500">*</span></label>
                        <input name="code" value="{{ old('code') }}" maxlength="8" placeholder="NZD" required
                               class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm uppercase" style="text-transform:uppercase">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Symbol <span class="text-red-500">*</span></label>
                        <input name="symbol" value="{{ old('symbol') }}" maxlength="12" placeholder="NZ$" required
                               class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Name</label>
                        <input name="name" value="{{ old('name') }}" maxlength="255" placeholder="New Zealand Dollar"
                               class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Add currency
                    </button>
                </form>
            </div>
        </div>

        {{-- List --}}
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Code</th>
                                <th class="px-5 py-3 font-semibold">Symbol</th>
                                <th class="px-5 py-3 font-semibold">Name</th>
                                <th class="px-5 py-3 font-semibold">Active</th>
                                <th class="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($currencies as $c)
                                <tr class="hover:bg-gray-50">
                                    <form method="POST" action="{{ route('admin.currencies.update', $c) }}" id="cur-{{ $c->id }}">@csrf @method('PUT')</form>
                                    <td class="px-5 py-3 font-mono font-semibold text-[var(--color-heading)]">{{ $c->code }}</td>
                                    <td class="px-5 py-3">
                                        <input form="cur-{{ $c->id }}" name="symbol" value="{{ $c->symbol }}" maxlength="12"
                                               class="h-9 w-20 rounded-lg border border-gray-200 px-2 text-sm">
                                    </td>
                                    <td class="px-5 py-3">
                                        <input form="cur-{{ $c->id }}" name="name" value="{{ $c->name }}" maxlength="255"
                                               class="h-9 w-full rounded-lg border border-gray-200 px-2 text-sm">
                                    </td>
                                    <td class="px-5 py-3">
                                        <label class="inline-flex cursor-pointer items-center">
                                            <input form="cur-{{ $c->id }}" type="checkbox" name="is_active" value="1" {{ $c->is_active ? 'checked' : '' }}
                                                   class="h-4 w-4 rounded border-gray-300 text-[var(--color-primary)]">
                                        </label>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="submit" form="cur-{{ $c->id }}"
                                                    class="rounded-lg bg-[var(--color-primary-soft)] px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)]">Save</button>
                                            <form method="POST" action="{{ route('admin.currencies.destroy', $c) }}"
                                                  onsubmit="return confirm('Delete {{ $c->code }}?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-100">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-8 text-center text-[var(--color-muted)]">No currencies yet. Add one on the left.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="mt-2 text-xs text-[var(--color-muted)]">A currency used by existing invoices can't be deleted — deactivate it to hide it from the dropdown.</p>
        </div>
    </div>
@endsection
