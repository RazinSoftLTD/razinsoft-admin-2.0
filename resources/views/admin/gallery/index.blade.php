@extends('admin.layouts.app')
@section('title', 'Gallery')

@section('content')
@php
    $q = fn (array $extra = []) => route('admin.gallery.index', array_merge(request()->except('page'), $extra));
    $activeCat = request('category');
    $activeKind = request('kind');
    // List by default: a file is usually looked for by name, date or size, and a column of
    // thumbnails buries all three. Grid is a click away for when the picture is the point.
    $mode = request('view') === 'grid' ? 'grid' : 'list';
    $icons = [
        'document' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6',
        'media' => 'M4 5h16v14H4zM10 9l5 3-5 3V9Z',
        'archive' => 'M3 7h18v3H3zM5 10v9h14v-9M10 14h4',
        'other' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Z',
    ];
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Gallery</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">
            Every file the panel has stored — {{ number_format($totalCount) }} files, {{ \App\Support\MediaLibrary::size($totalSize) }}.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.gallery.refresh') }}">
        @csrf
        <button class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6"/></svg>
            Rescan
        </button>
    </form>
</div>

{{-- Filters. The list is read from disk, so everything here is a plain GET — bookmarkable, and
     the back button behaves. --}}
<form method="GET" class="mb-4 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
        <div style="min-width: 16rem" class="relative flex-1">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
            <input name="search" type="text" value="{{ request('search') }}" autocomplete="off"
                   placeholder="Search by file name or path…"
                   class="h-11 w-full rounded-lg border border-gray-200 pl-11 pr-4 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
        </div>

        <select name="kind" onchange="this.form.submit()" class="h-11 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
            <option value="">All types</option>
            @foreach ($kinds as $k)
                <option value="{{ $k['key'] }}" @selected($activeKind === $k['key'])>{{ $k['label'] }} ({{ number_format($k['count']) }})</option>
            @endforeach
        </select>

        <select name="sort" onchange="this.form.submit()" class="h-11 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
            @foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'largest' => 'Largest first', 'name' => 'Name A–Z'] as $v => $label)
                <option value="{{ $v }}" @selected(request('sort', 'newest') === $v)>{{ $label }}</option>
            @endforeach
        </select>

        @if ($activeCat)<input type="hidden" name="category" value="{{ $activeCat }}">@endif
        @if ($mode === 'grid')<input type="hidden" name="view" value="grid">@endif

        <button class="h-11 rounded-lg bg-[var(--color-primary)] px-5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Search</button>
        @if ($activeCat || $activeKind || request('search'))
            <a href="{{ route('admin.gallery.index') }}" class="inline-flex h-11 items-center rounded-lg border border-gray-200 px-4 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Clear</a>
        @endif
    </div>

    {{-- Categories are the module that wrote the file, which is how people actually look for one
         ("the PDF from an invoice"), so they are chips rather than another dropdown. --}}
    <div class="mt-3 flex flex-wrap gap-2 border-t border-gray-100 pt-3">
        <a href="{{ $q(['category' => null]) }}"
           class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $activeCat ? 'bg-gray-100 text-[var(--color-muted)] hover:bg-gray-200' : 'bg-[var(--color-primary)] text-white' }}">
            All ({{ number_format($totalCount) }})
        </a>
        @foreach ($categories as $c)
            <a href="{{ $q(['category' => $c['key']]) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $activeCat === $c['key'] ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-[var(--color-muted)] hover:bg-gray-200' }}">
                {{ $c['label'] }} ({{ number_format($c['count']) }})
            </a>
        @endforeach
    </div>
</form>

<div class="mb-3 flex items-center justify-between gap-3">
    <p class="text-sm text-[var(--color-muted)]">
        Showing {{ number_format($files->total()) }} {{ Str::plural('file', $files->total()) }} &middot; {{ \App\Support\MediaLibrary::size($shownSize) }}
    </p>

    {{-- The mode rides in the query string, so it survives a filter, a page change and a bookmark. --}}
    <div class="flex overflow-hidden rounded-lg border border-gray-200 bg-white">
        <a href="{{ $q(['view' => null]) }}" title="List view"
           class="flex items-center gap-1.5 px-3 py-2 text-xs font-semibold {{ $mode === 'list' ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:bg-gray-50' }}">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg>
            List
        </a>
        <a href="{{ $q(['view' => 'grid']) }}" title="Grid view"
           class="flex items-center gap-1.5 px-3 py-2 text-xs font-semibold {{ $mode === 'grid' ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:bg-gray-50' }}">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
            Grid
        </a>
    </div>
