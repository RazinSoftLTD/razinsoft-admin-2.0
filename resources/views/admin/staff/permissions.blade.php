@extends('admin.layouts.app')
@section('title', 'Permissions — '.$staff->name)

@php $override = old('override', (array) ($staff->permissions ?? [])); @endphp

@section('content')
    <a href="{{ route('admin.staff.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Staff
    </a>

    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Permissions — {{ $staff->name }}</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">
            Role: <span class="font-semibold text-[var(--color-heading)]">{{ $staff->assignedRole?->name ?? 'No role' }}</span>.
            Each permission follows the role unless you <strong>Allow</strong> or <strong>Deny</strong> it here for this person only.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.staff.permissions.update', $staff) }}" class="max-w-4xl">
        @csrf @method('PUT')

        @php use App\Support\Permissions; @endphp
        <div class="space-y-6">
            @foreach (Permissions::grouped() as $group => $modules)
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-wide text-gray-400">{{ $group }}</p>
                    <div class="divide-y divide-gray-50">
                        @foreach ($modules as $mod => $cfg)
                            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 py-3">
                                <span class="w-28 shrink-0 text-sm font-semibold text-[var(--color-heading)]">{{ $cfg['label'] }}</span>
                                @foreach ($cfg['actions'] as $act)
                                    @php
                                        $key = "$mod.$act";
                                        $isCrud = in_array($act, ['view', 'create', 'edit', 'delete'], true);
                                        $scopes = Permissions::scopesFor($mod, $act);
                                        $cur = array_key_exists($key, $override) ? Permissions::scopeValue($override[$key]) : '';
                                        $roleScope = optional($staff->assignedRole)->grantedScope($key) ?? 'none';
                                    @endphp
                                    <label class="inline-flex items-center gap-1.5 text-xs text-[var(--color-muted)]">
                                        <span class="{{ ! $isCrud ? 'font-semibold text-[var(--color-primary)]' : '' }}">{{ Permissions::actionLabel($act) }}</span>
                                        <select name="override[{{ $key }}]" title="{{ $cur !== '' ? Permissions::optionHelp($mod, $act, $cur) : 'Follows the role setting.' }}" class="h-8 rounded border-gray-200 text-xs {{ $cur !== '' ? 'bg-indigo-50 text-indigo-700' : '' }}">
                                            <option value="" @selected($cur === '')>Inherit ({{ Permissions::optionLabel($mod, $act, $roleScope) }})</option>
                                            @foreach ($scopes as $scope)
                                                <option value="{{ $scope }}" @selected($cur === $scope)>{{ Permissions::optionLabel($mod, $act, $scope) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save permissions</button>
            <a href="{{ route('admin.staff.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
@endsection
