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
                    <p class="text-xs text-gray-400">{{ $staff->last_seen_at ? 'Last seen '.$staff->last_seen_at->diffForHumans() : 'Never signed in' }}</p>
                </div>
            </div>

            @if ($canEdit)
                <a href="{{ route('admin.staff.edit', $staff) }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg> Edit
                </a>
            @endif
        </div>
    </div>

    {{-- Headline numbers — each one opens the tab it came from --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach ([
            ['Open Tasks', $summary['open_tasks'], 'tasks'],
            ['Projects', $summary['projects'], null],
            ['Hours Logged', \App\Models\Attendance::minutesLabel($summary['hours_logged']), 'timesheet'],
            ['Tickets', $summary['tickets'], 'tickets'],
            ['Late (month)', $summary['late_this_month'], 'attendance'],
            ['Leaves Taken', $summary['leaves_taken'], 'leaves'],
        ] as [$label, $value, $goTab])
            @php $href = $goTab ? route('admin.staff.show', [$staff, 'tab' => $goTab]) : null; @endphp
            @if ($href)
                <a href="{{ $href }}" class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition hover:border-[var(--color-primary)]">
                    <p class="text-xs uppercase tracking-wide text-gray-400">{{ $label }}</p>
                    <p class="mt-1 text-xl font-extrabold text-[var(--color-heading)]">{{ $value === 0 ? '0' : ($value ?: '—') }}</p>
                </a>
            @else
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-400">{{ $label }}</p>
                    <p class="mt-1 text-xl font-extrabold text-[var(--color-heading)]">{{ $value === 0 ? '0' : ($value ?: '—') }}</p>
                </div>
            @endif
        @endforeach
    </div>

    {{-- Tabs --}}
    <div class="mb-5 flex gap-1 overflow-x-auto border-b border-gray-100">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.staff.show', [$staff, 'tab' => $key]) }}"
               class="shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $tab === $key ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @include('admin.staff.tabs.'.$tab)
@endsection
