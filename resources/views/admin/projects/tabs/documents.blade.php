@php
    $grouped = $project->documents->groupBy('type');
    $canEdit = $me->allows('projects', 'edit');
@endphp

<div class="space-y-4">
    @if ($canEdit)
        <form method="POST" action="{{ route('admin.projects.documents.store', $project) }}" enctype="multipart/form-data" data-turbo="false"
              x-data="{ name: '' }" class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            @csrf
            <select name="type" required class="h-10 rounded-lg border-gray-200 text-sm">
                @foreach (\App\Models\Project::DOCUMENT_TYPES as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
            </select>
            <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-2.5 text-sm text-[var(--color-muted)] hover:bg-gray-50">
                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                <span class="truncate" x-text="name || 'Choose a file (max 20MB)'"></span>
                <input type="file" name="file" required class="hidden" @change="name = $event.target.files[0]?.name || ''">
            </label>
            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Upload</button>
        </form>
    @endif

    @forelse ($grouped as $type => $docs)
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50/60 px-5 py-3"><h3 class="text-sm font-bold text-[var(--color-heading)]">{{ $type }} <span class="ml-1 text-xs font-medium text-gray-400">{{ $docs->count() }}</span></h3></div>
            <div class="grid gap-2 p-4 sm:grid-cols-2">
                @foreach ($docs as $doc)
                    <div class="flex items-center gap-3 rounded-lg border border-gray-100 p-2.5">
                        @if ($doc->isImage())
                            <a href="{{ $doc->url }}" target="_blank" class="shrink-0"><img src="{{ $doc->url }}" alt="" class="h-10 w-10 rounded object-cover"></a>
                        @else
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded bg-gray-100 text-gray-400"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" d="M7 3h7l5 5v13H7zM14 3v5h5"/></svg></span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <a href="{{ $doc->url }}" target="_blank" class="block truncate text-sm font-medium text-[var(--color-heading)] hover:text-[var(--color-primary)]" title="{{ $doc->name }}">{{ $doc->name }}</a>
                            <p class="text-xs text-gray-400">{{ $doc->readable_size }} · {{ $doc->uploader?->name ?? '—' }} · {{ $doc->created_at->format('d M Y') }}</p>
                        </div>
                        @if ($canEdit)
                            <form method="POST" action="{{ route('admin.projects.documents.destroy', [$project, $doc]) }}" data-turbo="false" onsubmit="return confirm('Remove document?')">@csrf @method('DELETE')
                                <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg></button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-200 py-12 text-center text-sm text-gray-400">No documents uploaded yet.</div>
    @endforelse
</div>
