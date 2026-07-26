{{-- Profile tab. The header above already carries the avatar, name and Edit button, so this
     only holds the details — grouped so they can be scanned rather than read line by line. --}}
@php
    $groups = [
        ['Work', 'M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2M3 7h18v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z', [
            ['Employee Code', $staff->employee_code],
            ['Designation', $staff->designation?->name],
            ['Department', $staff->department?->name],
            ['Reports To', $staff->reportsTo?->name],
            ['Employment Type', $staff->employment_type ? ucfirst(str_replace('_', ' ', $staff->employment_type)) : null],
            ['Joining Date', $staff->joining_date?->format('d M Y')],
        ]],
        ['Contact', 'M4 4h16v16H4zM4 7l8 6 8-6', [
            ['Email', $staff->email],
            ['Phone', trim(($staff->dial_code ? $staff->dial_code.' ' : '').$staff->phone) ?: null],
            ['Address', $staff->address],
            ['City', $staff->city],
            ['Country', $staff->country],
        ]],
        ['Personal', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0', [
            ['Date of Birth', $staff->date_of_birth?->format('d M Y')],
            ['Gender', $staff->gender ? ucfirst($staff->gender) : null],
            ['Language', \App\Http\Controllers\Admin\StaffController::LANGUAGES[$staff->language] ?? $staff->language],
            ['Biometric ID', $staff->biometric_id],
        ]],
    ];
    $statusTone = ['active' => 'bg-emerald-50 text-emerald-700', 'inactive' => 'bg-gray-100 text-gray-600', 'blocked' => 'bg-red-50 text-red-600'];
@endphp

<div class="grid gap-5 lg:grid-cols-3">
    {{-- Details --}}
    <div class="space-y-5 lg:col-span-2">
        @foreach ($groups as [$title, $icon, $rows])
            @php $filled = collect($rows)->filter(fn ($r) => filled($r[1])); @endphp
            <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center gap-2.5 border-b border-gray-100 px-5 py-3.5">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </span>
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">{{ $title }}</h2>
                    <span class="ml-auto text-xs text-gray-400">{{ $filled->count() }}/{{ count($rows) }} filled</span>
                </div>
                <dl class="grid gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
                    @foreach ($rows as [$label, $value])
                        <div class="min-w-0">
                            <dt class="text-xs uppercase tracking-wide text-gray-400">{{ $label }}</dt>
                            <dd class="mt-0.5 truncate text-sm font-medium {{ filled($value) ? 'text-[var(--color-heading)]' : 'text-gray-300' }}">{{ filled($value) ? $value : 'Not set' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach

        @if ($staff->about)
            <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="mb-2 text-sm font-bold text-[var(--color-heading)]">About</h2>
                <p class="whitespace-pre-wrap text-sm leading-relaxed text-[var(--color-muted)]">{{ $staff->about }}</p>
            </section>
        @endif
    </div>

    {{-- At a glance --}}
    <div class="space-y-5">
        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Account</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[var(--color-muted)]">Status</dt>
                    <dd><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusTone[$staff->status] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst($staff->status) }}</span></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[var(--color-muted)]">Role</dt>
                    <dd class="font-medium text-[var(--color-heading)]">{{ $staff->isAdmin() ? 'Administrator' : ($staff->assignedRole?->name ?? 'No role') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[var(--color-muted)]">Last seen</dt>
                    <dd class="font-medium text-[var(--color-heading)]">{{ $staff->last_seen_at?->diffForHumans() ?? 'Never' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[var(--color-muted)]">Member since</dt>
                    <dd class="font-medium text-[var(--color-heading)]">{{ $staff->created_at?->format('M Y') }}</dd>
                </div>
            </dl>
        </section>

        {{-- Whichever shift is in force today --}}
        @php $shift = \App\Models\EmployeeShift::where('user_id', $staff->id)->orderByDesc('effective_from')->get()->first(fn ($s) => $s->isCurrent()); @endphp
        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Current Shift</h2>
                <a href="{{ route('admin.staff.show', [$staff, 'tab' => 'shifts']) }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">Manage</a>
            </div>
            @if ($shift)
                <p class="text-sm font-semibold text-[var(--color-heading)]">{{ $shift->name }}</p>
                <p class="mt-0.5 text-sm text-[var(--color-muted)]">{{ \Carbon\Carbon::parse($shift->starts_at)->format('g:i A') }} – {{ \Carbon\Carbon::parse($shift->ends_at)->format('g:i A') }}</p>
                @if ($offs = $shift->weekOffLabels())
                    <p class="mt-1 text-xs text-gray-400">Week off: {{ implode(', ', $offs) }}</p>
                @endif
            @else
                <p class="text-sm text-gray-400">No shift assigned — office hours from HR Settings apply.</p>
            @endif
        </section>

        @if ($canEdit)
            <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Quick actions</h2>
                <div class="space-y-2">
                    @foreach ([['Add payroll', 'payroll'], ['Upload document', 'documents'], ['Assign shift', 'shifts'], ['View attendance', 'attendance']] as [$label, $goTab])
                        <a href="{{ route('admin.staff.show', [$staff, 'tab' => $goTab]) }}" class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2 text-sm font-medium text-[var(--color-heading)] transition hover:border-[var(--color-primary)] hover:bg-gray-50">
                            {{ $label }}
                            <svg class="h-4 w-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
