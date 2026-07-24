{{--
    Smooth multi-select as a checkbox dropdown. Check/uncheck several, then it saves
    once when you click away or close it (one request, not one per toggle).

    Params:
      $action      form action URL (PATCH)
      $name        array field name, e.g. 'group_ids' or 'agent_ids'
      $syncFlag    hidden marker so an all-unchecked submit still syncs to empty
      $options     iterable of objects/arrays with: value, label, checked (bool)
      $placeholder shown when nothing is selected
      $summary     text shown when something is selected (e.g. joined labels)
      $empty       text shown when there are no options at all
--}}
<div x-data="{
        open: false,
        dirty: false,
        close() {
            if (!this.open) return;
            this.open = false;
            if (this.dirty) { this.dirty = false; this.$refs.form.requestSubmit(); }
        },
     }"
     @click.outside="close()" @keydown.escape="close()" class="relative w-72">
    <form method="POST" action="{{ $action }}" x-ref="form">
        @csrf @method('PATCH')
        <input type="hidden" name="{{ $syncFlag }}" value="1">
        <button type="button" @click="open ? close() : (open = true)"
                class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-left text-sm hover:border-gray-300 focus:border-[var(--color-primary)] focus:outline-none">
            <span class="truncate {{ $summary ? 'text-[var(--color-heading)]' : 'text-gray-400' }}">{{ $summary ?: $placeholder }}</span>
            <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
        </button>
        <div x-show="open" x-cloak x-transition.opacity.duration.100ms
             class="absolute left-0 right-0 z-30 mt-1 max-h-56 overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
            @forelse ($options as $opt)
                <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 text-sm hover:bg-gray-50">
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $opt['value'] }}" @checked($opt['checked'])
                           @change="dirty = true"
                           class="h-4 w-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                    <span class="text-[var(--color-heading)]">{{ $opt['label'] }}</span>
                </label>
            @empty
                <p class="px-3 py-2 text-sm text-gray-400">{{ $empty }}</p>
            @endforelse
        </div>
    </form>
</div>
