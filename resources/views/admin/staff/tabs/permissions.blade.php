{{-- Summary of what this employee can do; editing happens on the dedicated screen. --}}
@php
    $scope = fn ($module, $action) => $staff->permissionScope($module, $action);
    $modules = \App\Support\Permissions::MODULES;
    $granted = collect($modules)->map(function ($def, $key) use ($staff) {
        $on = collect($def['actions'])->filter(fn ($a) => $staff->permissionScope($key, $a) !== 'none');
        return ['label' => $def['label'], 'group' => $def['group'] ?? 'Other', 'actions' => $on->values()->all(), 'total' => count($def['actions'])];
    })->filter(fn ($m) => count($m['actions']))->groupBy('group');
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
    <div>
        <p class="text-sm font-bold text-[var(--color-heading)]">Role: {{ $staff->assignedRole?->name ?? 'No role assigned' }}</p>
        <p class="text-xs text-[var(--color-muted)]">
            {{ $staff->isAdmin() ? 'Admins hold every permission.' : 'Role grants plus any per-user overrides.' }}
        </p>
    </div>
    <a href="{{ route('admin.staff.permissions', $staff) }}" class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Edit permissions</a>
</div>

@if ($staff->isAdmin())
    <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-4 text-sm text-indigo-700">
        This user is an <strong>administrator</strong> — every module and action is available to them.
    </div>
@elseif ($granted->isEmpty())
    <div class="rounded-xl border border-dashed border-gray-200 py-12 text-center">
        <p class="text-sm text-gray-400">No permissions granted yet.</p>
    </div>
@else
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($granted as $group => $mods)
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">{{ $group }}</h3>
                <div class="space-y-2.5">
                    @foreach ($mods as $m)
                        <div>
                            <p class="text-sm font-semibold text-[var(--color-heading)]">{{ $m['label'] }} <span class="text-xs font-normal text-gray-400">{{ count($m['actions']) }}/{{ $m['total'] }}</span></p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ($m['actions'] as $a)
                                    <span class="rounded bg-[var(--color-primary-soft)] px-1.5 py-0.5 text-[10px] font-semibold text-[var(--color-primary)]">{{ ucfirst(str_replace('_', ' ', $a)) }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
