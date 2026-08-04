@extends('admin.layouts.app')
@section('title', 'Gallery')

@section('content')
@php
    $q = fn (array $extra = []) => route('admin.gallery.index', array_merge(request()->except('page'), $extra));
    $activeCat = request('category');
    $activeKind = request('kind');
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

<p class="mb-3 text-sm text-[var(--color-muted)]">
    Showing {{ number_format($files->total()) }} {{ Str::plural('file', $files->total()) }} &middot; {{ \App\Support\MediaLibrary::size($shownSize) }}
</p>

@if ($files->total() === 0)
    <div class="rounded-xl border border-gray-100 bg-white px-4 py-16 text-center text-sm text-gray-400 shadow-sm">
        Nothing matches that. <a href="{{ route('admin.gallery.index') }}" class="font-semibold text-[var(--color-primary)] hover:underline">Clear the filters</a>.
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
        @foreach ($files as $f)
            @php
                $view = route('admin.gallery.file', ['disk' => $f['disk'], 'path' => $f['path']]);
                $dl = route('admin.gallery.file', ['disk' => $f['disk'], 'path' => $f['path'], 'download' => 1]);
                // Public files are served straight off the disk; private ones only through the
                // route, which checks the permission before it opens anything.
                $open = $f['url'] ?: $view;
            @endphp
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <a href="{{ $open }}" target="_blank" rel="noopener" class="block bg-gray-50">
                    @if ($f['kind'] === 'image')
                        <img src="{{ $open }}" alt="{{ $f['name'] }}" loading="lazy"
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
                        <a href="{{ $dl }}" class="shrink-0 text-[11px] font-semibold text-[var(--color-primary)] hover:underline">Download</a>
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-5">{{ $files->links() }}</div>
@endif
@endsection
