@extends('admin.layouts.app')
@section('title', 'Questions')

@section('content')
    <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
        {{-- Product filter --}}
        <aside class="lg:sticky lg:top-6 lg:self-start">
            <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">By product</p>
            <nav class="space-y-1">
                <a href="{{ route('admin.questions.index') }}"
                   class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium transition {{ ! $activeProduct ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:bg-gray-50' }}">
                    <span>All products</span>
                </a>
                @foreach ($products as $p)
                    <a href="{{ route('admin.questions.index', ['product' => $p->slug]) }}"
                       class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm font-medium transition {{ $activeProduct?->id === $p->id ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:bg-gray-50' }}">
                        <span class="truncate">{{ $p->name }}</span>
                        @if ($p->pending_count > 0)
                            <span class="grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-red-500 px-1.5 text-[11px] font-bold text-white">{{ $p->pending_count }}</span>
                        @else
                            <span class="shrink-0 text-xs {{ $activeProduct?->id === $p->id ? 'text-white/70' : 'text-gray-400' }}">{{ $p->questions_count }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </aside>

        <div>
            <p class="mb-5 text-sm text-[var(--color-muted)]">
                @if ($activeProduct)
                    {{ $questions->total() }} thread(s) on <strong>{{ $activeProduct->name }}</strong>.
                @else
                    {{ $questions->total() }} question thread(s).
                @endif
                Your replies appear as <strong>Author</strong> on the product page.
            </p>

            <div class="space-y-4">
        @forelse ($questions as $q)
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-[var(--color-heading)]">{{ $q->question }}</p>
                        <p class="mt-1 text-xs text-gray-400">
                            by {{ $q->user?->name ?? $q->name ?? 'Customer' }} ·
                            @if ($q->product)<a href="{{ route('admin.products.edit', $q->product) }}" class="text-[var(--color-primary)] hover:underline">{{ $q->product->name }}</a>@else (deleted product) @endif
                            · {{ $q->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <span class="shrink-0">
                        @if ($q->admin_answers_count > 0)
                            <x-admin.status status="answered" />
                        @else
                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Needs reply</span>
                        @endif
                    </span>
                </div>

                {{-- Thread --}}
                @if ($q->answers->isNotEmpty())
                    <ul class="mt-4 space-y-3 border-l-2 border-gray-100 pl-4">
                        @foreach ($q->answers as $a)
                            <li>
                                <p class="flex flex-wrap items-center gap-2 text-sm">
                                    <span class="font-semibold text-[var(--color-heading)]">{{ $a->name ?? $a->user?->name ?? 'User' }}</span>
                                    @if ($a->is_admin)<span class="rounded bg-[var(--color-primary-soft)] px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--color-primary)]">Author</span>@endif
                                    <span class="text-xs text-gray-400">{{ $a->created_at->diffForHumans() }}</span>
                                </p>
                                <p class="mt-0.5 text-sm text-gray-600">{{ $a->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Admin reply --}}
                <form method="POST" action="{{ route('admin.questions.reply', $q) }}" class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                    @csrf
                    <textarea name="body" rows="2" required placeholder="Reply as Author…"
                              class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]"></textarea>
                    <div class="flex items-center justify-between">
                        <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Post reply</button>
                        <span class="text-right">
                            <button form="del-{{ $q->id }}" class="text-xs font-semibold text-red-600 hover:underline">Delete question</button>
                        </span>
                    </div>
                </form>
                <form id="del-{{ $q->id }}" method="POST" action="{{ route('admin.questions.destroy', $q) }}" onsubmit="return confirm('Delete this question and its replies?')">@csrf @method('DELETE')</form>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-200 bg-white py-16 text-center text-gray-400">No questions yet.</div>
        @endforelse
            </div>

            <div class="mt-4">{{ $questions->links() }}</div>
        </div>
    </div>
@endsection
