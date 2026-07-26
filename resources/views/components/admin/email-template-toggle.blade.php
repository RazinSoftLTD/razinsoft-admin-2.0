@props(['template'])

{{--
    On/off switch for a template, usable from any list.

    Saved in the background rather than by submitting the page: a full reload made the whole
    screen flash for a change this small, and scrolled the list back to the top. The switch paints
    at once, then reverts if the request fails, so it never shows a state the server did not
    accept.

    Still a real form — with Alpine unavailable the submit goes through normally, so the switch
    works without JavaScript.

    stopPropagation matters: these sit inside rows that are themselves links to the editor.
--}}
<form method="POST" action="{{ route('admin.email.templates.toggle', $template) }}"
      x-data="{
          on: {{ $template->is_active ? 'true' : 'false' }},
          busy: false,
          async save() {
              if (this.busy) return;
              const next = !this.on;
              this.on = next;                       // paint first — the change feels instant
              this.busy = true;
              try {
                  const res = await fetch($el.action, {
                      method: 'POST',
                      credentials: 'same-origin',
                      headers: {
                          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                          'Accept': 'application/json',
                      },
                  });
                  if (!res.ok) throw new Error(res.status);
                  this.on = (await res.json()).is_active;
              } catch {
                  this.on = !next;                  // never leave it showing something unsaved
                  alert('Could not save that — please try again.');
              } finally {
                  this.busy = false;
              }
          },
      }"
      @submit.prevent="save()"
      @click.stop
      {{ $attributes->only('class') }}>
    @csrf
    <button type="submit"
            role="switch"
            :aria-checked="on ? 'true' : 'false'"
            :disabled="busy"
            :title="on ? 'Turn this email off' : 'Turn this email on'"
            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
            :class="[on ? 'bg-[var(--color-primary)]' : 'bg-gray-200', busy ? 'opacity-60' : '']">
        <span class="sr-only" x-text="on ? 'Turn off' : 'Turn on'"></span>
        {{-- Inline transform: translate-x-* is not in the precompiled CSS bundle, and the
             deploy runs no npm build. --}}
        <span class="inline-block h-4 w-4 rounded-full bg-white transition"
              :style="on ? 'transform:translateX(24px)' : 'transform:translateX(4px)'"></span>
    </button>
</form>
