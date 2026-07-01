@props(['label' => null, 'name', 'type' => 'text', 'value' => null, 'options' => null, 'rows' => 3, 'required' => false, 'hint' => null, 'placeholder' => null])

@php
    $val = old($name, $value);
    $base = 'w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]';
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</label>
    @endif

    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" @if($required) required @endif placeholder="{{ $placeholder }}" class="{{ $base }} py-2.5">{{ $val }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $name }}" name="{{ $name }}" @if($required) required @endif class="{{ $base }} h-11 bg-white">
            @foreach ($options as $k => $label)
                <option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $label }}</option>
            @endforeach
        </select>
    @elseif ($type === 'checkbox')
        <label class="flex items-center gap-2 text-sm text-[var(--color-muted)]">
            <input type="hidden" name="{{ $name }}" value="0">
            <input type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1" @checked($val) class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]">
            {{ $label }}
        </label>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $val }}" @if($required) required @endif placeholder="{{ $placeholder }}" class="{{ $base }} h-11">
    @endif

    @if ($hint)<p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
