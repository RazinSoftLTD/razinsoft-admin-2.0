@php $wsBadge = ['not_started' => 'bg-gray-100 text-gray-600', 'planning' => 'bg-indigo-50 text-indigo-700', 'development' => 'bg-blue-50 text-blue-700', 'testing' => 'bg-amber-50 text-amber-700', 'review' => 'bg-purple-50 text-purple-700', 'completed' => 'bg-emerald-50 text-emerald-700']; @endphp

<div class="space-y-4">
    @if ($me->allows('projects', 'edit'))
        <div x-data="{ open: false }" class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <button type="button" @click="open = !open" class="flex items-center gap-2 text-sm font-semibold text-[var(--color-primary)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Workstream
            </button>
            <form method="POST" action="{{ route('admin.projects.workstreams.store', $project) }}" data-turbo="false" x-show="open" x-cloak class="mt-4 grid gap-3 sm:grid-cols-4">
                @csrf
                <input type="text" name="name" required placeholder="Name (e.g. Website)" class="h-10 rounded-lg border-gray-200 text-sm sm:col-span-2">
                <select name="type" class="h-10 rounded-lg border-gray-200 text-sm">
                    <option value="">Type…</option>
                    @foreach (\App\Models\ProjectWorkstream::TYPES as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
                <div class="flex gap-2">
                    <select name="status" class="h-10 flex-1 rounded-lg border-gray-200 text-sm">
                        @foreach (\App\Models\ProjectWorkstream::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                </div>
            </form>
        </div>
    @endif

    @forelse ($project->workstreams as $ws)
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg></span>
                    <div>
                        <p class="font-semibold text-[var(--color-heading)]">{{ $ws->name }}</p>
                        @if ($ws->type)<p class="text-xs text-gray-400">{{ $ws->type }}</p>@endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if ($me->allows('projects', 'edit'))
                        <form method="POST" action="{{ route('admin.projects.workstreams.update', [$project, $ws]) }}" data-turbo="false">@csrf @method('PUT')
                            <select name="status" onchange="this.form.submit()" class="h-9 rounded-lg border-gray-200 text-xs font-semibold {{ $wsBadge[$ws->status] ?? '' }}">
                                @foreach (\App\Models\ProjectWorkstream::STATUSES as $k => $v)<option value="{{ $k }}" @selected($ws->status === $k)>{{ $v }}</option>@endforeach
                            </select>
                        </form>
                        <form method="POST" action="{{ route('admin.projects.workstreams.destroy', [$project, $ws]) }}" data-turbo="false" onsubmit="return confirm('Remove this workstream?')">@csrf @method('DELETE')
                            <button class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg></button>
                        </form>
                    @else
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $wsBadge[$ws->status] ?? '' }}">{{ \App\Models\ProjectWorkstream::STATUSES[$ws->status] ?? $ws->status }}</span>
                    @endif
                </div>
            </div>
            <div class="mt-3 flex items-center gap-3">
                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-[var(--color-primary)]" style="width: {{ $ws->computed_progress }}%"></div></div>
                <span class="text-xs font-semibold text-gray-400">{{ $ws->computed_progress }}%</span>
                <span class="text-xs text-gray-400">{{ $ws->tasks()->count() }} tasks</span>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-200 py-12 text-center text-sm text-gray-400">
            No workstreams. Child tracks (Website, Admin, Android, iOS…) are optional — add them above, or keep tasks directly on the project.
        </div>
    @endforelse
</div>
