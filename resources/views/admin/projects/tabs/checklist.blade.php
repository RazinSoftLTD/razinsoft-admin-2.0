@php
    $chkBadge = ['waiting' => 'bg-gray-100 text-gray-600', 'received' => 'bg-blue-50 text-blue-700', 'rejected' => 'bg-red-50 text-red-600', 'approved' => 'bg-emerald-50 text-emerald-700', 'need_update' => 'bg-amber-50 text-amber-700'];
    $grouped = $project->checklistItems->groupBy(fn ($i) => $i->category ?: 'General');
    $canEdit = $me->allows('projects', 'edit');
@endphp

<div class="space-y-4">
    {{-- Toolbar --}}
    @if ($canEdit)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm" x-data="{ addOpen: false }">
            <div class="text-sm text-[var(--color-muted)]">
                Client requirement checklist
                @if ($availableTemplates->isNotEmpty())· <span class="font-semibold text-[var(--color-heading)]">{{ $availableTemplates->count() }}</span> template items for <span class="font-semibold">{{ $project->project_type }}</span>@endif
            </div>
            <div class="flex items-center gap-2">
                @if ($availableTemplates->isNotEmpty())
                    <form method="POST" action="{{ route('admin.projects.checklist.generate', $project) }}" data-turbo="false">@csrf
                        <button class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-primary)] px-3 py-2 text-sm font-semibold text-[var(--color-primary)] hover:bg-[var(--color-primary-soft)]">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 4v6h6M20 20v-6h-6M20 9A8 8 0 0 0 5.6 5.6M4 15a8 8 0 0 0 14.4 3.4"/></svg> Generate from Template
                        </button>
                    </form>
                @endif
                <button type="button" @click="addOpen = !addOpen" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-3 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Item
                </button>
            </div>

            <form method="POST" action="{{ route('admin.projects.checklist.store', $project) }}" data-turbo="false" x-show="addOpen" x-cloak class="grid w-full gap-3 border-t border-gray-100 pt-4 sm:grid-cols-4">
                @csrf
                <input type="text" name="title" required placeholder="Item (e.g. SSH access)" class="h-10 rounded-lg border-gray-200 text-sm sm:col-span-2">
                <input type="text" name="category" placeholder="Category (optional)" class="h-10 rounded-lg border-gray-200 text-sm">
                <div class="flex gap-2">
                    <input type="date" name="deadline" class="h-10 flex-1 rounded-lg border-gray-200 text-sm" title="Deadline">
                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                </div>
                <label class="flex items-center gap-2 text-sm text-[var(--color-muted)] sm:col-span-4"><input type="checkbox" name="required" value="1" checked class="rounded border-gray-300"> Required</label>
            </form>
        </div>
    @endif

    @forelse ($grouped as $category => $items)
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50/60 px-5 py-3">
                <h3 class="text-sm font-bold text-[var(--color-heading)]">{{ $category }} <span class="ml-1 text-xs font-medium text-gray-400">{{ $items->count() }}</span></h3>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach ($items as $item)
                    <div class="flex flex-wrap items-center gap-3 px-5 py-3" x-data="{ note: false }">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-[var(--color-heading)]">
                                {{ $item->title }}
                                @if ($item->required)<span class="ml-1 text-xs font-semibold text-red-400">*</span>@endif
                            </p>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-gray-400">
                                @if ($item->deadline)<span>Deadline {{ $item->deadline->format('d M Y') }}</span>@endif
                                @if ($item->received_at)<span>· Received {{ $item->received_at->format('d M') }}</span>@endif
                                @if ($item->comment)<button type="button" @click="note = !note" class="text-[var(--color-primary)] hover:underline">Comment</button>@endif
                            </div>
                            <p x-show="note" x-cloak class="mt-1 rounded bg-gray-50 p-2 text-xs text-[var(--color-muted)]">{{ $item->comment }}</p>
                        </div>
                        @if ($canEdit)
                            <form method="POST" action="{{ route('admin.projects.checklist.update', [$project, $item]) }}" data-turbo="false" class="flex items-center gap-2">
                                @csrf @method('PUT')
                                <select name="status" onchange="this.form.submit()" class="h-9 rounded-lg border-gray-200 text-xs font-semibold {{ $chkBadge[$item->status] ?? '' }}">
                                    @foreach (\App\Models\ProjectChecklistItem::STATUSES as $k => $v)<option value="{{ $k }}" @selected($item->status === $k)>{{ $v }}</option>@endforeach
                                </select>
                            </form>
                            {{-- comment editor --}}
                            <div x-data="{ edit: false }" class="relative">
                                <button type="button" @click="edit = !edit" class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 hover:bg-gray-100" title="Comment"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v10Z"/></svg></button>
                                <form method="POST" action="{{ route('admin.projects.checklist.update', [$project, $item]) }}" data-turbo="false" x-show="edit" x-cloak @click.outside="edit = false" class="absolute right-0 z-20 mt-1 w-64 rounded-lg border border-gray-100 bg-white p-3 shadow-lg">
                                    @csrf @method('PUT')
                                    <textarea name="comment" rows="3" placeholder="Comment / note…" class="w-full rounded-lg border-gray-200 text-sm">{{ $item->comment }}</textarea>
                                    <input type="date" name="deadline" value="{{ $item->deadline?->toDateString() }}" class="mt-2 h-9 w-full rounded-lg border-gray-200 text-sm">
                                    <button class="mt-2 w-full rounded-lg bg-[var(--color-primary)] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                                </form>
                            </div>
                            <form method="POST" action="{{ route('admin.projects.checklist.destroy', [$project, $item]) }}" data-turbo="false" onsubmit="return confirm('Remove item?')">@csrf @method('DELETE')
                                <button class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg></button>
                            </form>
                        @else
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $chkBadge[$item->status] ?? '' }}">{{ \App\Models\ProjectChecklistItem::STATUSES[$item->status] ?? $item->status }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-200 py-12 text-center text-sm text-gray-400">
            No checklist items yet.@if ($canEdit && $availableTemplates->isNotEmpty()) Click “Generate from Template” to load the {{ $project->project_type }} checklist.@endif
        </div>
    @endforelse
</div>
