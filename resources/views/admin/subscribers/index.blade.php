@extends('admin.layouts.app')
@section('title', 'Subscribers')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Subscribers</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">{{ $total }} total · {{ $active }} active — people who followed from the blog.</p>
        </div>
        <a href="{{ route('admin.subscribers.index', ['export' => 'csv'] + request()->only('search')) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>
            Export CSV
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Add subscriber --}}
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Add subscriber</h2>
                <form method="POST" action="{{ route('admin.subscribers.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Email <span class="text-red-500">*</span></label>
                        <input name="email" type="email" value="{{ old('email') }}" required placeholder="person@example.com" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Name</label>
                        <input name="name" value="{{ old('name') }}" placeholder="Optional" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Add
                    </button>
                </form>
            </div>
        </div>

        {{-- List --}}
        <div class="lg:col-span-2">
            <form method="GET" class="mb-4">
                <input name="search" value="{{ request('search') }}" placeholder="Search email or name…" class="h-10 w-full max-w-sm rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
            </form>
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Email</th>
                                <th class="px-5 py-3 font-semibold">Source</th>
                                <th class="px-5 py-3 font-semibold">Subscribed</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($subscribers as $s)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3">
                                        <p class="font-semibold text-[var(--color-heading)]">{{ $s->email }}</p>
                                        @if ($s->name)<p class="text-xs text-[var(--color-muted)]">{{ $s->name }}</p>@endif
                                    </td>
                                    <td class="px-5 py-3 text-[var(--color-muted)]">
                                        <span class="capitalize">{{ $s->source ?? '—' }}</span>
                                        @if ($s->article)<p class="text-xs text-gray-400">{{ \Illuminate\Support\Str::limit($s->article, 28) }}</p>@endif
                                    </td>
                                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $s->created_at->format('d M Y') }}</td>
                                    <td class="px-5 py-3">
                                        <form method="POST" action="{{ route('admin.subscribers.update', $s) }}">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="is_active" value="{{ $s->is_active ? 0 : 1 }}">
                                            <button type="submit" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $s->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}" title="Click to toggle">
                                                {{ $s->is_active ? 'Active' : 'Unsubscribed' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <form method="POST" action="{{ route('admin.subscribers.destroy', $s) }}" onsubmit="return confirm('Remove {{ $s->email }}?')">
                                            @csrf @method('DELETE')
                                            <button class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center text-gray-400">No subscribers yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">{{ $subscribers->links() }}</div>
        </div>
    </div>
@endsection
