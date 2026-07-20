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
    {{-- Add member modal — opened from the button beside the project title --}}
    @if ($canEdit)
        <div x-show="addOpen" x-cloak @keydown.escape.window="addOpen = false">
            <div x-show="addOpen" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="addOpen = false"></div>
            <div x-show="addOpen" x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-24" @click.self="addOpen = false">
                <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM20 8v6M23 11h-6"/></svg>
                            </span>
                            <div>
                                <h3 class="text-base font-bold text-[var(--color-heading)]">Add Member</h3>
                                <p class="text-xs text-[var(--color-muted)]">Give someone access to this project</p>
                            </div>
                        </div>
                        <button type="button" @click="addOpen = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('admin.projects.members.store', $project) }}" data-turbo="false" class="p-5">
                        @csrf
                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Member <span class="text-red-500">*</span></label>
                        @php $available = $staff->reject(fn ($s) => in_array($s->id, $memberIds, true))->values(); @endphp
                        @if ($available->isEmpty())
                            <p class="rounded-lg bg-gray-50 px-3 py-4 text-center text-sm text-gray-400">Everyone is already on this project.</p>
                        @else
                            <x-admin.searchable-select name="user_id"
                                :options="$available->map(fn ($s) => ['id' => $s->id, 'label' => $s->name])"
                                placeholder="Search staff…" :allow-clear="false" required />
                        @endif
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" @click="addOpen = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                            <button @disabled($available->isEmpty())
                                    class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)] disabled:opacity-60">Add Member</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
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
