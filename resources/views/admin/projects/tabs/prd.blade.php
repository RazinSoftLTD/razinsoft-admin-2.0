@php
    $sections = $project->prdSectionKeys();
    $items = $project->prdItems()->with('uploader:id,name', 'approver:id,name')->get()->groupBy('section');
    $canEditProject = auth()->user()->allows('projects', 'edit');
@endphp

@if (! $project->needs_requirements || empty($sections))
    {{-- Nothing switched on in Settings yet. --}}
    <div class="rounded-2xl border border-gray-100 bg-white p-8 text-center shadow-sm">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-gray-50 text-gray-400">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1ZM8 6H6a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-2M9 12h6M9 16h4"/></svg>
        </div>
        <h3 class="mt-4 text-base font-semibold text-[var(--color-heading)]">No PRD sections yet</h3>
        <p class="mx-auto mt-1 max-w-md text-sm text-[var(--color-muted)]">
            @if ($canEditProject)
                Pick what this project needs under <a href="{{ route('admin.projects.show', $project) }}?tab=settings" class="font-semibold text-[var(--color-primary)] hover:underline">Settings → PRD / Requirements</a>.
            @else
                A project manager has not set up the requirement sections for this project.
            @endif
        </p>
    </div>
@else
    @php
        $done = collect($sections)->filter(fn ($k) => ($items[$k] ?? collect())->isNotEmpty())->count();
        $pct = count($sections) ? (int) round($done / count($sections) * 100) : 0;
        $shareUrl = $canEditProject ? $project->prdShareUrl() : null;
    @endphp

    <p class="mb-4 text-sm text-[var(--color-muted)]">Provide all required information and files for your project.</p>

    {{-- ===== Progress + client link ===== --}}
    <div class="grid items-start gap-4 lg:grid-cols-2">
        {{-- Overall progress --}}
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-bold text-[var(--color-heading)]">Overall Progress</h3>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-2">
                <p class="text-2xl font-bold text-[var(--color-primary)]">
                    {{ $pct }}% <span class="text-sm font-semibold text-[var(--color-muted)]">Completed</span>
                </p>
                <p class="text-sm text-[var(--color-muted)]">{{ $done }} of {{ count($sections) }} sections completed</p>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-2 rounded-full bg-[var(--color-primary)] transition-all" style="width: {{ $pct }}%"></div>
            </div>
        </div>

        {{-- Client access link --}}
        @if ($canEditProject)
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm" x-data="{ copied: false }">
                <h3 class="text-sm font-bold text-[var(--color-heading)]">Client Access Link</h3>
                <p class="mt-0.5 text-xs text-[var(--color-muted)]">Share this link with your client to fill requirements</p>

                @if (! $shareUrl)
                    <form method="POST" action="{{ route('admin.projects.prd.share', $project) }}" class="mt-3">
                        @csrf
                        <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]">Create link</button>
                    </form>
                @else
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <input type="text" readonly value="{{ $shareUrl }}" x-ref="link" @focus="$event.target.select()"
                               class="h-10 min-w-0 flex-1 truncate rounded-lg border-gray-200 bg-white text-sm text-[var(--color-muted)]">

                        <button type="button" title="Copy link"
                                @click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 1500)"
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-gray-200 text-[var(--color-primary)] transition hover:bg-gray-50">
                            <svg x-show="!copied" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9h10v12H9zM5 15V3h10"/></svg>
                            <svg x-show="copied" x-cloak class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                        </button>

                        <a href="{{ $shareUrl }}" target="_blank" rel="noopener"
                           class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-3 text-sm font-semibold text-[var(--color-heading)] transition hover:bg-gray-50">
                            Open
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5h5v5M19 5l-8 8M18 14v5H5V6h5"/></svg>
                        </a>

                        <form method="POST" action="{{ route('admin.projects.prd.share', $project) }}" onsubmit="return confirm('Revoke this link? The client will lose access.')">
                            @csrf
                            <input type="hidden" name="revoke" value="1">
                            <button class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-600 transition hover:bg-red-50">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5h6v2m-1 4v6m-4-6v6M7 7l1 13h8l1-13"/></svg>
                                Revoke
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- ===== Sections ===== --}}
    <div class="mt-4 space-y-3">
        @foreach ($sections as $key)
            @php
                [$label, $hint, $required, $icon, $tint] = \App\Models\Project::PRD_SECTIONS[$key];
                $rows = ($items[$key] ?? collect())->sortBy('id')->values();   // oldest first → v1, v2, …
                $fileCount = $rows->where('path', '!=', null)->count();
            @endphp
            <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm" x-data="{ open: {{ $rows->isEmpty() ? 'false' : 'true' }} }">
                {{-- Header --}}
                <button type="button" @click="open = !open" class="flex w-full items-center gap-3 p-4 text-left transition hover:bg-gray-50">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $tint }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="text-base font-bold text-[var(--color-heading)]">{{ $label }}</span>
                            <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $required ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500' }}">{{ $required ? 'Required' : 'Optional' }}</span>
                            @if ($rows->isNotEmpty())
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-600">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm-3-9 2 2 4-4"/></svg>
                                    Completed
                                </span>
                            @endif
                        </span>
                        <span class="mt-0.5 block text-xs text-[var(--color-muted)]">{{ $hint }}</span>
                    </span>
                    @if ($fileCount)
                        <span class="hidden shrink-0 text-sm text-[var(--color-muted)] sm:block">{{ $fileCount }} {{ Str::plural('File', $fileCount) }}</span>
                    @endif
                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>

                {{-- Body --}}
                <div x-show="open" x-cloak class="border-t border-gray-100">
                    {{-- Submitted entries --}}
                    @if ($rows->isNotEmpty())
                        <ul class="divide-y divide-gray-50 px-4">
                            @foreach ($rows as $i => $row)
                                @php
                                    $badge = ['approved' => 'bg-emerald-50 text-emerald-600', 'rejected' => 'bg-red-50 text-red-600'][$row->status] ?? 'bg-amber-50 text-amber-600';
                                @endphp
                                <li class="py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $row->isFile() ? 'bg-indigo-50 text-indigo-500' : 'bg-gray-100 text-gray-400' }}">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $row->isFile() ? 'M7 3h7l5 5v13H7zM14 3v5h5' : 'M4 6h16M4 12h16M4 18h10' }}"/></svg>
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            @if ($row->isFile())
                                                <p class="truncate text-sm font-semibold text-[var(--color-heading)]">{{ $row->name }}</p>
                                            @else
                                                <p class="whitespace-pre-line text-sm text-[var(--color-heading)]">{{ $row->note }}</p>
                                            @endif
                                            <p class="mt-0.5 truncate text-xs text-[var(--color-muted)]">
                                                Uploaded by {{ $row->submitterName() }} · {{ $row->created_at->format('M j, Y g:i A') }}@if ($row->isFile()) · {{ $row->sizeLabel() }}@endif
                                            </p>
                                        </div>

                                        {{-- Status + version --}}
                                        <span class="hidden shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold sm:block {{ $badge }}">{{ \App\Models\ProjectPrdItem::STATUSES[$row->status] ?? $row->status }}</span>
                                        <span class="hidden shrink-0 rounded-lg bg-gray-50 px-2 py-1 text-[11px] font-semibold text-gray-500 sm:block">v{{ $i + 1 }}</span>

                                        @if ($row->isFile())
                                            <a href="{{ route('admin.projects.prd.download', [$project, $row]) }}" title="Download"
                                               class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-[var(--color-heading)]">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0 4-4m-4 4-4-4M5 19h14"/></svg>
                                            </a>
                                            <a href="{{ asset('storage/'.$row->path) }}" target="_blank" rel="noopener" title="Preview"
                                               class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-[var(--color-heading)]">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                            </a>
                                        @endif

                                        {{-- Row menu --}}
                                        <div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false">
                                            <button type="button" @click="open = !open" title="More"
                                                    class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                            </button>
                                            <div x-show="open" x-cloak class="absolute right-0 z-30 mt-1.5 w-48 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg">
                                                @if ($canEditProject)
                                                    @if ($row->status !== 'approved')
                                                        <form method="POST" action="{{ route('admin.projects.prd.review', [$project, $row]) }}">
                                                            @csrf @method('PUT')
                                                            <input type="hidden" name="status" value="approved">
                                                            <button class="block w-full px-3.5 py-2 text-left text-xs font-medium text-emerald-600 hover:bg-emerald-50">Approve</button>
                                                        </form>
                                                    @endif
                                                    @if ($row->status !== 'rejected')
                                                        <form method="POST" action="{{ route('admin.projects.prd.review', [$project, $row]) }}"
                                                              onsubmit="this.review_note.value = prompt('What needs to change?') ?? ''; return this.review_note.value !== '';">
                                                            @csrf @method('PUT')
                                                            <input type="hidden" name="status" value="rejected">
                                                            <input type="hidden" name="review_note" value="">
                                                            <button class="block w-full px-3.5 py-2 text-left text-xs font-medium text-amber-600 hover:bg-amber-50">Request changes</button>
                                                        </form>
                                                    @endif
                                                    @if ($row->status !== 'pending')
                                                        <form method="POST" action="{{ route('admin.projects.prd.review', [$project, $row]) }}">
                                                            @csrf @method('PUT')
                                                            <input type="hidden" name="status" value="pending">
                                                            <button class="block w-full px-3.5 py-2 text-left text-xs font-medium text-[var(--color-heading)] hover:bg-gray-50">Reset to pending</button>
                                                        </form>
                                                    @endif
                                                @endif
                                                <form method="POST" action="{{ route('admin.projects.prd.destroy', [$project, $row]) }}" onsubmit="return confirm('Remove this entry?')">
                                                    @csrf @method('DELETE')
                                                    <button class="block w-full px-3.5 py-2 text-left text-xs font-medium text-red-600 hover:bg-red-50">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    @if ($row->review_note)
                                        <p class="ml-12 mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">{{ $row->review_note }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Submit --}}
                    <form method="POST" action="{{ route('admin.projects.prd.store', $project) }}" enctype="multipart/form-data" class="p-4"
                          x-data="{ names: 'No file chosen', over: false }">
                        @csrf
                        <input type="hidden" name="section" value="{{ $key }}">

                        <textarea name="note" rows="3" placeholder="Write the details for this section..."
                                  class="w-full rounded-xl border-gray-200 text-sm placeholder:text-gray-400 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"></textarea>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex min-w-0 flex-wrap items-center gap-3"
                                 @dragover.prevent="over = true" @dragleave="over = false"
                                 @drop.prevent="over = false; $refs.input.files = $event.dataTransfer.files; names = [...$refs.input.files].map(f => f.name).join(', ') || 'No file chosen'">
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition"
                                       :class="over ? 'border-[var(--color-primary)] bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.5 12.8 20.7a5 5 0 0 1-7-7l8.2-8.3a3.3 3.3 0 1 1 4.7 4.7l-8.2 8.2a1.7 1.7 0 0 1-2.4-2.4l7.6-7.5"/></svg>
                                    <span class="font-semibold text-[var(--color-heading)]">Choose files</span>
                                    <span class="text-[var(--color-muted)]">or drag &amp; drop</span>
                                    <input type="file" name="files[]" multiple x-ref="input" class="sr-only"
                                           @change="names = [...$event.target.files].map(f => f.name).join(', ') || 'No file chosen'">
                                </label>
                                <span class="min-w-0 truncate text-sm text-[var(--color-muted)]" x-text="names">No file chosen</span>
                            </div>

                            <button class="rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]">Save</button>
                        </div>
                    </form>
                </div>
            </section>
        @endforeach
    </div>
@endif
