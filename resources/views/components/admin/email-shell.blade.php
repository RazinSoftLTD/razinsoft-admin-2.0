{{-- Shared heading for the Email Manager pages. The section's own navigation lives in the sidebar
     group, so there is no in-page menu here — this only saves repeating the same header in seven
     views. --}}
<div class="mb-5">
    <h1 class="text-xl font-bold text-[var(--color-heading)]">Email Manager</h1>
    <p class="text-sm text-[var(--color-muted)]">How mail is templated, queued, sent and tracked. SMTP accounts live under Email Config.</p>
</div>

{{ $slot }}
