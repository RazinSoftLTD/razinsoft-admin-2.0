{{-- Method chip shown next to every check-in / check-out. --}}
@php
    $tone = [
        'biometric' => 'bg-indigo-50 text-indigo-700',
        'web'       => 'bg-emerald-50 text-emerald-700',
        'web_login' => 'bg-sky-50 text-sky-700',
        'mobile'    => 'bg-amber-50 text-amber-700',
        'manual'    => 'bg-gray-100 text-gray-600',
    ][$method ?? ''] ?? 'bg-gray-100 text-gray-400';
@endphp
@if ($method)
    <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $tone }}">{{ \App\Models\Attendance::METHODS[$method] ?? $method }}</span>
@endif
