@props(['template'])

{{--
    On/off switch for a template, usable from any list.

    Submits normally rather than by fetch, so it works without JavaScript and the page comes back
    with the flash message. The switch paints itself immediately on click so it never looks stuck
    while the request is in flight.

    stopPropagation matters: these sit inside rows that are themselves links to the editor.
--}}
<form method="POST" action="{{ route('admin.email.templates.toggle', $template) }}"
      x-data="{ on: {{ $template->is_active ? 'true' : 'false' }} }"
      @click.stop
      {{ $attributes->only('class') }}>
    @csrf
    <button type="submit"
            @click="on = !on"
            :aria-checked="on ? 'true' : 'false'"
            role="switch"
            title="{{ $template->is_active ? 'Turn this email off' : 'Turn this email on' }}"
            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
            :class="on ? 'bg-[var(--color-primary)]' : 'bg-gray-200'">
        <span class="sr-only" x-text="on ? 'Turn off' : 'Turn on'"></span>
        {{-- Inline transform: translate-x-* is not in the precompiled CSS bundle, and the
             deploy runs no npm build. --}}
        <span class="inline-block h-4 w-4 rounded-full bg-white transition"
              :style="on ? 'transform:translateX(24px)' : 'transform:translateX(4px)'"></span>
    </button>
</form>
