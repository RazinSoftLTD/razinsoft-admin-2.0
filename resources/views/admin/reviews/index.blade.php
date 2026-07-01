@extends('admin.layouts.app')
@section('title', 'Reviews')

@section('content')
    <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
        {{-- Product filter --}}
        <aside class="lg:sticky lg:top-6 lg:self-start">
            <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">By product</p>
            <nav class="space-y-1">
                <a href="{{ route('admin.reviews.index') }}"
                   class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium transition {{ ! $activeProduct ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:bg-gray-50' }}">
                    <span>All products</span>
                </a>
                @foreach ($products as $p)
                    <a href="{{ route('admin.reviews.index', ['product' => $p->slug]) }}"
                       class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm font-medium transition {{ $activeProduct?->id === $p->id ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:bg-gray-50' }}">
                        <span class="truncate">{{ $p->name }}</span>
                        @if ($p->hidden_count > 0)
                            <span class="grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-amber-500 px-1.5 text-[11px] font-bold text-white" title="{{ $p->hidden_count }} hidden">{{ $p->hidden_count }}</span>
                        @else
                            <span class="shrink-0 text-xs {{ $activeProduct?->id === $p->id ? 'text-white/70' : 'text-gray-400' }}">{{ $p->reviews_count }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </aside>

        <div>
            <p class="mb-5 text-sm text-[var(--color-muted)]">
                @if ($activeProduct)
                    {{ $reviews->total() }} review(s) on <strong>{{ $activeProduct->name }}</strong>.
                @else
                    {{ $reviews->total() }} review(s).
                @endif
                Editing or hiding a review updates the public product rating instantly.
            </p>

            <div class="space-y-4">
                @forelse ($reviews as $r)
                    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm" x-data="{ edit: false }">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2">
                                    <span class="text-amber-500">{{ str_repeat('★', $r->rating) }}<span class="text-gray-300">{{ str_repeat('★', 5 - $r->rating) }}</span></span>
                                    <span class="font-semibold text-[var(--color-heading)]">{{ $r->author_name ?? $r->user?->name ?? 'Customer' }}</span>
                                    @unless ($r->is_approved)<span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Hidden</span>@endunless
                                </p>
                                <p class="mt-1 text-xs text-gray-400">
                                    @if ($r->product)<a href="{{ route('admin.products.edit', $r->product) }}" class="text-[var(--color-primary)] hover:underline">{{ $r->product->name }}</a>@else (deleted product) @endif
                                    · {{ $r->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-3 text-xs font-semibold">
                                <button type="button" @click="edit = ! edit" class="text-[var(--color-primary)] hover:underline" x-text="edit ? 'Close' : 'Edit'"></button>
                                <button form="toggle-{{ $r->id }}" class="text-gray-500 hover:underline">{{ $r->is_approved ? 'Hide' : 'Show' }}</button>
                                <button form="del-{{ $r->id }}" class="text-red-600 hover:underline">Delete</button>
                            </div>
                        </div>

                        @if ($r->comment)<p class="mt-3 text-sm text-gray-600" x-show="!edit">{{ $r->comment }}</p>@endif

                        {{-- Edit form --}}
                        <form method="POST" action="{{ route('admin.reviews.update', $r) }}" class="mt-4 space-y-3" x-show="edit" x-cloak>
                            @csrf @method('PUT')
                            <div class="grid gap-3 sm:grid-cols-[1fr_8rem]">
                                <input name="author_name" value="{{ $r->author_name }}" placeholder="Author name"
                                       class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                <select name="rating" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                    @for ($i = 5; $i >= 1; $i--)
                                        <option value="{{ $i }}" @selected($r->rating == $i)>{{ $i }} star{{ $i > 1 ? 's' : '' }}</option>
                                    @endfor
                                </select>
                            </div>
                            <textarea name="comment" rows="3" placeholder="Comment"
                                      class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">{{ $r->comment }}</textarea>
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save changes</button>
                        </form>

                        <form id="toggle-{{ $r->id }}" method="POST" action="{{ route('admin.reviews.toggle', $r) }}">@csrf</form>
                        <form id="del-{{ $r->id }}" method="POST" action="{{ route('admin.reviews.destroy', $r) }}" onsubmit="return confirm('Delete this review?')">@csrf @method('DELETE')</form>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-200 bg-white py-16 text-center text-gray-400">No reviews yet.</div>
                @endforelse
            </div>

            <div class="mt-4">{{ $reviews->links() }}</div>
        </div>
    </div>
@endsection
