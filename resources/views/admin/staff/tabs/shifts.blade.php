<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm" style="min-width:720px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Shift</th>
                    <th class="px-5 py-3 font-semibold">Hours</th>
                    <th class="px-5 py-3 font-semibold">Week off</th>
                    <th class="px-5 py-3 font-semibold">Effective</th>
                    <th class="px-5 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($shifts as $s)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <span class="font-semibold text-[var(--color-heading)]">{{ $s->name }}</span>
                            @if ($s->isCurrent())<span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Current</span>@endif
                        </td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ \Carbon\Carbon::parse($s->starts_at)->format('g:i A') }} – {{ \Carbon\Carbon::parse($s->ends_at)->format('g:i A') }}</td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ implode(', ', $s->weekOffLabels()) ?: '—' }}</td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $s->effective_from?->format('d M Y') }} → {{ $s->effective_to?->format('d M Y') ?? 'ongoing' }}</td>
                        <td class="px-5 py-3 text-right">
                            @if ($canEdit)
                                <form method="POST" action="{{ route('admin.staff.shifts.destroy', [$staff, $s]) }}" onsubmit="return confirm('Remove this shift?')">
                                    @csrf @method('DELETE')
                                    <button class="rounded p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-gray-300">No shift assigned — the office hours from HR Settings apply.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($canEdit)
        <form method="POST" action="{{ route('admin.staff.shifts.store', $staff) }}" class="space-y-3 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            @csrf
            <h3 class="text-sm font-bold text-[var(--color-heading)]">Assign shift</h3>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Shift name <span class="text-red-500">*</span></label>
                <input name="name" required maxlength="60" value="General" class="h-10 w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Starts</label>
                    <input type="time" name="starts_at" required value="10:00" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Ends</label>
                    <input type="time" name="ends_at" required value="19:00" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Week off</label>
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Models\EmployeeShift::DAYS as $k => $d)
                        <label class="flex items-center gap-1 text-xs text-[var(--color-muted)]">
                            <input type="checkbox" name="week_offs[]" value="{{ $k }}" class="h-4 w-4 rounded accent-[var(--color-primary)]"> {{ $d }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">From <span class="text-red-500">*</span></label>
                    <input type="date" name="effective_from" required value="{{ today()->format('Y-m-d') }}" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">To</label>
                    <input type="date" name="effective_to" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                </div>
            </div>
            <button class="w-full rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Assign</button>
        </form>
    @endif
</div>
