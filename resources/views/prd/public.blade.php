<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $project->name }} — Project Requirements</title>
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50">
<div class="mx-auto max-w-3xl px-4 py-10">

    {{-- Header --}}
    <div class="mb-6">
        <p class="text-sm font-semibold text-[var(--color-primary)]">Project Requirements</p>
        <h1 class="mt-1 text-2xl font-bold text-[var(--color-heading)]">{{ $project->name }}</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">
            Please provide the information below. You can upload files or type the details — our team reviews each item after you submit.
        </p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="space-y-3">
        @foreach ($sections as $key)
            @php
                [$label, $hint, $required, $icon, $tint] = \App\Models\Project::PRD_SECTIONS[$key];
                $rows = $items[$key] ?? collect();
            @endphp
            <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm" x-data="{ open: {{ $rows->isEmpty() ? 'false' : 'true' }} }">
                <button type="button" @click="open = !open" class="flex w-full items-center gap-3 p-4 text-left transition hover:bg-gray-50">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg {{ $tint }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-bold text-[var(--color-heading)]">{{ $label }}</span>
                            <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $required ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500' }}">{{ $required ? 'Required' : 'Optional' }}</span>
                        </span>
                        <span class="mt-0.5 block text-xs text-[var(--color-muted)]">{{ $hint }}</span>
                    </span>
                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>

                <div x-show="open" x-cloak class="border-t border-gray-100 p-4">
                    @if ($rows->isNotEmpty())
                        <ul class="mb-4 divide-y divide-gray-50">
                            @foreach ($rows as $row)
                                @php
                                    $badge = ['approved' => 'bg-emerald-50 text-emerald-600', 'rejected' => 'bg-red-50 text-red-600'][$row->status] ?? 'bg-amber-50 text-amber-600';
                                @endphp
                                <li class="py-2.5 first:pt-0">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-gray-50 text-gray-400">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $row->isFile() ? 'M7 3h7l5 5v13H7zM14 3v5h5' : 'M4 6h16M4 12h16M4 18h10' }}"/></svg>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            @if ($row->isFile())
                                                <p class="truncate text-sm font-semibold text-[var(--color-heading)]">{{ $row->name }}</p>
                                            @else
                                                <p class="whitespace-pre-line text-sm text-[var(--color-heading)]">{{ $row->note }}</p>
                                            @endif
                                            <p class="mt-0.5 text-xs text-[var(--color-muted)]">{{ $row->submitterName() }} · {{ $row->created_at->diffForHumans() }}</p>
                                        </div>
                                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badge }}">{{ \App\Models\ProjectPrdItem::STATUSES[$row->status] ?? $row->status }}</span>
                                    </div>
                                    @if ($row->status === 'rejected' && $row->review_note)
                                        <p class="ml-11 mt-1 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">{{ $row->review_note }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ route('prd.public.store', $token) }}" enctype="multipart/form-data" class="rounded-lg bg-gray-50 p-3">
                        @csrf
                        <input type="hidden" name="section" value="{{ $key }}">
                        <input type="text" name="submitted_by_name" maxlength="80" placeholder="Your name (optional)"
                               class="mb-2 w-full rounded-lg border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                        <textarea name="note" rows="3" placeholder="Type the details for this section…"
                                  class="w-full rounded-lg border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"></textarea>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <input type="file" name="files[]" multiple
                                   class="max-w-full text-xs text-[var(--color-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[var(--color-heading)]">
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]">Submit</button>
                        </div>
                    </form>
                </div>
            </section>
        @endforeach
    </div>

    <p class="mt-8 text-center text-xs text-gray-400">This is a private link — please don't share it publicly.</p>
</div>
</body>
</html>