</div>

@if ($files->total() === 0)
    <div class="rounded-xl border border-gray-100 bg-white px-4 py-16 text-center text-sm text-gray-400 shadow-sm">
        Nothing matches that. <a href="{{ route('admin.gallery.index') }}" class="font-semibold text-[var(--color-primary)] hover:underline">Clear the filters</a>.
    </div>
@else
    @php
        // Both modes need the same three links per row, so they are worked out once.
        $link = function (array $f) {
            $view = route('admin.gallery.file', ['disk' => $f['disk'], 'path' => $f['path']]);

            return [
                'view' => $view,
                'download' => route('admin.gallery.file', ['disk' => $f['disk'], 'path' => $f['path'], 'download' => 1]),
                // Public files are served straight off the disk; private ones only through the
                // route, which checks the permission before it opens anything.
                'open' => $f['url'] ?: $view,
            ];
        };
    @endphp

    @if ($mode === 'grid')
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            @foreach ($files as $f)
                @php $l = $link($f); @endphp
                <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <a href="{{ $l['open'] }}" target="_blank" rel="noopener" class="block bg-gray-50">
                        @if ($f['kind'] === 'image')
                            <img src="{{ $l['open'] }}" alt="{{ $f['name'] }}" loading="lazy"
                                 style="height: 8rem" class="w-full bg-gray-50 object-cover">
                        @else
                            <div style="height: 8rem" class="flex w-full flex-col items-center justify-center gap-2 text-gray-400">
                                <svg class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$f['kind']] ?? $icons['other'] }}"/></svg>
                                <span class="text-[11px] font-semibold uppercase tracking-wide">{{ $f['ext'] ?: 'file' }}</span>
                            </div>
                        @endif
                    </a>
                    <div class="p-3">
                        <p class="truncate text-xs font-semibold text-[var(--color-heading)]" title="{{ $f['path'] }}">{{ $f['name'] }}</p>
                        <p class="mt-1 flex items-center justify-between text-[11px] text-[var(--color-muted)]">
                            <span>{{ \App\Support\MediaLibrary::size($f['size']) }}</span>
                            <span>{{ \Illuminate\Support\Carbon::createFromTimestamp($f['modified'])->format('d M Y') }}</span>
                        </p>
                        <p class="mt-1 flex items-center justify-between gap-2">
                            <span class="truncate text-[11px] text-gray-400">{{ \App\Support\MediaLibrary::categoryLabel($f['disk'], $f['folder']) }}</span>
                            <a href="{{ $l['download'] }}" class="shrink-0 text-[11px] font-semibold text-[var(--color-primary)] hover:underline">Download</a>
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 text-left">File</th>
                            <th class="px-4 py-3 text-left">Category</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-right">Size</th>
                            <th class="px-4 py-3 text-left">Modified</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($files as $f)
                            @php $l = $link($f); @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2.5">
                                    <a href="{{ $l['open'] }}" target="_blank" rel="noopener" class="flex items-center gap-3">
                                        @if ($f['kind'] === 'image')
                                            <img src="{{ $l['open'] }}" alt="" loading="lazy" style="height: 2.5rem; width: 2.5rem"
                                                 class="shrink-0 rounded-lg border border-gray-100 bg-gray-50 object-cover">
                                        @else
                                            <span style="height: 2.5rem; width: 2.5rem" class="grid shrink-0 place-items-center rounded-lg border border-gray-100 bg-gray-50 text-gray-400">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$f['kind']] ?? $icons['other'] }}"/></svg>
                                            </span>
                                        @endif
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold text-[var(--color-heading)]">{{ $f['name'] }}</span>
                                            <span class="block truncate text-xs text-gray-400" title="{{ $f['path'] }}">{{ $f['path'] }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-[var(--color-muted)]">{{ \App\Support\MediaLibrary::categoryLabel($f['disk'], $f['folder']) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <span class="rounded-lg bg-gray-100 px-2 py-1 text-xs font-semibold uppercase text-[var(--color-muted)]">{{ $f['ext'] ?: $f['kind'] }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-[var(--color-muted)]">{{ \App\Support\MediaLibrary::size($f['size']) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-[var(--color-muted)]">{{ \Illuminate\Support\Carbon::createFromTimestamp($f['modified'])->format('d M Y, H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                    <a href="{{ $l['open'] }}" target="_blank" rel="noopener" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">Open</a>
                                    <a href="{{ $l['download'] }}" class="ml-4 text-xs font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)] hover:underline">Download</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="mt-5">{{ $files->links() }}</div>
@endif
@endsection
