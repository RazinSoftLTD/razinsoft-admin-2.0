@php
    $memberIds = $project->members->pluck('user_id')->all();
    // Per-member task stats for this project.
    $taskStats = \App\Models\ProjectTask::query()
        ->where('project_id', $project->id)->whereNotNull('assigned_to')
        ->selectRaw('assigned_to,
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done,
            SUM(CASE WHEN status NOT IN (?, ?) AND due_date IS NOT NULL AND due_date < ? THEN 1 ELSE 0 END) as late',
            ['completed', 'completed', 'cancelled', now()->toDateString()])
        ->groupBy('assigned_to')->get()->keyBy('assigned_to');
@endphp

<div>
    @if ($canEdit)
        <form method="POST" action="{{ route('admin.projects.members.store', $project) }}" data-turbo="false" class="mb-4 flex flex-wrap items-center gap-2">
            @csrf
            <select name="user_id" required class="h-10 w-64 rounded-lg border-gray-200 text-sm">
                <option value="">Add a member…</option>
                @foreach ($staff as $s)
                    @continue(in_array($s->id, $memberIds, true))
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add Member</button>
        </form>
    @endif

    @if ($project->members->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-200 py-16 text-center"><p class="text-sm text-gray-400">No members on this project yet.</p></div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full min-w-[650px] text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/70 text-left text-[11px] uppercase tracking-wide text-gray-400">
                        <th class="px-4 py-3 font-semibold">Member</th>
                        <th class="px-4 py-3 text-center font-semibold">Assigned Tasks</th>
                        <th class="px-4 py-3 text-center font-semibold">Completed</th>
                        <th class="px-4 py-3 text-center font-semibold">Late</th>
                        <th class="px-4 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($project->members as $member)
                        @php $st = $taskStats->get($member->user_id); @endphp
                        <tr class="transition hover:bg-gray-50/60">
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-3">
                                    @include('admin.projects._avatars', ['users' => [$member->user], 'max' => 1, 'size' => 8])
                                    <div>
                                        <p class="font-semibold text-[var(--color-heading)]">{{ $member->user?->name }}</p>
                                        @if ($member->user?->job_title)<p class="text-xs text-gray-400">{{ $member->user->job_title }}</p>@endif
                                    </div>
                                    @if ($project->project_manager_id === $member->user_id)<span class="rounded bg-[var(--color-primary-soft)] px-1.5 py-0.5 text-[10px] font-bold text-[var(--color-primary)]">PM</span>@endif
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-center font-semibold text-[var(--color-heading)]">{{ $st->total ?? 0 }}</td>
                            <td class="px-4 py-3.5 text-center font-semibold text-emerald-600">{{ $st->done ?? 0 }}</td>
                            <td class="px-4 py-3.5 text-center font-semibold {{ ($st->late ?? 0) > 0 ? 'text-red-500' : 'text-[var(--color-muted)]' }}">{{ $st->late ?? 0 }}</td>
                            <td class="px-4 py-3.5 text-right">
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('admin.projects.members.destroy', [$project, $member]) }}" data-turbo="false" onsubmit="return confirm('Remove this member?')">
                                        @csrf @method('DELETE')
                                        <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500" title="Remove">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
