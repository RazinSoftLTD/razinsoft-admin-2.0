@extends('admin.layouts.app')
@section('title', 'Articles')

@section('content')
@php
    $mi = 'flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50';
    $miDanger = 'flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50';
    $site = rtrim(config('app.frontend_url', 'https://www.razinsoft.com'), '/');
@endphp

<div x-data="{ panel: false }">

    <div class="mb-5 flex items-center justify-between gap-3">
        <p class="text-sm text-[var(--color-muted)]">{{ $articles->total() }} article(s)</p>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.articles.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> New Article
            </a>

            {{-- The filters live in a drawer rather than above the table: they are set once and then
                 only get in the way of the list, which is what the page is for. --}}
            <button type="button" @click="panel = true" title="Filters"
                    style="height: 42px"
                    class="relative grid w-11 place-items-center rounded-lg border border-gray-200 bg-white text-[var(--color-heading)] hover:bg-gray-50">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M6 12h12M9 18h6"/></svg>
                @if ($activeFilters)
                    <span style="top: -0.25rem" class="absolute -right-1 grid h-5 min-w-5 place-items-center rounded-full bg-[var(--color-primary)] px-1 text-[10px] font-bold text-white">{{ $activeFilters }}</span>
                @endif
            </button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Author</th>
                        <th class="px-5 py-3 font-semibold">Title</th>
                        <th class="px-5 py-3 font-semibold">Category</th>
                        <th class="px-5 py-3 font-semibold">Dates</th>
                        <th class="px-5 py-3 text-right font-semibold">Views</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($articles as $a)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $a->author?->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.articles.edit', $a) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $a->title }}</a>
                                @if ($a->is_featured)<span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-600">Featured</span>@endif
                                <p class="mt-0.5 text-xs text-gray-400">/{{ $a->slug }}</p>
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $a->category?->name ?? '—' }}</td>
                            {{-- Both dates in one cell: written and published are read together, and
                                 two columns of near-identical dates was harder to compare, not easier. --}}
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-[var(--color-muted)]">
                                <span class="block"><span class="font-bold text-gray-400" title="Created">C</span> {{ $a->created_at?->format('M d, Y') ?? '—' }}</span>
                                <span class="mt-0.5 block"><span class="font-bold text-gray-400" title="Published">P</span> {{ $a->published_at?->format('M d, Y') ?? '—' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-[var(--color-heading)]">
                                {{ number_format($views['/blog/'.$a->slug] ?? 0) }}
                            </td>
                            <td class="px-5 py-3">
                                {{-- A select rather than a button: it reads as the current state and
                                     changes it in one move, which a "Publish"/"Unpublish" button does
                                     only if you already know which way round it is. --}}
                                <form method="POST" action="{{ route('admin.articles.publish', $a) }}">
                                    @csrf
                                    <select name="status" onchange="this.form.submit()"
                                            class="rounded-lg border px-2.5 py-1.5 text-xs font-semibold focus:outline-none {{ $a->status === 'published' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-gray-50 text-gray-500' }}">
                                        <option value="published" @selected($a->status === 'published')>Published</option>
                                        <option value="draft" @selected($a->status !== 'published')>Unpublished</option>
                                    </select>
                                </form>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <div x-data="rowMenu()" class="relative inline-block">
                                    <button type="button" @click="toggle($event)" title="Actions"
                                            class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>

                                    {{-- Teleported and fixed-positioned: the table scrolls sideways, and
                                         an overflow container clips a menu that is merely absolute. --}}
                                    <template x-teleport="body">
                                        <div x-show="open" x-cloak>
                                            <div class="fixed inset-0 z-50" @click="open = false"></div>
                                            <div x-ref="menu" :style="`position:fixed; top:${y}px; left:${x}px`"
                                                 class="z-[60] w-48 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 text-left shadow-xl">
                                                <a href="{{ $site }}/blog/{{ $a->slug }}" target="_blank" rel="noopener" class="{{ $mi }}">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                                    View
                                                </a>
                                                <a href="{{ route('admin.articles.edit', $a) }}" class="{{ $mi }}">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                                                    Edit
                                                </a>
                                                <div class="my-1 border-t border-gray-100"></div>
                                                <form method="POST" action="{{ route('admin.articles.destroy', $a) }}" onsubmit="return confirm('Delete this article?')">
                                                    @csrf @method('DELETE')
                                                    <button class="{{ $miDanger }}">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">
                            @if ($activeFilters)
                                Nothing matches those filters. <a href="{{ route('admin.articles.index') }}" class="font-semibold text-[var(--color-primary)] hover:underline">Clear them</a>.
                            @else
                                No articles yet.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $articles->links() }}</div>

    {{-- Filter drawer --}}
    <div x-show="panel" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="panel = false">
        <div class="absolute inset-0 bg-black/40" @click="panel = false"></div>
        <div style="width: 24rem" class="absolute inset-y-0 right-0 max-w-full overflow-y-auto bg-white shadow-xl"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
            <form method="GET" class="p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Filters</h2>
                    <button type="button" @click="panel = false" class="text-gray-400 hover:text-[var(--color-heading)]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Search</label>
                        <input name="search" type="text" value="{{ request('search') }}" placeholder="Title or slug…"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Status</label>
                        <select name="status" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">Any</option>
                            <option value="published" @selected(request('status') === 'published')>Published</option>
                            <option value="draft" @selected(request('status') === 'draft')>Unpublished</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Category</label>
                        <select name="category" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">Any</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}" @selected((string) request('category') === (string) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Author</label>
                        <select name="author" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">Any</option>
                            @foreach ($authors as $au)
                                <option value="{{ $au->id }}" @selected((string) request('author') === (string) $au->id)>{{ $au->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Featured</label>
                        <select name="featured" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">Any</option>
                            <option value="yes" @selected(request('featured') === 'yes')>Featured only</option>
                            <option value="no" @selected(request('featured') === 'no')>Not featured</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Created between</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input name="from" type="date" value="{{ request('from') }}" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <input name="to" type="date" value="{{ request('to') }}" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex gap-2">
                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply</button>
                    <a href="{{ route('admin.articles.index') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <style>[x-cloak]{display:none!important}</style>
    <script>
        function rowMenu() {
            return {
                open: false, x: 0, y: 0,
                toggle(e) {
                    if (this.open) { this.open = false; return; }
                    const r = e.currentTarget.getBoundingClientRect();
                    this.x = Math.max(8, r.right - 192);   // 192 = w-48
                    this.y = r.bottom + 4;
                    this.open = true;
                    // Measure once rendered and flip upward when a row near the bottom would push
                    // the menu off the window.
                    this.$nextTick(() => {
                        const m = this.$refs.menu;
                        if (!m) return;
                        const h = m.offsetHeight, vh = window.innerHeight;
                        if (r.bottom + 4 + h > vh - 8) {
                            const above = r.top - 4 - h;
                            this.y = above >= 8 ? above : Math.max(8, vh - h - 8);
                        }
                    });
                },
            };
        }
    </script>
</div>
@endsection
