@extends('admin.layouts.app')
@section('title', $staff->exists ? 'Edit Staff' : 'Add Staff')

@section('content')
    <a href="{{ route('admin.staff.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to staff
    </a>

    <form method="POST" action="{{ $staff->exists ? route('admin.staff.update', $staff) : route('admin.staff.store') }}" enctype="multipart/form-data" class="max-w-2xl">
        @csrf
        @if ($staff->exists) @method('PUT') @endif

        <div class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">{{ $staff->exists ? 'Edit Staff Member' : 'New Staff Member' }}</h2>

            {{-- Profile photo --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Profile Photo</label>
                <div class="flex items-center gap-4">
                    @if ($staff->photo_url)
                        <img src="{{ $staff->photo_url }}" alt="{{ $staff->name }}" class="h-16 w-16 rounded-full object-cover">
                    @else
                        <span class="grid h-16 w-16 place-items-center rounded-full bg-[var(--color-primary-soft)] text-lg font-bold text-[var(--color-primary)]">{{ strtoupper(substr($staff->name ?: '?', 0, 1)) }}</span>
                    @endif
                    <input type="file" name="photo" accept="image/*" class="text-sm text-[var(--color-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                </div>
                <p class="mt-1 text-xs text-[var(--color-muted)]">JPG/PNG, max 5MB.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field label="Full Name" name="name" :value="$staff->name" required placeholder="Enter full name" />
                <x-admin.field label="Email" name="email" type="email" :value="$staff->email" required placeholder="staff@razinsoft.com" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field label="Phone" name="phone" :value="$staff->phone" placeholder="Optional" />
                <x-admin.field label="Job Title" name="job_title" :value="$staff->job_title" placeholder="e.g. Sales Executive" />
            </div>
            <x-admin.field label="Password" name="password" type="password" :required="!$staff->exists" :hint="$staff->exists ? 'Leave blank to keep the current password.' : 'Min 8 characters — staff use this to log in.'" />
        </div>

        @if ($errors->any())
            <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $staff->exists ? 'Save changes' : 'Add staff' }}</button>
            <a href="{{ route('admin.staff.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
@endsection
