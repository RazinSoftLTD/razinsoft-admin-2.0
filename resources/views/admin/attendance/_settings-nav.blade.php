{{-- Settings > HR Settings tabs. --}}
@php
    $tabs = [
        ['label' => 'Attendance Settings', 'route' => 'admin.attendance.settings'],
        ['label' => 'Biometric Devices', 'route' => 'admin.attendance.devices'],
    ];
@endphp

<div class="mb-5 flex items-center justify-between gap-3 border-b border-gray-100">
    <div class="flex gap-1 overflow-x-auto">
        @foreach ($tabs as $tab)
            @php $on = request()->routeIs($tab['route']); @endphp
            <a href="{{ route($tab['route']) }}"
               class="shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $on ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
    <a href="{{ route('admin.attendance.index') }}" class="mb-1.5 inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg>
        Back to Attendance
    </a>
</div>
