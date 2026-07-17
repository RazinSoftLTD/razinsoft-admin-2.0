@props([
    'name',
    'options' => [],          // collection/array of ['id' => .., 'label' => ..]
    'selected' => null,
    'placeholder' => 'Search…',
    'allowClear' => true,
    'clearLabel' => 'None',
    'required' => false,
])

@php
    $json = collect($options)->map(fn ($o) => ['id' => $o['id'], 'label' => $o['label']])->values();
    $sel = old($name, $selected);
@endphp

<div class="relative" x-data="{
        open: false, q: '',
        sel: {{ $sel !== null && $sel !== '' ? (int) $sel : 'null' }},
        options: {{ Illuminate\Support\Js::from($json) }},
        get current() { return this.options.find(o => o.id === this.sel); },
        get filtered() { const q = this.q.trim().toLowerCase(); return q ? this.options.filter(o => o.label.toLowerCase().includes(q)) : this.options; },
        pick(o) { this.sel = o ? o.id : null; this.open = false; this.q = ''; }
     }" @click.outside="open = false">
    <input type="hidden" name="{{ $name }}" :value="sel ?? ''" @if($required) x-bind:required="!sel" @endif>
    <button type="button" @click="open = !open" class="flex h-11 w-full items-center justify-between rounded-lg border border-gray-200 bg-white px-3 text-left text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
        <span :class="!current && 'text-gray-400'" x-text="current ? current.label : '{{ $placeholder }}'"></span>
        <svg class="h-4 w-4 text-gray-400 transition" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
    </button>
    <div x-show="open" x-cloak class="absolute z-40 mt-1 w-full overflow-hidden rounded-lg border border-gray-100 bg-white shadow-lg">
        <div class="p-2"><input x-model="q" @click.stop type="text" placeholder="{{ $placeholder }}" class="h-9 w-full rounded-lg border border-gray-200 px-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none"></div>
        <div class="max-h-56 overflow-y-auto pb-1">
            @if ($allowClear)
                <button type="button" @click="pick(null)" class="flex w-full items-center px-3 py-1.5 text-left text-sm text-gray-400 hover:bg-gray-50">{{ $clearLabel }}</button>
            @endif
            <template x-for="o in filtered" :key="o.id">
                <button type="button" @click="pick(o)" class="flex w-full items-center px-3 py-1.5 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50" :class="o.id === sel && 'bg-[var(--color-primary-soft)]'" x-text="o.label"></button>
            </template>
            <p x-show="!filtered.length" class="px-3 py-2 text-sm text-gray-400">No match found.</p>
        </div>
    </div>
</div>
