@extends('admin.layouts.app')
@section('title', $staff->name)

@php
    $statusTone = [
        'active' => 'bg-emerald-50 text-emerald-700',
        'inactive' => 'bg-gray-100 text-gray-600',
        'blocked' => 'bg-red-50 text-red-600',
    ];
    $rows = [
        ['Employee Code', $staff->employee_code],
        ['Email', $staff->email],
        ['Phone', trim(($staff->dial_code ? $staff->dial_code.' ' : '').$staff->phone) ?: null],
        ['Designation', $staff->designation?->name],
        ['Department', $staff->department?->name],
        ['Reports To', $staff->reportsTo?->name],
        ['Employment Type', $staff->employment_type ? ucfirst(str_replace('_', ' ', $staff->employment_type)) : null],
        ['Joining Date', $staff->joining_date?->format('d M Y')],
        ['Date of Birth', $staff->date_of_birth?->format('d M Y')],
        ['Country', $staff->country],
        ['Address', $staff->address],
    ];
@endphp

@section('content')
    <a href="{{ route('admin.staff.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Employees
    </a>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Profile card --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 text-center shadow-sm">
            @if ($staff->photo_url)
                <img src="{{ $staff->photo_url }}" alt="" class="mx-auto h-24 w-24 rounded-full object-cover ring-4 ring-gray-50">
            @else
                <span class="mx-auto grid h-24 w-24 place-items-center rounded-full bg-[var(--color-primary-soft)] text-2xl font-bold text-[var(--color-primary)]">{{ strtoupper(substr($staff->name, 0, 1)) }}</span>
            @endif
            <h1 class="mt-4 text-lg font-bold text-[var(--color-heading)]">{{ $staff->salutation ? $staff->salutation.' ' : '' }}{{ $staff->name }}</h1>
            <p class="text-sm text-[var(--color-muted)]">{{ $staff->designation?->name ?? 'Employee' }}</p>
            <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone[$staff->status] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst($staff->status) }}</span>
                @if (auth()->user()->isAdmin())
                    <span class="inline-flex rounded-full bg-[var(--color-primary-soft)] px-2.5 py-1 text-xs font-semibold text-[var(--color-primary)]">{{ $staff->assignedRole?->name ?? 'No role' }}</span>
                @endif
            </div>
            @if ($canEdit)
                <a href="{{ route('admin.staff.edit', $staff) }}" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg> Edit
                </a>
            @endif
        </div>

        {{-- Details --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Employee Information</h2>
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    @foreach ($rows as [$label, $value])
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400">{{ $label }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-[var(--color-heading)]">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($staff->about)
                    <div class="mt-5 border-t border-gray-100 pt-4">
                        <dt class="text-xs uppercase tracking-wide text-gray-400">About</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-[var(--color-muted)]">{{ $staff->about }}</dd>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
