@php
    $checklistPending = $project->checklistItems->whereNotIn('status', ['approved', 'received'])->count();
    $tasksDone = $project->allTasks->where('status', 'completed')->count();
    $tasksTotal = $project->allTasks->count();
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        {{-- Key info --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Project Details</h2>
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                <div><dt class="text-xs uppercase tracking-wide text-gray-400">Client</dt><dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $project->client?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-400">Company</dt><dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $project->company ?: '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-400">Project Manager</dt><dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $project->projectManager?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-400">Sales Person</dt><dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $project->salesPerson?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-400">Account Manager</dt><dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $project->accountManager?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-400">Priority</dt><dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ \App\Models\Project::PRIORITIES[$project->priority] ?? ucfirst($project->priority) }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-400">Budget</dt><dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $project->budget ? $cur.number_format($project->budget, 0) : '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-400">Currency</dt><dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $project->currency }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-400">Start Date</dt><dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $project->start_date?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-400">Expected Delivery</dt><dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $project->expected_delivery?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-400">Actual Delivery</dt><dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $project->actual_delivery?->format('d M Y') ?? '—' }}</dd></div>
            </dl>
            @if ($project->description)
                <div class="mt-5 border-t border-gray-100 pt-4">
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Description</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-[var(--color-muted)]">{{ $project->description }}</dd>
                </div>
            @endif
        </div>

        {{-- Snapshot --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ([['Workstreams', $project->workstreams->count(), 'text-indigo-700'], ['Tasks Done', $tasksDone.'/'.$tasksTotal, 'text-emerald-700'], ['Pending Reqs', $checklistPending, 'text-amber-600'], ['Change Reqs', $project->changeRequests->count(), 'text-purple-700']] as [$l, $v, $t])
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs text-[var(--color-muted)]">{{ $l }}</p>
                    <p class="mt-1 text-xl font-bold {{ $t }}">{{ $v }}</p>
                </div>
            @endforeach
        </div>

        {{-- Workstream progress --}}
        @if ($project->workstreams->isNotEmpty())
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Workstream Progress</h2>
                <div class="space-y-3">
                    @foreach ($project->workstreams as $ws)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="font-medium text-[var(--color-heading)]">{{ $ws->name }}</span>
                                <span class="text-xs font-semibold text-gray-400">{{ $ws->computed_progress }}%</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-[var(--color-primary)]" style="width: {{ $ws->computed_progress }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Team --}}
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Team Members</h2>
            @forelse ($project->members as $member)
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]">{{ strtoupper(substr($member->user->name, 0, 1)) }}</span>
                        <div>
                            <p class="text-sm font-medium text-[var(--color-heading)]">{{ $member->user->name }}</p>
                            @if ($member->role)<p class="text-xs text-gray-400">{{ $member->role }}</p>@endif
                        </div>
                    </div>
                    @if ($me->allows('projects', 'edit'))
                        <form method="POST" action="{{ route('admin.projects.members.destroy', [$project, $member]) }}" data-turbo="false">@csrf @method('DELETE')
                            <button class="grid h-7 w-7 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-[var(--color-muted)]">No members yet.</p>
            @endforelse

            @if ($me->allows('projects', 'edit'))
                <form method="POST" action="{{ route('admin.projects.members.store', $project) }}" data-turbo="false" class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                    @csrf
                    <select name="user_id" required class="h-9 w-full rounded-lg border-gray-200 text-sm">
                        <option value="">Select a member…</option>
                        @foreach ($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                    <div class="flex gap-2">
                        <select name="role" class="h-9 flex-1 rounded-lg border-gray-200 text-sm">
                            <option value="">Role…</option>
                            @foreach (\App\Models\ProjectMember::ROLES as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                        </select>
                        <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
