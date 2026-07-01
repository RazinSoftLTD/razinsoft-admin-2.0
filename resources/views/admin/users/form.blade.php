@extends('admin.layouts.app')
@section('title', $user->exists ? 'Edit User' : 'New User')

@section('content')
    <a href="{{ route('admin.users.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to users
    </a>

    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="max-w-2xl">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <div class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <x-admin.field label="Full name" name="name" :value="$user->name" required />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field label="Email" name="email" type="email" :value="$user->email" required />
                <x-admin.field label="Phone" name="phone" :value="$user->phone" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field label="Role" name="role" type="select" :value="$user->role" :options="['customer' => 'Customer', 'admin' => 'Admin']" required />
                <x-admin.field label="Password" name="password" type="password" :required="!$user->exists" :hint="$user->exists ? 'Leave blank to keep current.' : 'Min 8 characters.'" />
            </div>
        </div>

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $user->exists ? 'Save changes' : 'Create user' }}</button>
            <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
@endsection
