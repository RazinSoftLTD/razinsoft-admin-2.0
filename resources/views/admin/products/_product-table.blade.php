@php
    $canClone = auth()->user()->allows('products', 'clone');
    $canReorder = auth()->user()->allows('products', 'edit');
    $menuItem = 'flex w-full items-center gap-2.5 px-3 py-2 text-left text-xs font-medium hover:bg-gray-50';
@endphp
<div class="rounded-xl border border-gray-100 bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
            <tr>
                <th class="w-8"></th>
                <th class="px-4 py-3 font-semibold" title="Position — lower shows first on the site">#</th>
                <th class="px-5 py-3 font-semibold">Name</th>
                <th class="px-5 py-3 font-semibold">Category</th>
                <th class="px-5 py-3 font-semibold">From</th>
                <th class="px-5 py-3 font-semibold">Version</th>
                <th class="px-5 py-3 font-semibold">Status</th>
                <th class="px-5 py-3 text-right font-semibold">Actions</th>
            </tr>
        </thead>
        <tbody id="{{ $tableId }}" class="product-tbody divide-y divide-gray-100" @if ($canReorder) data-reorder-url="{{ $reorderUrl }}" @endif>
            @forelse ($rows as $p)
                <tr class="product-row hover:bg-gray-50" data-product-id="{{ $p->id }}">
                    <td class="py-3 pl-4">
                        @if ($canReorder)
                            <span data-drag-handle class="inline-flex cursor-move text-gray-300 hover:text-gray-500" title="Drag to reorder">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg>
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="row-serial inline-flex h-7 min-w-7 items-center justify-center rounded-lg bg-gray-100 px-2 text-sm font-semibold text-[var(--color-heading)]">{{ $loop->iteration }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <a href="{{ route('admin.products.show', $p) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $p->name }}</a>
                        <p class="text-xs text-gray-400">{{ $p->tagline }}</p>
                    </td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->category ?? '—' }}</td>
                    <td class="px-5 py-3 font-semibold">{{ $p->firstPlan ? '$'.number_format($p->firstPlan->price, 0) : '—' }}</td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">v{{ $p->version }}</td>
                    <td class="px-5 py-3"><x-admin.status :status="$p->status" /></td>
                    <td class="px-5 py-3">
                        <div class="flex justify-end">
                            <div class="relative" x-data="{ open: false }">
                                <button type="button" @click="open = !open" @click.outside="open = false"
                                        class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Actions">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                                </button>
                                <div x-show="open" x-cloak x-transition.opacity style="min-width:11rem"
                                     class="absolute right-0 top-10 z-20 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg">
                                    <a href="{{ route('admin.products.show', $p) }}" class="{{ $menuItem }} text-[var(--color-heading)]">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.6-7.5 10-7.5S22 12 22 12s-3.6 7.5-10 7.5S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>View &amp; manage
                                    </a>
                                    @if (auth()->user()->allows('products', 'edit'))
                                        <a href="{{ route('admin.products.edit', $p) }}" class="{{ $menuItem }} text-[var(--color-heading)]">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Edit
                                        </a>
                                    @endif
                                    @if ($canClone)
                                        <form method="POST" action="{{ route('admin.products.clone', $p) }}" onsubmit="return confirm('Clone this product with all its content (as a draft)?')">
                                            @csrf
                                            <button class="{{ $menuItem }} text-[var(--color-heading)]">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="9" y="9" width="11" height="11" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>Clone
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.products.destroy', $p) }}" onsubmit="return confirm('Delete this product?')">
                                        @csrf @method('DELETE')
                                        <button class="{{ $menuItem }} text-red-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-5 py-10 text-center text-gray-400">{{ $emptyText }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
