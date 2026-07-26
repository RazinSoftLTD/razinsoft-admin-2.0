@extends('admin.layouts.app')
@section('title', $staff->name)

@php
    $tabs = \App\Http\Controllers\Admin\StaffController::TABS;
    // Permissions stays admin-only, matching the standalone permissions screen.
    if (! auth()->user()->isAdmin()) {
        unset($tabs['permissions']);
    }
@endphp

@section('content')
    {{-- Who this is --}}
    <div class="mb-5">
        <nav class="mb-2 flex items-center gap-2 text-sm text-[var(--color-muted)]">
            <a href="{{ route('admin.staff.index') }}" class="hover:text-[var(--color-heading)]">Employees</a>
            <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
            <span class="text-[var(--color-heading)]">{{ $staff->name }}</span>
        </nav>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                @if ($staff->photo_url)
                    <img src="{{ $staff->photo_url }}" alt="" class="h-14 w-14 rounded-full object-cover ring-4 ring-gray-50">
                @else
                    <span class="grid h-14 w-14 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xl font-bold text-[var(--color-primary)]">{{ strtoupper(substr($staff->name, 0, 1)) }}</span>
                @endif
                <div>
                    <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $staff->name }}</h1>
                    <p class="text-sm text-[var(--color-muted)]">
                        {{ $staff->designation?->name ?? 'Employee' }}@if ($staff->department?->name) · {{ $staff->department->name }}@endif
                        @if ($staff->employee_code) · <span class="font-semibold text-[var(--color-heading)]">{{ $staff->employee_code }}</span>@endif
                    </p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        @php $tone = ['active' => 'bg-emerald-50 text-emerald-700', 'inactive' => 'bg-gray-100 text-gray-600', 'blocked' => 'bg-red-50 text-red-600']; @endphp
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $tone[$staff->status] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst($staff->status) }}</span>
                        <span class="rounded-full bg-[var(--color-primary-soft)] px-2 py-0.5 text-[11px] font-semibold text-[var(--color-primary)]">{{ $staff->isAdmin() ? 'Administrator' : ($staff->assignedRole?->name ?? 'No role') }}</span>
                        <span class="text-xs text-gray-400">{{ $staff->last_seen_at ? 'Last seen '.$staff->last_seen_at->diffForHumans() : 'Never signed in' }}</span>
                    </div>
                </div>
            </div>

            @if ($canEdit)
                <a href="{{ route('admin.staff.edit', $staff) }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg> Edit
                </a>
            @endif
        </div>
    </div>

    {{-- Headline numbers — each tile opens the tab its number came from --}}
    <div class="mb-5 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['Tasks', $summary['open_tasks'], 'tasks', 'text-indigo-600 bg-indigo-50', 'M9 5h10M9 12h10M9 19h10M5 5h.01M5 12h.01M5 19h.01'],
            ['Projects', $summary['projects'], null, 'text-sky-600 bg-sky-50', 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z'],
            ['Hours', \App\Models\Attendance::minutesLabel($summary['hours_logged']), 'timesheet', 'text-emerald-600 bg-emerald-50', 'M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ['Tickets', $summary['tickets'], 'tickets', 'text-amber-600 bg-amber-50', 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7Z'],
            ['Late', $summary['late_this_month'], 'attendance', 'text-red-600 bg-red-50', 'M12 8v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ['Leaves', $summary['leaves_taken'], 'leaves', 'text-violet-600 bg-violet-50', 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z'],
        ] as [$label, $value, $goTab, $tone, $icon])
            @php $href = $goTab ? route('admin.staff.show', [$staff, 'tab' => $goTab]) : null; @endphp
            <{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif
               class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3.5 shadow-sm {{ $href ? 'transition hover:border-[var(--color-primary)] hover:shadow' : '' }}">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $tone }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-[11px] uppercase tracking-wide text-gray-400">{{ $label }}</span>
                    <span class="block text-lg font-extrabold leading-tight text-[var(--color-heading)]">{{ $value === 0 ? '0' : ($value ?: '—') }}</span>
                </span>
            </{{ $href ? 'a' : 'div' }}>
        @endforeach
    </div>

    {{-- Tabs. The strip scrolls on narrow screens, but the scrollbar itself would sit right
         under the labels and read as a second border, so it is hidden. --}}
    <style>#staff-tabs{scrollbar-width:none}#staff-tabs::-webkit-scrollbar{display:none}</style>
    <div id="staff-tabs" class="mb-5 flex gap-1 overflow-x-auto border-b border-gray-100">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.staff.show', [$staff, 'tab' => $key]) }}"
               class="shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $tab === $key ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @include('admin.staff.tabs.'.$tab)
@endsection
