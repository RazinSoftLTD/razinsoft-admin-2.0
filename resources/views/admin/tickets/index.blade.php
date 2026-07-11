@extends('admin.layouts.app')
@section('title', 'Tickets')

@php
    $statusDot = ['open' => 'bg-red-500', 'pending' => 'bg-amber-400', 'resolved' => 'bg-emerald-500', 'closed' => 'bg-gray-400'];
    $prioColor = ['urgent' => 'text-red-600', 'high' => 'text-orange-600', 'medium' => 'text-blue-600', 'low' => 'text-gray-500'];
    $ticketIcon = 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7Z';
    $cards = [
        ['Total Tickets', $counts['all']],
        ['Closed Tickets', $counts['closed']],
        ['Open Tickets', $counts['open']],
        ['Pending Tickets', $counts['pending']],
        ['Resolved Tickets', $counts['resolved']],
    ];
@endphp

@section('content')
    {{-- Filter bar --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-x-6 gap-y-3 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
        <div class="flex items-center gap-2">
            <span class="text-sm text-[var(--color-muted)]">Duration</span>
            <input type="date" name="from" value="{{ $from }}" class="h-9 rounded-lg border border-gray-200 px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
            <span class="text-xs text-gray-400">to</span>
            <input type="date" name="to" value="{{ $to }}" class="h-9 rounded-lg border border-gray-200 px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
        </div>
        <div class="flex items-center gap-2">
            <span class="text-sm text-[var(--color-muted)]">Status</span>
            <select name="status" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                <option value="">All</option>
                @foreach (\App\Models\Ticket::STATUSES as $val => $label)<option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="relative min-w-56 flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14ZM21 21l-4.3-4.3"/></svg>
            <input name="search" value="{{ $search }}" placeholder="Start typing to search" class="h-9 w-full rounded-lg border border-gray-200 pl-9 pr-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
        </div>
        <button class="inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4Z"/></svg> Filters
        </button>
    </form>

    {{-- Stat cards --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($cards as [$label, $val])
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <p class="text-sm text-[var(--color-muted)]">{{ $label }}</p>
                    <svg class="h-6 w-6 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ticketIcon }}"/></svg>
                </div>
                <p class="mt-3 text-2xl font-bold text-[var(--color-primary)]">{{ $val }}</p>
            </div>
        @endforeach
    </div>

    {{-- Actions --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.tickets.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Create Ticket
        </a>
        <a href="{{ route('admin.tickets.export', request()->query()) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg> Export
        </a>
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="w-10 px-5 py-3"><input type="checkbox" x-on:change="$root.querySelectorAll('.row-check').forEach(c => c.checked = $event.target.checked)" class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"></th>
                        <th class="px-5 py-3">Ticket #</th>
                        <th class="px-5 py-3">Ticket Subject</th>
                        <th class="px-5 py-3">Requester Name</th>
                        <th class="px-5 py-3">Requested On</th>
                        <th class="px-5 py-3">Others</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" x-data>
                    @forelse ($tickets as $t)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-4 align-top"><input type="checkbox" value="{{ $t->id }}" class="row-check h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"></td>
                            <td class="px-5 py-4 align-top font-semibold"><a href="{{ route('admin.tickets.show', $t) }}" class="text-[var(--color-primary)] hover:underline">{{ $t->ticket_number }}</a></td>
                            <td class="px-5 py-4 align-top">
                                <span class="flex items-center gap-2">
                                    @if ($t->unread_by_admin)<span class="h-2 w-2 shrink-0 rounded-full bg-red-500" title="New message"></span>@endif
                                    <a href="{{ route('admin.tickets.show', $t) }}" class="{{ $t->unread_by_admin ? 'font-bold' : 'font-medium' }} text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ \Illuminate\Support\Str::limit($t->subject, 90) }}</a>
                                </span>
                                @if ($t->unread_by_admin)
                                    <span class="mt-1 inline-block rounded bg-red-500 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">New message</span>
                                @elseif (($t->admin_replies_count ?? 0) === 0)
                                    <span class="mt-1 inline-block rounded bg-emerald-500 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">New</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 align-top">
                                <div class="flex items-center gap-2">
                                    @if ($t->client?->photo)
                                        <img src="{{ asset('storage/'.$t->client->photo) }}" alt="" class="h-8 w-8 rounded-full object-cover">
                                    @else
                                        <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]">{{ strtoupper(substr($t->client->name ?? '?', 0, 1)) }}</span>
                                    @endif
                                    <span class="leading-tight">
                                        <span class="block font-medium text-[var(--color-heading)]">{{ $t->client->name ?? '—' }}</span>
                                        @if ($t->client?->company)<span class="block text-xs text-[var(--color-muted)]">{{ $t->client->company }}</span>@endif
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-4 align-top text-[var(--color-muted)]">{{ $t->created_at?->format('d F, Y h:i a') }}</td>
                            <td class="px-5 py-4 align-top text-sm">
                                <span class="text-[var(--color-muted)]">Priority:</span>
                                <span class="font-semibold {{ $prioColor[$t->priority] ?? 'text-gray-500' }}">{{ $t->priorityLabel() }}</span>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <form method="POST" action="{{ route('admin.tickets.status', $t) }}">
                                    @csrf @method('PATCH')
                                    <span class="relative inline-flex items-center">
                                        <span class="pointer-events-none absolute left-3 z-10 h-2 w-2 rounded-full {{ $statusDot[$t->status] ?? 'bg-gray-400' }}"></span>
                                        <select name="status" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-200 bg-white pl-7 pr-8 text-sm font-medium focus:border-[var(--color-primary)] focus:outline-none">
                                            @foreach (\App\Models\Ticket::STATUSES as $val => $label)<option value="{{ $val }}" @selected($t->status === $val)>{{ $label }}</option>@endforeach
                                        </select>
                                    </span>
                                </form>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <div class="relative flex justify-end" x-data="{ open: false, x: 0, y: 0, place(b) { const r = b.getBoundingClientRect(); this.y = r.bottom + 4; this.x = r.right; } }">
                                    <button @click="open = !open; if (open) place($el)" @click.outside="open = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                                    </button>
                                    <div x-show="open" x-cloak @click="open = false" :style="`top:${y}px; left:${x - 176}px`" class="fixed z-50 w-44 rounded-lg border border-gray-100 bg-white py-1 text-sm shadow-xl ring-1 ring-black/5">
                                        <a href="{{ route('admin.tickets.show', $t) }}" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">View / Reply</a>
                                        @if ($t->client)<a href="{{ route('admin.clients.show', $t->client) }}" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">View customer</a>@endif
                                        @if (auth()->user()->isAdmin())
                                            <div class="my-1 border-t border-gray-100"></div>
                                            <form method="POST" action="{{ route('admin.tickets.destroy', $t) }}" onsubmit="return confirm('Delete this ticket?')">
                                                @csrf @method('DELETE')
                                                <button class="block w-full px-4 py-2 text-left text-red-600 hover:bg-red-50">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400">No tickets found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-gray-100 px-5 py-3 text-sm text-[var(--color-muted)]">
            <form method="GET" class="flex items-center gap-2">
                @foreach (request()->except(['per_page', 'page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <span>Show</span>
                <select name="per_page" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    @foreach ([10, 25, 50, 100] as $n)<option value="{{ $n }}" @selected((int) request('per_page', 10) === $n)>{{ $n }}</option>@endforeach
                </select>
                <span>entries</span>
            </form>
            @if ($tickets->total())
                <span>Showing {{ $tickets->firstItem() }} to {{ $tickets->lastItem() }} of {{ $tickets->total() }} entries</span>
            @endif
            <div>{{ $tickets->onEachSide(1)->links() }}</div>
        </div>
    </div>
@endsection
