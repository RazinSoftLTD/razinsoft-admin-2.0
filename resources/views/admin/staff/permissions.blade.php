@extends('admin.layouts.app')
@section('title', 'Permissions — '.$staff->name)

@php
    use App\Support\Permissions;

    // '' (or a missing key) means inherit — this person follows whatever the role says.
    $stored = old('override', (array) ($staff->permissions ?? []));
    $current = [];
    foreach ($stored as $key => $value) {
        $current[$key] = $value === '' || $value === null ? '' : Permissions::scopeValue($value);
    }
    $roleFor = fn ($key) => optional($staff->assignedRole)->grantedScope($key) ?? 'none';
@endphp

@section('content')
    <a href="{{ route('admin.staff.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Staff
    </a>

    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Permissions — {{ $staff->name }}</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">
            Role: <span class="font-semibold text-[var(--color-heading)]">{{ $staff->assignedRole?->name ?? 'No role' }}</span>.
            Each permission follows the role unless you change it here for this person only.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.staff.permissions.update', $staff) }}">
        @csrf @method('PUT')

        @include('admin.partials.permission-matrix', [
            'mode' => 'staff',
            'current' => $current,
            'roleFor' => $roleFor,
            'field' => 'override',
        ])

        @if ($errors->any())
            <div class="mt-5 max-w-3xl rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save permissions</button>
            <a href="{{ route('admin.staff.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
@endsection
