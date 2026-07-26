@props(['model', 'empty' => null, 'size' => 'sm'])

@php $labels = $model->relationLoaded('interests') || $model->exists ? $model->interestLabels() : []; @endphp

@if (count($labels))
    <span class="flex flex-wrap gap-1">
        @foreach ($labels as $label)
            <span class="inline-block rounded-full bg-[var(--color-primary-soft)] px-2 py-0.5 font-semibold text-[var(--color-primary)] {{ $size === 'sm' ? 'text-[10px]' : 'text-xs' }}">{{ $label }}</span>
        @endforeach
    </span>
@elseif ($empty)
    <span class="text-xs text-gray-400">{{ $empty }}</span>
@endif
