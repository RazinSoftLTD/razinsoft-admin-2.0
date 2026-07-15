@extends('admin.layouts.app')
@section('title', 'Client Activity')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Client Activity</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Which pages your logged-in clients visited on the website, and when.</p>
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Client</label>
            <select name="client" class="h-10 w-52 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                <option value="">All clients</option>
                @foreach ($clients as $c)<option value="{{ $c->id }}" @selected((string) request('client') === (string) $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">When</label>
            <select name="date_range" class="h-10 w-36 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                <option value="">Any time</option>
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $dv => $dl)
                    <option value="{{ $dv }}" @selected(request('date_range') === $dv)>{{ $dl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="h-10 rounded-lg border border-gray-200 px-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="h-10 rounded-lg border border-gray-200 px-2 text-sm">
        </div>
        <button class="h-10 rounded-lg bg-[var(--color-primary)] px-5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Filter</button>
        <a href="{{ route('admin.client-activity') }}" class="h-10 rounded-lg border border-gray-200 px-5 text-sm font-semibold leading-10 text-[var(--color-muted)] hover:bg-gray-50">Clear</a>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Client</th>
                        <th class="px-5 py-3 font-semibold">Page visited</th>
                        <th class="px-5 py-3 font-semibold">Came from</th>
                        <th class="px-5 py-3 font-semibold">IP</th>
                        <th class="px-5 py-3 font-semibold">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                @if ($log->client)
                                    <a href="{{ route('admin.clients.show', $log->client_id) }}" class="flex items-center gap-2 hover:opacity-80">
                                        @if ($log->client->photo)
                                            <img src="{{ asset('storage/'.$log->client->photo) }}" alt="" class="h-7 w-7 rounded-full border border-gray-200 object-cover">
                                        @else
                                            <span class="grid h-7 w-7 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[10px] font-bold text-[var(--color-primary)]">{{ strtoupper(substr($log->client->name, 0, 1)) }}</span>
                                        @endif
                                        <span>
                                            <span class="block font-medium text-[var(--color-heading)]">{{ $log->client->name }}</span>
                                            <span class="block text-xs text-[var(--color-muted)]">{{ $log->client->email }}</span>
                                        </span>
                                    </a>
                                @else
                                    <span class="text-[var(--color-muted)]">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="font-medium text-[var(--color-heading)]">{{ $log->title ?: '—' }}</span>
                                <span class="block font-mono text-xs text-[var(--color-primary)]">{{ $log->path }}</span>
                            </td>
                            <td class="max-w-[16rem] truncate px-5 py-3 text-xs text-[var(--color-muted)]" title="{{ $log->referrer }}">{{ $log->referrer ?: '—' }}</td>
                            <td class="px-5 py-3 text-xs text-[var(--color-muted)]">{{ $log->ip ?? '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $log->created_at?->format('d M Y, h:i A') }} <span class="text-xs text-gray-400">({{ $log->created_at?->diffForHumans() }})</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-gray-400">No client visits recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
