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

        {{-- Permission matrix --}}
        <div class="mt-6 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <div>
                    <p class="text-sm font-bold text-[var(--color-heading)]">Permissions</p>
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-[var(--color-muted)]">
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-400"></span>Owned <span class="text-gray-400">their records</span></span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-indigo-400"></span>Added <span class="text-gray-400">they created</span></span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-violet-400"></span>Both</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>All <span class="text-gray-400">everyone’s</span></span>
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="mr-1 text-xs text-[var(--color-muted)]">Set all</span>
                    <button type="button" data-bulk="all" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">All</button>
                    <button type="button" data-bulk="owned" class="rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">Owned</button>
                    <button type="button" data-bulk="none" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-500 hover:bg-gray-50">None</button>
                </div>
            </div>

            @foreach (Permissions::grouped() as $group => $modules)
                <div class="border-b border-gray-100 last:border-0">
                    <p class="bg-gray-50/70 px-5 py-2 text-[11px] font-bold uppercase tracking-wide text-gray-400">{{ $group }}</p>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-sm">
                            <colgroup>
                                <col class="w-[26%]">
                                @foreach ($crud as $l)<col class="w-[15%]">@endforeach
                                <col class="w-[14%]">
                            </colgroup>
                            <thead>
                                <tr class="text-left text-[11px] uppercase tracking-wide text-gray-400">
                                    <th class="px-5 py-2 font-semibold">Module</th>
                                    @foreach ($crud as $label)<th class="px-3 py-2 font-semibold">{{ $label }}</th>@endforeach
                                    <th class="px-3 py-2 text-right font-semibold">Sections</th>
                                </tr>
                            </thead>
                                @foreach ($modules as $mod => $cfg)
                                    @php $extras = Permissions::extraActions($mod); @endphp
                                    {{-- One <tbody> per module so the Alpine `more` scope wraps BOTH
                                         the CRUD row and its expandable extras row. Collapsed by default. --}}
                                    <tbody x-data="{ more: false }" class="border-t border-gray-50 align-middle">
                                    <tr class="transition hover:bg-gray-50/60">
                                        <td class="px-5 py-2.5 font-semibold text-[var(--color-heading)]">{{ $cfg['label'] }}</td>
                                        @foreach (array_keys($crud) as $act)
                                            <td class="px-3 py-2.5">
                                                @if (in_array($act, $cfg['actions'], true))
                                                    @php $key = "$mod.$act"; @endphp
                                                    <select name="permissions[{{ $key }}]" data-perm class="perm-select">
                                                        @foreach (Permissions::scopesFor($mod, $act) as $scope)
                                                            <option value="{{ $scope }}" @selected($scopeOf($key) === $scope)>{{ Permissions::scopeLabel($scope) }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="text-gray-200">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-3 py-2.5 text-right">
                                            @if ($extras)
                                                <button type="button" @click="more = !more"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-indigo-50"
                                                        :class="more && 'bg-indigo-50'">
                                                    <span x-text="more ? 'Hide' : '{{ count($extras) }}'"></span>
                                                    <span x-show="!more">section{{ count($extras) === 1 ? '' : 's' }}</span>
                                                    <svg class="h-3.5 w-3.5 transition" :class="more && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                                                </button>
                                            @else
                                                <span class="text-gray-200">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($extras)
                                        <tr x-show="more" x-cloak>
                                            <td colspan="6" class="px-5 pb-4 pt-0">
                                                <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4">
                                                    <p class="mb-3 text-[11px] font-bold uppercase tracking-wide text-gray-400">{{ $cfg['label'] }} · sections</p>
                                                    <div class="grid grid-cols-[repeat(auto-fill,minmax(200px,1fr))] gap-3">
                                                        @foreach ($extras as $act)
                                                            @php $key = "$mod.$act"; @endphp
                                                            <label class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 bg-white px-3 py-2">
                                                                <span class="text-xs font-semibold text-[var(--color-heading)]">{{ Permissions::actionLabel($act) }}</span>
                                                                <select name="permissions[{{ $key }}]" data-perm class="perm-select">
                                                                    @foreach (Permissions::scopesFor($mod, $act) as $scope)
                                                                        <option value="{{ $scope }}" @selected($scopeOf($key) === $scope)>{{ Permissions::scopeLabel($scope) }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                    </tbody>
                                @endforeach
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

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

    <style>
        /* Compact, consistent scope dropdowns — colour reflects the selected scope
           so the whole matrix reads at a glance. Painted on load/change via JS. */
        .perm-select {
            height: 2.15rem;
            width: 100%;
            max-width: 9.5rem;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            padding: 0 1.8rem 0 0.6rem;
            font-size: 0.75rem;
            font-weight: 600;
            background-color: #fff;
            color: #6b7280;
            transition: background-color .15s, color .15s, border-color .15s;
        }
        .perm-select:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 1px var(--color-primary); }
        .perm-select.s-owned { background-color: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .perm-select.s-added { background-color: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
        .perm-select.s-both  { background-color: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
        .perm-select.s-all   { background-color: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .perm-select.s-none  { color: #9ca3af; }
    </style>

    <script>
        (function () {
            const order = ['none', 'owned', 'added', 'both', 'all'];
            const paint = (sel) => {
                sel.classList.remove('s-none', 's-owned', 's-added', 's-both', 's-all');
                sel.classList.add('s-' + (sel.value || 'none'));
            };
            const selects = document.querySelectorAll('select[data-perm]');
            selects.forEach((sel) => { paint(sel); sel.addEventListener('change', () => paint(sel)); });

            document.querySelectorAll('[data-bulk]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const want = btn.dataset.bulk;
                    selects.forEach((sel) => {
                        const opts = Array.from(sel.options).map((o) => o.value);
                        let choice = opts.includes(want) ? want : null;
                        if (!choice) {
                            const wi = order.indexOf(want);
                            for (let i = wi; i >= 0; i--) { if (opts.includes(order[i])) { choice = order[i]; break; } }
                            if (!choice) choice = 'none';
                        }
                        sel.value = choice;
                        paint(sel);
                    });
                });
            });
        })();
    </script>
@endsection
