@props(['status'])

@php
    $map = [
        'completed' => 'bg-emerald-50 text-emerald-700',
        'paid' => 'bg-blue-50 text-blue-700',
        'processing' => 'bg-blue-50 text-blue-700',
        'pending' => 'bg-amber-50 text-amber-700',
        'refunded' => 'bg-gray-100 text-gray-600',
        'cancelled' => 'bg-red-50 text-red-700',
        'published' => 'bg-emerald-50 text-emerald-700',
        'draft' => 'bg-gray-100 text-gray-600',
        'active' => 'bg-emerald-50 text-emerald-700',
    ];
    $cls = $map[strtolower($status)] ?? 'bg-gray-100 text-gray-600';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize $cls"]) }}>{{ $status }}</span>
