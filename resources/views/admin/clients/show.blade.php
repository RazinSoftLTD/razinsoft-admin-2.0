@extends('admin.layouts.app')
@section('title', $client->name)

@php
    $user = auth()->user();
    $sym = \App\Models\Currency::symbolMap();
    $statusBadge = [
        'draft' => 'bg-gray-100 text-gray-600', 'sent' => 'bg-blue-50 text-blue-700',
        'partially_paid' => 'bg-amber-50 text-amber-700', 'paid' => 'bg-emerald-50 text-emerald-700', 'overdue' => 'bg-red-50 text-red-600',
    ];
    $primary = $sym[$invoices->first()->currency ?? ''] ?? '';
    $flag = collect(config('countries'))->firstWhere('name', $client->country)['flag'] ?? '';
    $mobile = trim($client->dial_code.' '.$client->phone);
    $statusChip = match ($client->status) {
        'active' => ['Active', 'text-emerald-600', 'bg-emerald-500'],
        'inactive' => ['Inactive', 'text-amber-600', 'bg-amber-400'],
        default => ['Blocked', 'text-red-600', 'bg-red-500'],
    };
    $tabs = ['profile' => 'Profile', 'projects' => 'Projects', 'invoices' => 'Invoices', 'payments' => 'Payments', 'documents' => 'Documents', 'notes' => 'Notes', 'tickets' => 'Tickets'];
@endphp

