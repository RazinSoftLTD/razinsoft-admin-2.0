@php
    $apprBadge = ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-red-50 text-red-600'];
    $devBadge = ['not_started' => 'bg-gray-100 text-gray-600', 'in_progress' => 'bg-blue-50 text-blue-700', 'completed' => 'bg-emerald-50 text-emerald-700'];
    $priText = ['low' => 'text-gray-500', 'medium' => 'text-amber-600', 'high' => 'text-orange-600', 'critical' => 'text-red-600'];
    $sym = \App\Models\Currency::symbolMap(); $cur = $sym[$project->currency] ?? '';
    $canEdit = $me->allows('projects', 'edit');
@endphp

<div class="space-y-4">
    @if ($canEdit)
        <div x-data="{ open: false }" class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <button type="button" @click="open = !open" class="flex items-center gap-2 text-sm font-semibold text-[var(--color-primary)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> New Change Request
            </button>
            <form method="POST" action="{{ route('admin.projects.change-requests.store', $project) }}" data-turbo="false" x-show="open" x-cloak class="mt-4 space-y-3">
                @csrf
                <input type="text" name="title" required placeholder="Title" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                <textarea name="description" rows="2" placeholder="Description" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                <div class="grid gap-3 sm:grid-cols-3">
                    <select name="priority" class="h-10 rounded-lg border-gray-200 text-sm">
                        @foreach (\App\Models\ProjectChangeRequest::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($k === 'medium')>{{ $v }}</option>@endforeach
                    </select>
                    <input type="number" name="estimated_cost" step="0.01" min="0" placeholder="Est. cost" class="h-10 rounded-lg border-gray-200 text-sm">
                    <input type="text" name="estimated_time" placeholder="Est. time (e.g. 3 days)" class="h-10 rounded-lg border-gray-200 text-sm">
                </div>
                <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add Change Request</button>
            </form>
        </div>
    @endif

    @forelse ($project->changeRequests as $cr)
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold text-[var(--color-heading)]">{{ $cr->title }}
                        <span class="ml-1 text-xs font-semibold uppercase {{ $priText[$cr->priority] ?? '' }}">{{ $cr->priority }}</span>
                    </p>
                    @if ($cr->description)<p class="mt-1 whitespace-pre-wrap text-sm text-[var(--color-muted)]">{{ $cr->description }}</p>@endif
                    <p class="mt-2 text-xs text-gray-400">
                        @if ($cr->estimated_cost)Est. {{ $cur }}{{ number_format($cr->estimated_cost, 0) }} · @endif
                        @if ($cr->estimated_time){{ $cr->estimated_time }} · @endif
                        by {{ $cr->requester?->name ?? '—' }} · {{ $cr->created_at->format('d M Y') }}
                    </p>
                </div>
                <div class="flex flex-col items-end gap-2">
                    @if ($canEdit)
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('admin.projects.change-requests.update', [$project, $cr]) }}" data-turbo="false">@csrf @method('PUT')
                                <select name="approval_status" onchange="this.form.submit()" class="h-8 rounded-lg border-gray-200 text-xs font-semibold {{ $apprBadge[$cr->approval_status] ?? '' }}">
                                    @foreach (\App\Models\ProjectChangeRequest::APPROVAL_STATUSES as $k => $v)<option value="{{ $k }}" @selected($cr->approval_status === $k)>{{ $v }}</option>@endforeach
                                </select>
                            </form>
                            <form method="POST" action="{{ route('admin.projects.change-requests.update', [$project, $cr]) }}" data-turbo="false">@csrf @method('PUT')
                                <select name="development_status" onchange="this.form.submit()" class="h-8 rounded-lg border-gray-200 text-xs font-semibold {{ $devBadge[$cr->development_status] ?? '' }}">
                                    @foreach (\App\Models\ProjectChangeRequest::DEVELOPMENT_STATUSES as $k => $v)<option value="{{ $k }}" @selected($cr->development_status === $k)>{{ $v }}</option>@endforeach
                                </select>
                            </form>
                            <form method="POST" action="{{ route('admin.projects.change-requests.destroy', [$project, $cr]) }}" data-turbo="false" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')
                                <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg></button>
                            </form>
                        </div>
                    @else
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $apprBadge[$cr->approval_status] ?? '' }}">{{ \App\Models\ProjectChangeRequest::APPROVAL_STATUSES[$cr->approval_status] ?? $cr->approval_status }}</span>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $devBadge[$cr->development_status] ?? '' }}">{{ \App\Models\ProjectChangeRequest::DEVELOPMENT_STATUSES[$cr->development_status] ?? $cr->development_status }}</span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-200 py-12 text-center text-sm text-gray-400">No change requests.</div>
    @endforelse
</div>
