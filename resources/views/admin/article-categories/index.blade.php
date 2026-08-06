@extends('admin.layouts.app')
@section('title', 'Blog Categories')

@section('content')
@php
    $mi = 'flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50';
    $miDanger = 'flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50';
@endphp

<div x-data="{ form: false, editing: null }">

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Blog Categories</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                {{ $categories->count() }} {{ Str::plural('category', $categories->count()) }} &middot;
                {{ number_format($categories->sum('articles_count')) }} {{ Str::plural('article', $categories->sum('articles_count')) }} filed
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.articles.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg>
                Articles
            </a>
            <button type="button" @click="editing = null; form = true"
                    class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                New Category
            </button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">#</th>
                        <th class="px-5 py-3 font-semibold">Category</th>
                        <th class="px-5 py-3 font-semibold">Created</th>
                        <th class="px-5 py-3 font-semibold">Authors</th>
                        <th class="px-5 py-3 text-right font-semibold">Articles</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($categories as $c)
                        @php
                            $names = $authors[$c->id] ?? [];
                            $rowJson = \Illuminate\Support\Js::from(['id' => $c->id, 'name' => $c->name]);
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-5 py-3 text-gray-400">{{ $c->id }}</td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-[var(--color-heading)]">{{ $c->name }}</p>
                                <p class="mt-0.5 text-xs text-gray-400">/{{ $c->slug }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-[var(--color-muted)]">{{ $c->created_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @if (! count($names))
                                    <span class="text-xs text-gray-300">—</span>
                                @else
                                    {{-- Taken from the articles themselves, so it is always who
                                         actually writes here rather than who was once assigned. --}}
                                    <div class="flex flex-wrap items-center gap-1">
                                        @foreach (array_slice($names, 0, 2) as $n)
                                            <span class="rounded-lg bg-gray-100 px-2 py-0.5 text-xs font-medium text-[var(--color-muted)]">{{ $n }}</span>
                                        @endforeach
                                        @if (count($names) > 2)
                                            <span class="rounded-lg bg-gray-100 px-2 py-0.5 text-xs font-semibold text-[var(--color-muted)]" title="{{ implode(', ', $names) }}">+{{ count($names) - 2 }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                @if ($c->articles_count)
                                    <a href="{{ route('admin.articles.index', ['category' => $c->id]) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ number_format($c->articles_count) }}</a>
                                @else
                                    <span class="text-gray-300">0</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <form method="POST" action="{{ route('admin.article-categories.status', $c) }}">
                                    @csrf @method('PATCH')
                                    <select name="status" onchange="this.form.submit()"
                                            class="rounded-lg border px-2.5 py-1.5 text-xs font-semibold focus:outline-none {{ $c->is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-gray-50 text-gray-500' }}">
                                        <option value="active" @selected($c->is_active)>Active</option>
                                        <option value="inactive" @selected(! $c->is_active)>Retired</option>
                                    </select>
                                </form>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <div x-data="rowMenu()" class="relative inline-block">
                                    <button type="button" @click="toggle($event)" title="Actions"
                                            class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>

                                    <template x-teleport="body">
                                        <div x-show="open" x-cloak>
                                            <div class="fixed inset-0 z-50" @click="open = false"></div>
                                            <div x-ref="menu" :style="`position:fixed; top:${y}px; left:${x}px`"
                                                 class="z-[60] w-52 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 text-left shadow-xl">
                                                <a href="{{ route('admin.articles.index', ['category' => $c->id]) }}" class="{{ $mi }}">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/></svg>
                                                    See its articles
                                                </a>
                                                <button type="button" @click="open = false; editing = {{ $rowJson }}; form = true" class="{{ $mi }}">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                                                    Rename
                                                </button>
                                                <div class="my-1 border-t border-gray-100"></div>
                                                <form method="POST" action="{{ route('admin.article-categories.destroy', $c) }}"
                                                      onsubmit="return confirm('Delete this category? Its {{ $c->articles_count }} article(s) stay, but lose their category.')">
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
                        <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-gray-400">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-3 text-xs text-[var(--color-muted)]">
        Retired categories are not offered when writing a new article. Articles already filed under one keep it.
    </p>

    {{-- Add / rename drawer --}}
    <div x-show="form" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="form = false">
        <div class="absolute inset-0 bg-black/40" @click="form = false"></div>
        <div style="width: 24rem" class="absolute inset-y-0 right-0 max-w-full overflow-y-auto bg-white shadow-xl"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
            <form method="POST" x-bind:action="editing ? '{{ route('admin.article-categories.store') }}/' + editing.id : '{{ route('admin.article-categories.store') }}'" class="p-5">
                @csrf
                <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]" x-text="editing ? 'Rename category' : 'New category'"></h2>
                    <button type="button" @click="form = false" class="text-gray-400 hover:text-[var(--color-heading)]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <label class="mb-1.5 block text-sm font-medium">Name</label>
                <input name="name" required placeholder="AI & Automation" x-bind:value="editing ? editing.name : ''"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                <p class="mt-1.5 text-xs text-[var(--color-muted)]">The web address is made from the name, so renaming changes the category's URL.</p>

                <div class="mt-5 flex gap-2">
                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                    <button type="button" @click="form = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
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
                    this.x = Math.max(8, r.right - 208);   // 208 = w-52
                    this.y = r.bottom + 4;
                    this.open = true;
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
