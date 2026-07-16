@extends('admin.layouts.app')
@section('title', 'Visitor History')

@section('content')
    <a href="{{ route('admin.client-activity') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Client Activity
    </a>

    {{-- Visitor header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-4">
            @if ($client)
                @if ($client->photo)
                    <img src="{{ asset('storage/'.$client->photo) }}" alt="" class="h-14 w-14 rounded-full border border-gray-200 object-cover">
                @else
                    <span class="grid h-14 w-14 place-items-center rounded-full bg-[var(--color-primary-soft)] text-lg font-bold text-[var(--color-primary)]">{{ strtoupper(substr($client->name, 0, 1)) }}</span>
                @endif
                <div>
                    <h1 class="text-lg font-bold text-[var(--color-heading)]">{{ $client->name }} <span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-600">Client</span></h1>
                    <p class="text-sm text-[var(--color-muted)]">{{ $client->email }}@if ($country) · {{ $country }}@endif</p>
                </div>
            @else
                <span class="grid h-14 w-14 place-items-center rounded-full bg-gray-100 text-gray-400">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12a9.5 9.5 0 1 0 19 0 9.5 9.5 0 0 0-19 0Zm0 0h19M12 2.5c2.5 2.6 2.5 16.4 0 19M12 2.5c-2.5 2.6-2.5 16.4 0 19"/></svg>
                </span>
                <div>
                    <h1 class="text-lg font-bold italic text-[var(--color-muted)]">Unknown visitor</h1>
                    <p class="text-sm text-[var(--color-muted)]">IP: {{ $ip }}@if ($country) · {{ $country }}@endif</p>
                </div>
            @endif
        </div>
        <div class="flex gap-6 text-sm">
            <div><p class="text-xs font-semibold uppercase text-gray-400">Total visits</p><p class="text-lg font-bold text-[var(--color-heading)]">{{ number_format($total) }}</p></div>
            <div><p class="text-xs font-semibold uppercase text-gray-400">First seen</p><p class="font-medium text-[var(--color-heading)]">{{ \Illuminate\Support\Carbon::parse($firstSeen)->format('d M Y') }}</p></div>
            <div><p class="text-xs font-semibold uppercase text-gray-400">Last seen</p><p class="font-medium text-[var(--color-heading)]">{{ \Illuminate\Support\Carbon::parse($lastSeen)->diffForHumans() }}</p></div>
            @if ($client)
                <a href="{{ route('admin.clients.show', $client->id) }}" class="self-center rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-primary)] hover:bg-gray-50">Client profile</a>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Their top pages --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-1">
            <h2 class="mb-1 text-sm font-bold text-[var(--color-heading)]">Their Most Visited Pages</h2>
            <p class="mb-4 text-xs text-[var(--color-muted)]">What this visitor keeps coming back to.</p>
            @php $maxP = max(1, (int) ($topPages->max('visits') ?? 1)); @endphp
            <div class="space-y-3">
                @forelse ($topPages as $p)
                    <div>
                        <div class="mb-1 flex items-baseline justify-between gap-2 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-[var(--color-heading)]">{{ $p->title ?: $p->path }}</span>
                                <span class="block truncate font-mono text-[11px] text-[var(--color-muted)]">{{ $p->path }}</span>
                            </span>
                            <span class="shrink-0 font-bold text-[var(--color-heading)]">{{ number_format($p->visits) }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-[var(--color-primary)]" style="width: {{ round($p->visits / $maxP * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Nothing yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Full history --}}
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Visit History</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Page</th>
                            <th class="px-5 py-3 font-semibold">Came from</th>
                            <th class="px-5 py-3 font-semibold">Country</th>
                            <th class="px-5 py-3 font-semibold">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($timeline as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <span class="font-medium text-[var(--color-heading)]">{{ $log->title ?: '—' }}</span>
                                    <span class="block font-mono text-xs text-[var(--color-primary)]">{{ $log->path }}</span>
                                </td>
                                <td class="max-w-[12rem] truncate px-5 py-3 text-xs text-[var(--color-muted)]" title="{{ $log->referrer }}">{{ $log->referrer ?: '—' }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $log->country ?: '—' }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $log->created_at?->format('d M Y, h:i A') }} <span class="block text-xs text-gray-400">{{ $log->created_at?->diffForHumans() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-12 text-center text-gray-400">No visits.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $timeline->links() }}</div>
        </div>
    </div>
@endsection
