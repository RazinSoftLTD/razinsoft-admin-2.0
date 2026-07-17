<div>
    @if ($canEdit)
        <form method="POST" action="{{ route('admin.projects.files.store', $project) }}" enctype="multipart/form-data" data-turbo="false" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-gray-200 bg-white p-4">
            @csrf
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0 4 4m-4-4-4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                </span>
                <input type="file" name="files[]" multiple required class="text-sm text-[var(--color-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
            </div>
            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Upload</button>
        </form>
    @endif

    @if ($project->files->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center"><p class="text-sm text-gray-400">No files uploaded yet.</p></div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($project->files as $file)
                <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-gray-50 text-gray-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M14 3v5h5"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-[var(--color-heading)]" title="{{ $file->name }}">{{ $file->name }}</p>
                        <p class="text-[11px] text-gray-400">{{ $file->sizeLabel() }} · {{ $file->uploader?->name ?? '—' }} · {{ $file->created_at->format('d M, Y') }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <a href="{{ route('admin.projects.files.download', [$project, $file]) }}" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Download">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0 4-4m-4 4-4-4M4 19h16"/></svg>
                        </a>
                        @if ($canEdit)
                            <form method="POST" action="{{ route('admin.projects.files.destroy', [$project, $file]) }}" data-turbo="false" onsubmit="return confirm('Delete this file?')">
                                @csrf @method('DELETE')
                                <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500" title="Delete">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m3 0v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7"/></svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
