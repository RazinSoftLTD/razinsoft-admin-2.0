@php
    use App\Models\ProjectTimeLog;

    $logs = $project->timeLogs()->with('user:id,name,photo', 'task:id,title')->get();
    $total = (int) $logs->sum('minutes');
    $byTask = $logs->groupBy('task_id');
    $estimate = $project->hours_allocated ? $project->hours_allocated * 60 : null;
    $pct = $estimate ? min(100, (int) round($total / $estimate * 100)) : null;
    $me = auth()->user();
    $canManage = $me->allows('projects', 'edit');
@endphp

{{-- ===== Totals ===== --}}
<div class="grid items-start gap-4 lg:grid-cols-2">
    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-bold text-[var(--color-heading)]">Total Time Logged</h3>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-2">
            <p class="text-2xl font-bold text-[var(--color-primary)]">{{ ProjectTimeLog::humanMinutes($total) }}</p>
            @if ($estimate)
                <p class="text-sm text-[var(--color-muted)]">of {{ $project->hours_allocated }}h estimate</p>
            @endif
        </div>
        @if ($pct !== null)
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-2 rounded-full {{ $pct >= 100 ? 'bg-red-500' : 'bg-[var(--color-primary)]' }}" style="width: {{ $pct }}%"></div>
            </div>
            @if ($pct >= 100)
                <p class="mt-1.5 text-xs font-semibold text-red-600">Over the estimated hours.</p>
            @endif
        @endif
        <p class="mt-3 text-xs text-[var(--color-muted)]">{{ $logs->count() }} {{ Str::plural('entry', $logs->count()) }} · {{ $logs->pluck('user_id')->unique()->count() }} {{ Str::plural('member', $logs->pluck('user_id')->unique()->count()) }}</p>
    </div>

    {{-- Log time --}}
    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-bold text-[var(--color-heading)]">Log Time</h3>
        <form method="POST" action="{{ route('admin.projects.time.store', $project) }}" class="mt-3 space-y-3">
            @csrf
            <div class="flex flex-wrap items-center gap-2">
                <select name="task_id" class="h-10 min-w-0 flex-1 rounded-lg border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                    <option value="">Whole project (no task)</option>
                    @foreach ($tasks as $t)
                        <option value="{{ $t->id }}">{{ Str::limit($t->title, 60) }}</option>
                    @endforeach
                </select>
                <input type="date" name="spent_on" value="{{ now()->toDateString() }}" required
                       class="h-10 rounded-lg border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <input type="number" name="hours" min="0" max="999" placeholder="Hours" class="h-10 w-24 rounded-lg border-gray-200 text-sm">
                <input type="number" name="minutes" min="0" max="59" placeholder="Minutes" class="h-10 w-28 rounded-lg border-gray-200 text-sm">
                <input type="text" name="note" maxlength="255" placeholder="What did you work on? (optional)"
                       class="h-10 min-w-0 flex-1 rounded-lg border-gray-200 text-sm placeholder:text-gray-400">
                <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]">Add</button>
            </div>
            @error('hours')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        </form>
    </div>
</div>

{{-- ===== Per-task history ===== --}}
<div class="mt-4 rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="border-b border-gray-100 px-5 py-4">
        <h3 class="text-lg font-bold text-[var(--color-heading)]">Time History</h3>
        <p class="text-xs text-[var(--color-muted)]">Grouped by task — expand to see every entry.</p>
    </div>

    @if ($logs->isEmpty())
        <p class="px-5 py-12 text-center text-sm text-gray-400">No time logged yet.</p>
    @else
        <ul class="divide-y divide-gray-50">
            @foreach ($byTask as $taskId => $rows)
                @php
                    $taskTitle = $taskId ? ($rows->first()->task?->title ?? 'Deleted task') : 'Whole project (no task)';
                    $sum = (int) $rows->sum('minutes');
                @endphp
                <li x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex w-full items-center gap-3 px-5 py-3 text-left transition hover:bg-gray-50">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-[var(--color-heading)]">{{ $taskTitle }}</span>
                            <span class="text-xs text-[var(--color-muted)]">{{ $rows->count() }} {{ Str::plural('entry', $rows->count()) }}</span>
                        </span>
                        <span class="shrink-0 rounded-full bg-gray-50 px-3 py-1 text-sm font-bold text-[var(--color-heading)]">{{ ProjectTimeLog::humanMinutes($sum) }}</span>
                        <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>

                    <ul x-show="open" x-cloak class="divide-y divide-gray-50 bg-gray-50 px-5">
                        @foreach ($rows as $log)
                            <li class="flex items-center gap-3 py-2.5">
                                @if ($log->user?->photo_url)
                                    <img src="{{ $log->user->photo_url }}" class="h-7 w-7 shrink-0 rounded-full object-cover" alt="">
                                @else
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-white text-[11px] font-bold text-[var(--color-muted)]">{{ strtoupper(substr($log->user?->name ?? '?', 0, 1)) }}</span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-[var(--color-heading)]">
                                        <span class="font-semibold">{{ $log->user?->name ?? 'Someone' }}</span>
                                        @if ($log->note) — {{ $log->note }} @endif
                                    </p>
                                    <p class="text-xs text-[var(--color-muted)]">{{ $log->spent_on->format('d M Y') }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold text-[var(--color-heading)]">{{ $log->duration() }}</span>
                                @if ($canManage || $log->user_id === $me->id)
                                    <form method="POST" action="{{ route('admin.projects.time.destroy', [$project, $log]) }}" onsubmit="return confirm('Remove this time entry?')">
                                        @csrf @method('DELETE')
                                        <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-300 transition hover:bg-red-50 hover:text-red-500" title="Remove">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    @endif
</div>
