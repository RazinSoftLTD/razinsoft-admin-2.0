{{-- Right pane — re-rendered by a smooth Turbo Drive visit on conversation switch. --}}
<section id="thread-pane" class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
    @if ($active)
        @include('admin.chat._thread')
    @else
        <div class="grid flex-1 place-items-center text-center">
            <div>
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v10Z"/></svg>
                </span>
                <p class="mt-3 text-sm font-semibold text-[var(--color-heading)]">Your team conversations</p>
                <p class="mt-1 text-sm text-[var(--color-muted)]">Pick a teammate or channel on the left to start chatting.</p>
            </div>
        </div>
    @endif
</section>
