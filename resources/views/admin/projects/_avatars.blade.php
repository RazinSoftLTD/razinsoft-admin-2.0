{{-- Overlapping avatar stack (desk-style). Props: $users (User collection), $max = 4, $size = 7. --}}
@php
    $max = $max ?? 4;
    $size = $size ?? 7;
    $users = collect($users)->filter();
    $extra = max(0, $users->count() - $max);
    $cls = "h-{$size} w-{$size}";
@endphp
<div class="flex items-center -space-x-2">
    @forelse ($users->take($max) as $u)
        @if ($u->photo_url)
            <img src="{{ $u->photo_url }}" alt="{{ $u->name }}" title="{{ $u->name }}" class="{{ $cls }} rounded-full border-2 border-white object-cover">
        @else
            <span title="{{ $u->name }}" class="grid {{ $cls }} place-items-center rounded-full border-2 border-white bg-[var(--color-primary-soft)] text-[10px] font-bold text-[var(--color-primary)]">{{ collect(explode(' ', $u->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('') }}</span>
        @endif
    @empty
        <span class="text-xs text-gray-300">—</span>
    @endforelse
    @if ($extra > 0)
        <span class="grid {{ $cls }} place-items-center rounded-full border-2 border-white bg-gray-100 text-[10px] font-bold text-gray-500">+{{ $extra }}</span>
    @endif
</div>
