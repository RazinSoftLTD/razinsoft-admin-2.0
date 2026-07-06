@extends('admin.layouts.app')
@section('title', $role->exists ? 'Edit Role' : 'New Role')

@section('content')
    <a href="{{ route('admin.roles.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Roles
    </a>

    <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="max-w-4xl">
        @csrf
        @if ($role->exists) @method('PUT') @endif

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
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

            @php $granted = old('permissions', $role->permissions ?? []); @endphp
            <div class="mt-6 border-t border-gray-100 pt-5">
                <p class="text-sm font-semibold text-[var(--color-heading)]">Permissions</p>
                <p class="mb-4 text-xs text-[var(--color-muted)]">Tick what this role can do. <strong>View all</strong> (on Invoices/Leads/Deals) lets them see everyone’s records — without it they only see their own.</p>

                <div class="space-y-6">
                    @foreach (\App\Support\Permissions::grouped() as $group => $modules)
                        <div>
                            <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-400">{{ $group }}</p>
                            <div class="divide-y divide-gray-50 rounded-lg border border-gray-100">
                                @foreach ($modules as $mod => $cfg)
                                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 px-4 py-3">
                                        <span class="w-28 shrink-0 text-sm font-semibold text-[var(--color-heading)]">{{ $cfg['label'] }}</span>
                                        @foreach (\App\Support\Permissions::actionsFor($mod) as $act)
                                            @php $key = "$mod.$act"; @endphp
                                            <label class="inline-flex items-center gap-1.5 text-sm text-[var(--color-heading)]">
                                                <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, (array) $granted, true))
                                                       class="h-4 w-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                                <span class="{{ $act === 'view_all' || $act === 'finance' ? 'font-semibold text-[var(--color-primary)]' : '' }}">{{ \App\Support\Permissions::actionLabel($act) }}</span>
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
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $role->exists ? 'Save role' : 'Create role' }}</button>
            <a href="{{ route('admin.roles.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
@endsection
