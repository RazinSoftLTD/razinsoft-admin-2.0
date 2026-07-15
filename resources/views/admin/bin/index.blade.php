@extends('admin.layouts.app')
@section('title', 'Bin')

@php $cur = \App\Models\Currency::symbolMap(); @endphp

@section('content')
    <div x-data="{ tab: 'clients', cSel: 0, iSel: 0,
                   recount() { this.cSel = document.querySelectorAll('.client-check:checked').length; this.iSel = document.querySelectorAll('.invoice-check:checked').length },
                   bulk(formId, checkClass) {
                       const ids = [...document.querySelectorAll(checkClass + ':checked')].map(c => c.value);
                       if (!ids.length) return;
                       const f = document.getElementById(formId);
                       const box = f.querySelector('.bulk-ids'); box.innerHTML = '';
                       ids.forEach(id => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; box.appendChild(i); });
                       f.submit();
                   } }">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Bin</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Deleted clients and invoices are kept here for {{ $retentionDays }} days, then permanently removed. Super admin only.</p>
        </div>

        @if (session('status'))<div class="mb-5 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>@endif

        {{-- hidden bulk forms --}}
        <form id="bulk-clients-restore" method="POST" action="{{ route('admin.bin.clients.bulk-restore') }}" class="hidden">@csrf<div class="bulk-ids"></div></form>
        <form id="bulk-clients-delete" method="POST" action="{{ route('admin.bin.clients.bulk-delete') }}" class="hidden">@csrf @method('DELETE')<div class="bulk-ids"></div></form>
        <form id="bulk-invoices-restore" method="POST" action="{{ route('admin.bin.invoices.bulk-restore') }}" class="hidden">@csrf<div class="bulk-ids"></div></form>
        <form id="bulk-invoices-delete" method="POST" action="{{ route('admin.bin.invoices.bulk-delete') }}" class="hidden">@csrf @method('DELETE')<div class="bulk-ids"></div></form>

        <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="flex gap-1 border-b border-gray-100 px-4 pt-3">
                <button type="button" @click="tab = 'clients'" :class="tab === 'clients' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]'" class="border-b-2 px-4 py-2.5 text-sm font-semibold">Clients <span class="ml-1 rounded-full bg-gray-100 px-1.5 text-xs">{{ $clients->total() }}</span></button>
                <button type="button" @click="tab = 'invoices'" :class="tab === 'invoices' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]'" class="border-b-2 px-4 py-2.5 text-sm font-semibold">Invoices <span class="ml-1 rounded-full bg-gray-100 px-1.5 text-xs">{{ $invoices->total() }}</span></button>
            </div>

            {{-- ===== Clients ===== --}}
            <div x-show="tab === 'clients'" x-cloak>
                {{-- bulk bar --}}
                <div x-show="cSel > 0" x-cloak class="flex items-center justify-between gap-3 border-b border-gray-100 bg-[var(--color-primary-soft)] px-5 py-3 text-sm">
                    <span class="font-semibold text-[var(--color-primary)]"><span x-text="cSel"></span> selected</span>
                    <div class="flex gap-2">
                        <button type="button" @click="bulk('bulk-clients-restore', '.client-check')" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-gray-50">Restore selected</button>
                        <button type="button" @click="if(confirm('Permanently delete '+cSel+' client(s)? This cannot be undone.')) bulk('bulk-clients-delete', '.client-check')" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Delete forever</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="w-10 px-5 py-3"><input type="checkbox" @change="document.querySelectorAll('.client-check').forEach(c => c.checked = $event.target.checked); recount()" class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"></th>
                                <th class="px-5 py-3 font-semibold">Client</th>
                                <th class="px-5 py-3 font-semibold">Email</th>
                                <th class="px-5 py-3 font-semibold">Deleted</th>
                                <th class="px-5 py-3 font-semibold">Auto-purge</th>
                                <th class="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($clients as $c)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3"><input type="checkbox" value="{{ $c->id }}" @change="recount()" class="client-check h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"></td>
                                    <td class="px-5 py-3">
                                        <p class="font-semibold text-[var(--color-heading)]">{{ $c->name }}</p>
                                        @if ($c->company)<p class="text-xs text-[var(--color-muted)]">{{ $c->company }}</p>@endif
                                    </td>
                                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $c->email }}</td>
                                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $c->deleted_at->format('d M Y, h:i A') }}</td>
                                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $c->deleted_at->addDays($retentionDays)->format('d M Y') }} <span class="text-xs text-gray-400">({{ $c->deleted_at->addDays($retentionDays)->diffForHumans() }})</span></td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="{{ route('admin.bin.clients.restore', $c->id) }}">
                                                @csrf<button class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-gray-50">Restore</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.bin.clients.force-delete', $c->id) }}" onsubmit="return confirm('Permanently delete “{{ $c->name }}”? This cannot be undone.')">
                                                @csrf @method('DELETE')<button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Delete forever</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">No deleted clients.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $clients->links() }}</div>
            </div>

            {{-- ===== Invoices ===== --}}
            <div x-show="tab === 'invoices'" x-cloak>
                <div x-show="iSel > 0" x-cloak class="flex items-center justify-between gap-3 border-b border-gray-100 bg-[var(--color-primary-soft)] px-5 py-3 text-sm">
                    <span class="font-semibold text-[var(--color-primary)]"><span x-text="iSel"></span> selected</span>
                    <div class="flex gap-2">
                        <button type="button" @click="bulk('bulk-invoices-restore', '.invoice-check')" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-gray-50">Restore selected</button>
                        <button type="button" @click="if(confirm('Permanently delete '+iSel+' invoice(s)? This cannot be undone.')) bulk('bulk-invoices-delete', '.invoice-check')" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Delete forever</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="w-10 px-5 py-3"><input type="checkbox" @change="document.querySelectorAll('.invoice-check').forEach(c => c.checked = $event.target.checked); recount()" class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"></th>
                                <th class="px-5 py-3 font-semibold">Invoice #</th>
                                <th class="px-5 py-3 font-semibold">Client</th>
                                <th class="px-5 py-3 text-right font-semibold">Total</th>
                                <th class="px-5 py-3 font-semibold">Deleted</th>
                                <th class="px-5 py-3 font-semibold">Auto-purge</th>
                                <th class="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($invoices as $inv)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3"><input type="checkbox" value="{{ $inv->id }}" @change="recount()" class="invoice-check h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"></td>
                                    <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $inv->invoice_number }}</td>
                                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $inv->bill_to_name ?: ($inv->client->name ?? '—') }}</td>
                                    <td class="px-5 py-3 text-right text-[var(--color-heading)]">{{ $cur[$inv->currency] ?? '' }}{{ number_format($inv->total, 2) }}</td>
                                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $inv->deleted_at->format('d M Y, h:i A') }}</td>
                                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $inv->deleted_at->addDays($retentionDays)->format('d M Y') }} <span class="text-xs text-gray-400">({{ $inv->deleted_at->addDays($retentionDays)->diffForHumans() }})</span></td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="{{ route('admin.invoices.bin.restore', $inv->id) }}">
                                                @csrf<button class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-gray-50">Restore</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.invoices.bin.force-delete', $inv->id) }}" onsubmit="return confirm('Permanently delete {{ $inv->invoice_number }}? This cannot be undone.')">
                                                @csrf @method('DELETE')<button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Delete forever</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">No deleted invoices.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $invoices->links() }}</div>
            </div>
        </div>
    </div>
@endsection
