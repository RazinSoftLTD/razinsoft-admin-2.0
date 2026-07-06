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
                <p class="mt-1 text-xs text-[var(--color-muted)]">{{ \App\Support\ImageSpecs::hint('avatar') }} JPG/PNG, max 5MB.</p>
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

            {{-- Role — the base permission set --}}
            @php $override = old('override', (array) ($staff->permissions ?? [])); @endphp
            <div class="border-t border-gray-100 pt-5">
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Role</label>
                <select name="role_id" class="h-11 w-full max-w-sm rounded-lg border border-gray-200 px-3 text-sm">
                    <option value="">— No role —</option>
                    @foreach ($roles as $r)<option value="{{ $r->id }}" @selected((string) old('role_id', $staff->role_id) === (string) $r->id)>{{ $r->name }}</option>@endforeach
                </select>
                <p class="mt-1 text-xs text-[var(--color-muted)]">The role gives the base permissions. Manage roles from <a href="{{ route('admin.roles.index') }}" class="font-semibold text-[var(--color-primary)] hover:underline">Roles &amp; Permissions</a>.</p>
            </div>

            {{-- Per-user override (optional) — Inherit / Allow / Deny on top of the role --}}
            <div x-data="{ open: {{ count($override) ? 'true' : 'false' }} }" class="border-t border-gray-100 pt-5">
                <button type="button" @click="open=!open" class="flex items-center gap-2 text-sm font-semibold text-[var(--color-heading)]">
                    <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                    Custom access (override this role)
                </button>
                <p class="mt-1 text-xs text-[var(--color-muted)]">Leave everything on <strong>Inherit</strong> to just use the role. Set <strong>Allow</strong> / <strong>Deny</strong> to override a specific permission for this person only.</p>
                <div x-show="open" x-cloak class="mt-4 space-y-5">
                    @foreach (\App\Support\Permissions::grouped() as $group => $modules)
                        <div>
                            <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-400">{{ $group }}</p>
                            <div class="divide-y divide-gray-50 rounded-lg border border-gray-100">
                                @foreach ($modules as $mod => $cfg)
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2.5">
                                        <span class="w-24 shrink-0 text-sm font-semibold text-[var(--color-heading)]">{{ $cfg['label'] }}</span>
                                        @foreach (\App\Support\Permissions::actionsFor($mod) as $act)
                                            @php $key = "$mod.$act"; $cur = array_key_exists($key, $override) ? ($override[$key] ? '1' : '0') : ''; @endphp
                                            <label class="inline-flex items-center gap-1.5 text-xs text-[var(--color-muted)]">
                                                <span class="{{ $act === 'view_all' || $act === 'finance' ? 'font-semibold text-[var(--color-primary)]' : '' }}">{{ \App\Support\Permissions::actionLabel($act) }}</span>
                                                <select name="override[{{ $key }}]" class="h-8 rounded border-gray-200 text-xs">
                                                    <option value="" @selected($cur==='')>Inherit</option>
                                                    <option value="1" @selected($cur==='1')>Allow</option>
                                                    <option value="0" @selected($cur==='0')>Deny</option>
                                                </select>
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
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
