@props(['action', 'title' => 'Add', 'enctype' => null])

<div x-data="{ open: false }" class="rounded-xl border border-dashed border-gray-200 bg-white p-4 shadow-sm">
    <button type="button" @click="open = !open" class="flex w-full items-center gap-2 text-sm font-semibold text-[var(--color-primary)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" :class="open && 'rotate-45'"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
        {{ $title }}
    </button>
    <form method="POST" action="{{ $action }}" @if($enctype) enctype="{{ $enctype }}" @endif x-show="open" x-cloak class="mt-4 space-y-4 border-t border-gray-100 pt-4">
        @csrf
        {{ $slot }}
        <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
    </form>
</div>
