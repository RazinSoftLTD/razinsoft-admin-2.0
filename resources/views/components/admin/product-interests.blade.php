@props([
    'selected' => [],                     // array of product_category ids already chosen
    'name' => 'interest_ids',
    'label' => 'Interested in',
    'hint' => null,
])

@php
    $options = \App\Models\ProductCategory::pickerOptions();
    $chosen = collect($selected)->map(fn ($v) => (int) $v)->all();
    $byId = collect($options)->keyBy('id');
@endphp

{{-- Multi-select over the shared Product Category tree: tick a category for the whole thing, or
     one of its sub-categories to be specific. Picks show as labels under the field. --}}
<div x-data="{
        open: false,
        sel: @js($chosen),
        options: @js($options),
        labelFor(id) {
            const o = this.options.find(o => o.id === id);
            return o ? o.label : '';
        },
        remove(id) { this.sel = this.sel.filter(v => v !== id); },
     }"
     @click.outside="open = false" @keydown.escape="open = false"
     class="relative">
    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">{{ $label }}</label>

    <button type="button" @click="open = !open"
            class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-left text-sm hover:border-gray-200 focus:border-[var(--color-primary)] focus:outline-none">
        <span class="truncate" :class="sel.length ? 'text-[var(--color-heading)]' : 'text-gray-400'"
              x-text="sel.length ? sel.length + ' selected' : 'Select categories…'"></span>
        <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
    </button>

    <div x-show="open" x-cloak
         class="absolute left-0 right-0 z-40 mt-1 max-h-64 overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-xl ring-1 ring-black/5">
        @forelse ($options as $opt)
            <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 text-sm hover:bg-gray-50 {{ $opt['is_sub'] ? 'pl-8' : '' }}">
                <input type="checkbox" name="{{ $name }}[]" value="{{ $opt['id'] }}" x-model.number="sel"
                       class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]">
                <span class="{{ $opt['is_sub'] ? 'text-[var(--color-muted)]' : 'font-semibold text-[var(--color-heading)]' }}">{{ $opt['label'] }}</span>
            </label>
        @empty
            <p class="px-3 py-2 text-sm text-gray-400">None yet — add them in Settings &rsaquo; CRM Settings &rsaquo; Product Categories.</p>
        @endforelse
    </div>

    {{-- Chosen ones, as removable labels. --}}
    <div x-show="sel.length" x-cloak class="mt-2 flex flex-wrap gap-1.5">
        <template x-for="id in sel" :key="id">
            <span class="inline-flex items-center gap-1 rounded-full bg-[var(--color-primary-soft)] py-1 pl-2.5 pr-1 text-xs font-semibold text-[var(--color-primary)]">
                <span x-text="labelFor(id)"></span>
                <button type="button" @click="remove(id)" title="Remove" class="grid h-4 w-4 place-items-center rounded-full hover:bg-white">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </span>
        </template>
    </div>

    {{-- Submitting with nothing ticked must clear the set, not be read as "field absent". --}}
    <input type="hidden" name="{{ $name }}_sync" value="1">

    @if ($hint)<p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>@endif
</div>
