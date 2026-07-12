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
        <div class="mt-6 rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4">
                <div>
                    <p class="text-sm font-bold text-[var(--color-heading)]">Permissions</p>
                    <p class="text-xs text-[var(--color-muted)]">Pick a scope per action — <strong>Owned</strong> = their records, <strong>Added</strong> = records they created, <strong>All</strong> = everyone’s.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-[var(--color-muted)]">Set all:</span>
                    <button type="button" data-bulk="all" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">All</button>
                    <button type="button" data-bulk="owned" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">Owned</button>
                    <button type="button" data-bulk="none" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-500 hover:bg-gray-50">None</button>
                </div>
            </div>

            @foreach (Permissions::grouped() as $group => $modules)
                <div class="border-b border-gray-100 last:border-0">
                    <p class="bg-gray-50/70 px-5 py-2 text-[11px] font-bold uppercase tracking-wide text-gray-400">{{ $group }}</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[11px] uppercase tracking-wide text-gray-400">
                                    <th class="px-5 py-2 font-semibold">Module</th>
                                    @foreach ($crud as $label)<th class="px-3 py-2 font-semibold">{{ $label }}</th>@endforeach
                                    <th class="px-3 py-2 font-semibold">More</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($modules as $mod => $cfg)
                                    @php $extras = Permissions::extraActions($mod); @endphp
                                    <tr x-data="{ more: false }">
                                        <td class="px-5 py-2.5 font-semibold text-[var(--color-heading)]">{{ $cfg['label'] }}</td>
                                        @foreach (array_keys($crud) as $act)
                                            <td class="px-3 py-2.5">
                                                @if (in_array($act, $cfg['actions'], true))
                                                    @php $key = "$mod.$act"; @endphp
                                                    <select name="permissions[{{ $key }}]" data-perm class="h-9 rounded-lg border-gray-200 text-xs font-medium focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                                        @foreach (Permissions::scopesFor($mod, $act) as $scope)
                                                            <option value="{{ $scope }}" @selected($scopeOf($key) === $scope)>{{ Permissions::scopeLabel($scope) }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-3 py-2.5">
                                            @if ($extras)
                                                <button type="button" @click="more = !more" class="inline-flex items-center gap-1 text-xs font-semibold text-[var(--color-primary)]">
                                                    More <svg class="h-3.5 w-3.5 transition" :class="more && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                                                </button>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($extras)
                                        <tr x-show="more" x-cloak>
                                            <td></td>
                                            <td colspan="5" class="px-3 pb-3">
                                                <div class="flex flex-wrap gap-4 rounded-lg bg-gray-50 p-3">
                                                    @foreach ($extras as $act)
                                                        @php $key = "$mod.$act"; @endphp
                                                        <label class="flex items-center gap-2 text-xs">
                                                            <span class="font-semibold text-[var(--color-heading)]">{{ Permissions::actionLabel($act) }}</span>
                                                            <select name="permissions[{{ $key }}]" data-perm class="h-8 rounded-lg border-gray-200 text-xs">
                                                                <option value="none" @selected($scopeOf($key) === 'none')>None</option>
                                                                <option value="all" @selected($scopeOf($key) === 'all')>All</option>
                                                            </select>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
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

    <script>
        (function () {
            const order = ['none', 'owned', 'added', 'both', 'all'];
            document.querySelectorAll('[data-bulk]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const want = btn.dataset.bulk;
                    document.querySelectorAll('select[data-perm]').forEach((sel) => {
                        const opts = Array.from(sel.options).map((o) => o.value);
                        let choice = opts.includes(want) ? want : null;
                        if (!choice) {
                            const wi = order.indexOf(want);
                            for (let i = wi; i >= 0; i--) { if (opts.includes(order[i])) { choice = order[i]; break; } }
                            if (!choice) choice = 'none';
                        }
                        sel.value = choice;
                    });
                });
            });
        })();
    </script>
@endsection
