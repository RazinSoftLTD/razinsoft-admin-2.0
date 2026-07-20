@props([
    'name',
    'checked' => false,
    'label' => null,
    'hint' => null,
    'value' => 1,
])

{{-- On/off switch. Plain CSS because peer-checked:*, translate-x-* and left-0.5
     are not in the compiled stylesheet, which left every switch looking "off". --}}
<label class="flex cursor-pointer items-start justify-between gap-4 rounded-lg border border-gray-200 p-4">
    <span class="min-w-0">
        @if ($label)<span class="block text-sm font-semibold text-[var(--color-heading)]">{{ $label }}</span>@endif
        @if ($hint)<span class="mt-0.5 block text-xs text-[var(--color-muted)]">{{ $hint }}</span>@endif
        {{ $slot }}
    </span>

    <span class="admin-switch shrink-0">
        <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked) {{ $attributes }}>
        <span class="admin-switch__track"><span class="admin-switch__knob"></span></span>
    </span>
</label>

@once
    <style>
        .admin-switch { position: relative; display: inline-flex; align-items: center; }
        .admin-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
        .admin-switch__track {
            display: block; width: 44px; height: 24px; border-radius: 9999px;
            background: #e5e7eb; transition: background-color .2s ease;
        }
        .admin-switch__knob {
            display: block; width: 20px; height: 20px; margin: 2px; border-radius: 9999px;
            background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: transform .2s ease;
        }
        .admin-switch input:checked + .admin-switch__track { background: var(--color-primary); }
        .admin-switch input:checked + .admin-switch__track .admin-switch__knob { transform: translateX(20px); }
        .admin-switch input:focus-visible + .admin-switch__track { outline: 2px solid var(--color-primary); outline-offset: 2px; }
    </style>
@endonce
