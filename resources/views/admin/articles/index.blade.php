@extends('admin.layouts.app')
@section('title', 'Articles')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <p class="text-sm text-[var(--color-muted)]">{{ $articles->total() }} article(s)</p>
        <a href="{{ route('admin.articles.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> New Article
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Title</th>
                        <th class="px-5 py-3 font-semibold">Category</th>
                        <th class="px-5 py-3 font-semibold">Author</th>
                        <th class="px-5 py-3 font-semibold">Published</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($articles as $a)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.articles.edit', $a) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $a->title }}</a>
                                @if ($a->is_featured)<span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-600">Featured</span>@endif
                                <p class="mt-0.5 text-xs text-gray-400">/{{ $a->slug }}</p>
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $a->category?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $a->author?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $a->published_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="px-5 py-3"><x-admin.status :status="$a->status" /></td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <form method="POST" action="{{ route('admin.articles.publish', $a) }}">
                                        @csrf
                                        <button class="rounded-lg px-2 py-1.5 text-xs font-semibold {{ $a->status === 'published' ? 'text-amber-600 hover:bg-amber-50' : 'text-emerald-600 hover:bg-emerald-50' }}" title="{{ $a->status === 'published' ? 'Unpublish' : 'Publish' }}">
                                            {{ $a->status === 'published' ? 'Unpublish' : 'Publish' }}
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.articles.edit', $a) }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('admin.articles.destroy', $a) }}" onsubmit="return confirm('Delete this article?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">No articles yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $articles->links() }}</div>
@endsection
