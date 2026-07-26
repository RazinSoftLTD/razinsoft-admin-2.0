@php
    $canSettings = auth()->user()->allows('attendance', 'settings');
    $tabs = [['label' => 'Today', 'route' => 'admin.attendance.index'], ['label' => 'History', 'route' => 'admin.attendance.history']];
    if ($canSettings) {
        $tabs[] = ['label' => 'Devices', 'route' => 'admin.attendance.devices'];
        $tabs[] = ['label' => 'Settings', 'route' => 'admin.attendance.settings'];
    }
@endphp

<div class="mb-5 flex gap-1 overflow-x-auto border-b border-gray-100">
    @foreach ($tabs as $tab)
        @php $on = request()->routeIs($tab['route']); @endphp
        <a href="{{ route($tab['route']) }}"
           class="shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $on ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
