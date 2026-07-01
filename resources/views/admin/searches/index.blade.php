@extends('admin.layouts.app')
@section('title', 'Searches')

@php
    $ranges = ['today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days', 'all' => 'All time'];
    $noResultsOn = request()->boolean('no_results');
    $q = request()->query('q');
@endphp

@section('content')
    {{-- Stats --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Total searches</p>
            <p class="mt-1 text-3xl font-bold text-[var(--color-heading)]">{{ number_format($totalSearches) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Unique terms</p>
            <p class="mt-1 text-3xl font-bold text-[var(--color-heading)]">{{ number_format($uniqueTerms) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">No-result searches</p>
            <p class="mt-1 text-3xl font-bold text-amber-600">{{ number_format($noResults) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-3">
        <div class="flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
            @foreach ($ranges as $key => $label)
                <a href="{{ route('admin.searches.index', array_merge(request()->query(), ['range' => $key])) }}"
                   class="rounded-md px-3 py-1.5 font-medium {{ $range === $key ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
        </div>

        <input type="hidden" name="range" value="{{ $range }}">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m21 21-4.3-4.3" /></svg>
            <input name="q" value="{{ $q }}" placeholder="Filter terms…" class="h-9 rounded-lg border border-gray-200 pl-9 pr-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-[var(--color-muted)]">
            <input type="checkbox" name="no_results" value="1" @checked($noResultsOn) onchange="this.form.submit()" class="rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
            No results only
        </label>

        <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply</button>

        @if ($totalSearches > 0)
            <span class="ml-auto">
                <button form="clear-searches" class="text-xs font-semibold text-red-600 hover:underline">Clear all history</button>
            </span>
        @endif
    </form>
    <form id="clear-searches" method="POST" action="{{ route('admin.searches.destroy') }}" onsubmit="return confirm('Delete ALL search history? This cannot be undone.')">@csrf @method('DELETE')</form>

    {{-- Terms table --}}
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Search term</th>
                        <th class="px-5 py-3 text-right font-semibold">Times used</th>
                        <th class="px-5 py-3 text-center font-semibold">Result status</th>
                        <th class="px-5 py-3 font-semibold">Last searched</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($terms as $t)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ rtrim(config('services.frontend_url'), '/') }}/products?q={{ urlencode($t->term) }}" target="_blank" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $t->term }}</a>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <span class="inline-flex min-w-8 justify-center rounded-full bg-[var(--color-primary-soft)] px-2.5 py-1 text-xs font-bold text-[var(--color-primary)]">{{ $t->total }}</span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if ($t->best_results > 0)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Found results</span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">No results</span>
                                @endif
                                @if ($t->no_result_count > 0 && $t->best_results > 0)
                                    <span class="ml-1 text-[11px] text-gray-400">({{ $t->no_result_count }} empty)</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ \Illuminate\Support\Carbon::parse($t->last_at)->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-gray-400">No searches recorded for this filter yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $terms->links() }}</div>
@endsection
