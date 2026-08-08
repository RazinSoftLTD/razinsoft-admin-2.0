@extends('admin.layouts.app')
@section('title', $role->exists ? 'Edit Role' : 'New Role')

@php
    use App\Support\Permissions;
    $current = old('permissions', $role->exists ? $role->permissionMap() : Permissions::DEFAULTS);
    $scopeOf = fn ($key) => $current[$key] ?? 'none';
    $crud = ['view' => 'View', 'create' => 'Add', 'edit' => 'Update', 'delete' => 'Delete'];
@endphp

@section('content')
    <a href="{{ route('admin.roles.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Roles
    </a>

    <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
        @csrf
        @if ($role->exists) @method('PUT') @endif

        <div class="max-w-3xl rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Role name <span class="text-red-500">*</span></label>
                    <input name="name" value="{{ old('name', $role->name) }}" required placeholder="e.g. Sales Executive" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Description</label>
                    <input name="description" value="{{ old('description', $role->description) }}" placeholder="Optional" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>
            </div>
        </div>

        @include('admin.partials.permission-matrix', [
            'mode' => 'role',
            'current' => $current,
            'field' => 'permissions',
        ])

        @if ($errors->any())
            <div class="mt-5 max-w-3xl rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $role->exists ? 'Save role' : 'Create role' }}</button>
            <a href="{{ route('admin.roles.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>

@endsection
