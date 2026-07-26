{{-- Attendance's own tabs. The Devices/Settings screens live under Settings > HR Settings. --}}
@php
    $tabs = [
        ['label' => 'Today', 'route' => 'admin.attendance.index'],
        ['label' => 'History', 'route' => 'admin.attendance.history'],
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
    @if (auth()->user()->allows('attendance', 'settings'))
        <a href="{{ route('admin.attendance.settings') }}" class="mb-1.5 inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 13a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 0 1-4 0v-.2a1.7 1.7 0 0 0-2.9-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0-1.1-2.9H3a2 2 0 0 1 0-4h.2a1.7 1.7 0 0 0 1.1-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 2.9-1.1V3a2 2 0 0 1 4 0v.2a1.7 1.7 0 0 0 2.9 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9Z"/></svg>
            HR Settings
        </a>
    @endif
</div>
