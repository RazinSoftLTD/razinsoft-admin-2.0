@extends('admin.layouts.app')
@section('title', 'Logged-in Clients')

@section('content')
    <a href="{{ route('admin.client-activity', request()->only(['date_range', 'from', 'to'])) }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Client Activity
    </a>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Logged-in Clients</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Every client who browsed the website while signed in — one row each. Open a client to see their full visit history.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <select name="date_range" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                <option value="">All time</option>
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $dv => $dl)
                    <option value="{{ $dv }}" @selected(request('date_range') === $dv)>{{ $dl }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="h-10 rounded-lg border border-gray-200 px-2 text-sm">
            <input type="date" name="to" value="{{ request('to') }}" class="h-10 rounded-lg border border-gray-200 px-2 text-sm">
            <button class="h-10 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply</button>
            <a href="{{ route('admin.client-activity.clients') }}" class="h-10 rounded-lg border border-gray-200 px-4 text-sm font-semibold leading-10 text-[var(--color-muted)] hover:bg-gray-50">Clear</a>
        </form>
    </div>

    {{-- Stats --}}
    <div class="mb-6 flex flex-wrap gap-4">
        <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-sky-50 text-sky-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM5 21v-1a6 6 0 0 1 12 0v1M19 8v4M21 10h-4"/></svg>
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Logged-in Clients</p>
                <p class="text-lg font-bold text-[var(--color-heading)]">{{ number_format($totalClients) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Their Visits</p>
                <p class="text-lg font-bold text-[var(--color-heading)]">{{ number_format($totalLogins) }}</p>
            </div>
        </div>
        {{-- Counts only the clients on this page — an active session is looked up per listed client. --}}
        <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z"/></svg>
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Signed in now</p>
                <p class="text-lg font-bold text-[var(--color-heading)]">{{ number_format($activeSessions->count()) }}</p>
                <p class="text-xs text-[var(--color-muted)]">of {{ $clients->count() }} on this page</p>
            </div>
        </div>
    </div>

    {{-- Client list --}}
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Client</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Country</th>
                        <th class="px-5 py-3 font-semibold">Last page visited</th>
                        <th class="px-5 py-3 text-right font-semibold">Total visits</th>
                        <th class="px-5 py-3 font-semibold">Last seen</th>
                        <th class="px-5 py-3 text-right font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($clients as $row)
                        @php
                            $client = $clientUsers[$row->client_id] ?? null;
                            $log = $lastRows[$row->last_id] ?? null;
                            $active = $activeSessions[$row->client_id] ?? null;
                            $detailUrl = route('admin.client-activity.details', ['client' => $row->client_id]);
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ $detailUrl }}" class="flex items-center gap-2 hover:opacity-80">
                                    @if ($client?->photo)
                                        <img src="{{ asset('storage/'.$client->photo) }}" alt="" class="h-8 w-8 rounded-full border border-gray-200 object-cover">
                                    @else
                                        <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[11px] font-bold text-[var(--color-primary)]">{{ strtoupper(substr($client->name ?? '?', 0, 1)) }}</span>
                                    @endif
                                    <span>
                                        <span class="block font-medium text-[var(--color-heading)]">{{ $client->name ?? 'Deleted client #'.$row->client_id }}</span>
                                        <span class="block text-xs text-[var(--color-muted)]">{{ $client->email ?? '—' }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-5 py-3">
                                @if ($active)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600">
                                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                        Signed in
                                    </span>
                                    <span class="mt-0.5 block text-[11px] text-gray-400">{{ $active->sessions }} session(s)</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-400">Signed out</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $log->country ?: '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="font-medium text-[var(--color-heading)]">{{ $log->title ?: '—' }}</span>
                                <span class="block font-mono text-xs text-[var(--color-primary)]">{{ $log->path ?? '' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right"><span class="inline-flex rounded-full bg-[var(--color-primary-soft)] px-2.5 py-0.5 text-xs font-bold text-[var(--color-primary)]">{{ number_format($row->visits) }}</span></td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">
                                {{ \Illuminate\Support\Carbon::parse($row->last_seen)->format('d M Y, h:i A') }}
                                <span class="block text-xs text-gray-400">first seen {{ \Illuminate\Support\Carbon::parse($row->first_seen)->format('d M Y') }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ $detailUrl }}" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-gray-50">
                                        History
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 18 6-6-6-6"/></svg>
                                    </a>
                                    {{-- Super admins only: this kicks a paying customer out of the website mid-visit. --}}
                                    @if ($canSignOut && $active)
                                        <form method="POST" action="{{ route('admin.client-activity.clients.logout', $row->client_id) }}"
                                              onsubmit="return confirm('Sign {{ addslashes($client->name ?? 'this client') }} out of the website? They will have to log in again.')">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0 4-4m-4 4 4 4M13 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5"/></svg>
                                                Sign out
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">No client has browsed while signed in yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $clients->links() }}</div>
@endsection
