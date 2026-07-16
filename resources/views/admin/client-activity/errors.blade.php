@extends('admin.layouts.app')
@section('title', 'Website Errors')

@section('content')
    <a href="{{ route('admin.client-activity') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Client Activity
    </a>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Website Errors</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Error pages visitors hit — 404s and other failures, which URLs cause them, and who ran into them.</p>
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
        </form>
    </div>

    {{-- Stats: total + by status code --}}
    <div class="mb-6 flex flex-wrap gap-4">
        <div class="flex items-center gap-4 rounded-xl border border-red-200 bg-red-50/60 p-5 shadow-sm">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-red-100 text-red-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-red-400">Total Errors</p>
                <p class="text-lg font-bold text-red-600">{{ number_format($totalErrors) }}</p>
                <p class="text-xs text-red-400">{{ number_format($affectedVisitors) }} visitor(s) affected</p>
            </div>
        </div>
        @foreach ($byCode as $c)
            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <span class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-bold text-[var(--color-heading)]">{{ $c->error_code }}</span>
                <div>
                    <p class="text-lg font-bold text-[var(--color-heading)]">{{ number_format($c->hits) }}</p>
                    <p class="text-xs text-[var(--color-muted)]">{{ $c->error_code == 404 ? 'Page not found' : ($c->error_code >= 500 ? 'Server error' : 'Client error') }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Error URLs --}}
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Error Pages</h2>
                <p class="text-xs text-[var(--color-muted)]">URLs that error, ranked by how often visitors hit them.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Code</th>
                            <th class="px-5 py-3 font-semibold">URL</th>
                            <th class="px-5 py-3 text-right font-semibold">Hits</th>
                            <th class="px-5 py-3 text-right font-semibold">Visitors</th>
                            <th class="px-5 py-3 font-semibold">Last seen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($pages as $p)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3"><span class="rounded-md bg-red-50 px-2 py-0.5 text-xs font-bold text-red-600">{{ $p->error_code }}</span></td>
                                <td class="max-w-md px-5 py-3">
                                    <span class="block truncate font-mono text-xs text-[var(--color-heading)]">{{ $p->path }}</span>
                                    @if ($p->sample_referrer)<span class="block truncate text-[11px] text-gray-400" title="Example referrer">from: {{ $p->sample_referrer }}</span>@endif
                                </td>
                                <td class="px-5 py-3 text-right font-bold text-[var(--color-heading)]">{{ number_format($p->hits) }}</td>
                                <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ number_format($p->visitors) }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ \Illuminate\Support\Carbon::parse($p->last_seen)->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-12 text-center text-gray-400">No errors recorded — all good! 🎉</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $pages->links() }}</div>
        </div>

        {{-- Recent hits --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-1">
            <h2 class="mb-1 text-sm font-bold text-[var(--color-heading)]">Recent Error Hits</h2>
            <p class="mb-4 text-xs text-[var(--color-muted)]">Who ran into an error most recently.</p>
            <div class="space-y-3">
                @forelse ($recent as $log)
                    <div class="flex items-start gap-2.5 border-b border-gray-50 pb-3 last:border-0">
                        <span class="mt-0.5 rounded-md bg-red-50 px-1.5 py-0.5 text-[11px] font-bold text-red-600">{{ $log->error_code }}</span>
                        <div class="min-w-0 text-sm">
                            <p class="truncate font-mono text-xs text-[var(--color-heading)]">{{ $log->path }}</p>
                            <p class="text-xs text-[var(--color-muted)]">
                                @if ($log->client)
                                    <a href="{{ route('admin.client-activity.details', ['client' => $log->client_id]) }}" class="font-medium text-[var(--color-primary)] hover:underline">{{ $log->client->name }}</a>
                                @else
                                    Unknown ({{ $log->ip }})
                                @endif
                                @if ($log->country) · {{ $log->country }} @endif
                                · {{ $log->created_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">None yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
