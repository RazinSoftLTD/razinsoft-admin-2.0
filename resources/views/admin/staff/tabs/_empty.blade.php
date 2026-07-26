{{-- Shared empty state for the profile tabs: an icon, a plain line, and an optional hint. --}}
<tr>
    <td colspan="{{ $cols }}" class="px-5 py-12 text-center">
        <span class="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-full bg-gray-50 text-gray-300">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon ?? 'M9 12h6M4 6h16M4 18h16M4 6v12M20 6v12' }}"/></svg>
        </span>
        <p class="text-sm font-semibold text-[var(--color-heading)]">{{ $title }}</p>
        @if (! empty($hint))
            <p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>
        @endif
    </td>
</tr>