@section('content')
    <div x-data="{ tab: '{{ request('tab', 'profile') }}' }">
        {{-- Breadcrumb --}}
        <div class="mb-4 flex items-center gap-2 text-sm">
            <a href="{{ route('admin.clients.index') }}" class="text-[var(--color-muted)] hover:text-[var(--color-heading)]">Clients</a>
            <span class="text-gray-300">•</span>
            <span class="font-semibold text-[var(--color-heading)]">{{ $client->name }}</span>
        </div>

        {{-- Tab nav --}}
        <div class="mb-6 flex gap-1 overflow-x-auto border-b border-gray-200">
            @foreach ($tabs as $key => $label)
                <button @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]'"
                        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold">{{ $label }}</button>
            @endforeach
        </div>

        {{-- ══ PROFILE ══ --}}
        <div x-show="tab === 'profile'" x-cloak class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-center justify-between gap-3">
                <h2 class="text-base font-bold text-[var(--color-heading)]">Profile Info</h2>
                @if ($user->allows('clients', 'edit'))
                    <a href="{{ route('admin.clients.edit', $client) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg> Edit
                    </a>
                @endif
            </div>
            <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                @php
                    $rows = [
                        'Full Name' => $client->name,
                        'Email' => $client->email,
                        'Company Name' => $client->company,
                        'Mobile' => $mobile,
                        'Country' => trim(($flag ? $flag.' ' : '').$client->country),
                        'Address' => $client->address,
                        'City' => $client->city,
                        'State' => $client->state,
                        'Postal code' => $client->zip,
                        'Status' => \App\Models\User::STATUSES[$client->status] ?? $client->status,
                        'Client since' => $client->created_at?->format('d M, Y'),
                    ];
                @endphp
                @foreach ($rows as $label => $value)
                    <div class="flex justify-between gap-4 border-b border-gray-50 pb-3">
                        <dt class="text-sm text-[var(--color-muted)]">{{ $label }}</dt>
                        <dd class="text-right text-sm font-medium text-[var(--color-heading)]">{{ filled($value) ? $value : '--' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- ══ PROJECTS (placeholder) ══ --}}
        <div x-show="tab === 'projects'" x-cloak class="rounded-xl border border-gray-100 bg-white p-12 text-center shadow-sm">
            <p class="text-sm font-semibold text-[var(--color-heading)]">Projects</p>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Coming soon — this section will be implemented later.</p>
        </div>

        {{-- ══ INVOICES ══ --}}
        <div x-show="tab === 'invoices'" x-cloak
             x-data="{ q: '', statusFilter: 'all', shown: 0,
                       pay: { open: false, id: null, number: '', max: 0, symbol: '' },
                       openPay(id, number, max, symbol) { this.pay = { open: true, id, number, max, symbol }; },
                       copyLink(url) { navigator.clipboard.writeText(url).then(() => alert('Payment link copied to clipboard.')); } }">

            {{-- Toolbar: create + filter + search --}}
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <a href="{{ route('admin.invoices.create', ['client_id' => $client->id]) }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Create Invoice
                </a>
                <div class="ml-auto flex items-center gap-2">
                    <select x-model="statusFilter" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <option value="all">All statuses</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="partial">Partially Paid</option>
                        <option value="paid">Paid</option>
                    </select>
                    <input x-model="q" placeholder="Start typing to search…" class="h-10 w-60 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Invoice</th>
                            <th class="px-5 py-3 font-semibold">Client</th>
                            <th class="px-5 py-3 text-right font-semibold">Total</th>
                            <th class="px-5 py-3 font-semibold">Invoice Date</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($invoices as $inv)
                            @php
                                $due = $inv->amountDue();
                                $paid = (float) $inv->amount_paid;
                                $ps = $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
                                $psMeta = ['paid' => ['Paid', 'text-emerald-600', 'bg-emerald-500'], 'partial' => ['Partially Paid', 'text-amber-600', 'bg-amber-400'], 'unpaid' => ['Unpaid', 'text-red-600', 'bg-red-500']][$ps];
                                $csym = $inv->currencySymbol();
                                $searchStr = strtolower($inv->invoice_number.' '.$client->name.' '.$client->company);
                            @endphp
                            <tr class="hover:bg-gray-50" x-show="(statusFilter === 'all' || statusFilter === '{{ $ps }}') && @js($searchStr).includes(q.toLowerCase().trim())">
                                <td class="px-5 py-3 align-top font-semibold text-[var(--color-heading)]">{{ $inv->invoice_number }}</td>
                                <td class="px-5 py-3 align-top">
                                    <div class="flex items-center gap-2">
                                        @if ($client->photo)
                                            <img src="{{ asset('storage/'.$client->photo) }}" alt="" class="h-8 w-8 rounded-full border border-gray-200 object-cover">
                                        @else
                                            <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]">{{ strtoupper(substr($client->name, 0, 1)) }}</span>
                                        @endif
                                        <span class="leading-tight">
                                            <span class="block font-medium text-[var(--color-heading)]">{{ $client->name }}</span>
                                            @if ($client->company)<span class="block text-xs text-[var(--color-muted)]">{{ $client->company }}</span>@endif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right align-top">
                                    <div class="text-[var(--color-heading)]">Total: {{ $csym }}{{ number_format($inv->total, 2) }}</div>
                                    <div class="text-xs text-emerald-600">Paid: {{ $csym }}{{ number_format($paid, 2) }}</div>
                                    <div class="text-xs text-red-600">Unpaid: {{ $csym }}{{ number_format(max(0, $due), 2) }}</div>
                                </td>
                                <td class="px-5 py-3 align-top text-[var(--color-muted)]">{{ $inv->invoice_date->format('d M, Y') }}</td>
                                <td class="px-5 py-3 align-top"><span class="inline-flex items-center gap-1.5 text-sm font-medium {{ $psMeta[1] }}"><span class="h-2 w-2 rounded-full {{ $psMeta[2] }}"></span> {{ $psMeta[0] }}</span></td>
                                <td class="px-5 py-3 align-top">
                                    <div class="flex justify-end" x-data="{ open: false, x: 0, y: 0, place(btn) { const r = btn.getBoundingClientRect(); this.y = r.bottom + 4; this.x = r.right; } }">
                                        <button @click="open = !open; if (open) place($el)" @click.outside="open = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                                        </button>
                                        <div x-show="open" x-cloak @click="open = false" :style="`top:${y}px; left:${x - 208}px`" class="fixed z-50 w-52 rounded-lg border border-gray-100 bg-white py-1 text-sm shadow-xl ring-1 ring-black/5">
                                            <a href="{{ route('admin.invoices.show', $inv) }}" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">View</a>
                                            <a href="{{ route('admin.invoices.pdf', $inv) }}" target="_blank" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">View / Download PDF</a>
                                            <a href="{{ route('admin.invoices.edit', $inv) }}" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">Edit</a>
                                            @if ($due > 0)
                                                <button type="button" @click="open = false; openPay({{ $inv->id }}, '{{ $inv->invoice_number }}', {{ max(0, $due) }}, '{{ $csym }}')" class="block w-full px-4 py-2 text-left text-[var(--color-heading)] hover:bg-gray-50">Add Payment</button>
                                            @endif
                                            <form method="POST" action="{{ route('admin.invoices.send', $inv) }}">@csrf<button class="block w-full px-4 py-2 text-left text-[var(--color-heading)] hover:bg-gray-50">Send</button></form>
                                            <form method="POST" action="{{ route('admin.invoices.request-payment', $inv) }}">@csrf<button class="block w-full px-4 py-2 text-left text-[var(--color-heading)] hover:bg-gray-50">Payment Reminder</button></form>
                                            <button type="button" @click="open = false; copyLink('{{ $inv->payUrl() }}')" class="block w-full px-4 py-2 text-left text-[var(--color-heading)] hover:bg-gray-50">Copy Payment Link</button>
                                            <a href="{{ $inv->payUrl() }}" target="_blank" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">View Payment Page</a>
                                            <div class="my-1 border-t border-gray-100"></div>
                                            <form method="POST" action="{{ route('admin.invoices.destroy', $inv) }}" onsubmit="return confirm('Delete this invoice?')">@csrf @method('DELETE')<button class="block w-full px-4 py-2 text-left text-red-600 hover:bg-red-50">Delete</button></form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-[var(--color-muted)]">No invoices for this client yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="border-t border-gray-100 px-5 py-3 text-sm text-[var(--color-muted)]">Showing {{ $invoices->count() }} invoice(s)</div>
            </div>

            {{-- Add Payment modal (shared across rows) --}}
            <div x-show="pay.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="pay.open = false">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    <h3 class="text-base font-bold text-[var(--color-heading)]">Add payment — <span x-text="pay.number"></span></h3>
                    <form method="POST" :action="'{{ url('admin/invoices') }}/' + pay.id + '/payments'" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Amount</label>
                            <input type="number" step="0.01" min="0.01" :max="pay.max" :value="pay.max.toFixed(2)" name="amount" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <p class="mt-1 text-xs text-gray-400">Due: <span x-text="pay.symbol + pay.max.toFixed(2)"></span></p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Date</label>
                            <input type="date" name="paid_at" value="{{ now()->format('Y-m-d') }}" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Method</label>
                            <select name="method" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                <option value="">—</option>
                                @foreach (\App\Models\ClientInvoice::PAYMENT_METHODS as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Reference <span class="font-normal text-[var(--color-muted)]">(optional)</span></label>
                            <input name="reference" placeholder="Txn ID, cheque no…" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="pay.open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Record payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ══ PAYMENTS ══ --}}
        <div x-show="tab === 'payments'" x-cloak class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Date</th>
                            <th class="px-5 py-3 font-semibold">Invoice #</th>
                            <th class="px-5 py-3 text-right font-semibold">Amount</th>
                            <th class="px-5 py-3 font-semibold">Method</th>
                            <th class="px-5 py-3 font-semibold">Reference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($payments as $p)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->paid_at?->format('d M Y') }}</td>
                                <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $p->invoice->invoice_number ?? '—' }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-emerald-600">{{ $sym[$p->invoice->currency ?? ''] ?? '' }}{{ number_format($p->amount, 2) }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->method ?: '—' }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->reference ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-[var(--color-muted)]">No payments recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ══ DOCUMENTS ══ --}}
        <div x-show="tab === 'documents'" x-cloak class="space-y-4">
            @if ($user->allows('clients', 'edit'))
                <form method="POST" action="{{ route('admin.clients.documents.store', $client) }}" enctype="multipart/form-data"
                      class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    @csrf
                    <div class="grow">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Document name <span class="font-normal text-[var(--color-muted)]">(optional)</span></label>
                        <input name="name" placeholder="e.g. Signed contract" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>
                    <div class="grow">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">File</label>
                        <input type="file" name="file" required class="block w-full text-sm text-[var(--color-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-heading)] hover:file:bg-gray-200">
                    </div>
                    <button class="h-11 rounded-lg bg-[var(--color-primary)] px-5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Upload</button>
                    @error('file')<p class="w-full text-xs text-red-600">{{ $message }}</p>@enderror
                </form>
            @endif

            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Name</th>
                                <th class="px-5 py-3 font-semibold">Size</th>
                                <th class="px-5 py-3 font-semibold">Uploaded by</th>
                                <th class="px-5 py-3 font-semibold">Date</th>
                                <th class="px-5 py-3 text-right font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($documents as $doc)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ $doc->name }}</td>
                                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $doc->readableSize() }}</td>
                                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $doc->uploader->name ?? '—' }}</td>
                                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $doc->created_at?->format('d M Y') }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ asset('storage/'.$doc->path) }}" target="_blank" class="text-sm font-semibold text-[var(--color-primary)] hover:underline">Download</a>
                                            @if ($user->allows('clients', 'edit'))
                                                <form method="POST" action="{{ route('admin.clients.documents.destroy', [$client, $doc]) }}" onsubmit="return confirm('Delete this document?')">
                                                    @csrf @method('DELETE')
                                                    <button class="text-sm font-semibold text-red-600 hover:underline">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-[var(--color-muted)]">No documents uploaded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ══ NOTES ══ --}}
        <div x-show="tab === 'notes'" x-cloak class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-base font-bold text-[var(--color-heading)]">Notes</h2>
            @if (filled($client->note))
                <div class="prose prose-sm max-w-none text-[var(--color-heading)]">{!! $client->note !!}</div>
            @else
                <p class="text-sm text-[var(--color-muted)]">No notes yet. Add one from the <a href="{{ route('admin.clients.edit', $client) }}" class="font-semibold text-[var(--color-primary)] hover:underline">edit page</a>.</p>
            @endif
        </div>

        {{-- ══ TICKETS (placeholder) ══ --}}
        <div x-show="tab === 'tickets'" x-cloak class="rounded-xl border border-gray-100 bg-white p-12 text-center shadow-sm">
            <p class="text-sm font-semibold text-[var(--color-heading)]">Tickets</p>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Coming soon — this section will be implemented later.</p>
        </div>
    </div>
@endsection
