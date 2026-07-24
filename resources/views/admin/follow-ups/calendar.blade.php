@extends('admin.layouts.app')
@section('title', 'Follow-up Calendar')

@section('content')
    @php
        $statusDot = ['pending' => 'bg-orange-500', 'overdue' => 'bg-red-500', 'done' => 'bg-emerald-500', 'cancelled' => 'bg-gray-400'];
    @endphp

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-sm text-[var(--color-muted)]">
                <a href="{{ route('admin.follow-ups.index') }}" class="hover:text-[var(--color-heading)]">Follow-ups</a>
                <span>&rsaquo;</span><span>Calendar</span>
            </div>
            <h1 class="mt-1 text-xl font-bold text-[var(--color-heading)]">{{ $month->format('F Y') }}</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.follow-ups.calendar', ['month' => $prevMonth]) }}" class="grid h-9 w-9 place-items-center rounded-lg border border-gray-200 bg-white text-[var(--color-muted)] hover:bg-gray-50" title="Previous month">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg>
            </a>
            <a href="{{ route('admin.follow-ups.calendar') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Today</a>
            <a href="{{ route('admin.follow-ups.calendar', ['month' => $nextMonth]) }}" class="grid h-9 w-9 place-items-center rounded-lg border border-gray-200 bg-white text-[var(--color-muted)] hover:bg-gray-50" title="Next month">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 18 6-6-6-6"/></svg>
            </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        {{-- Weekday header --}}
        <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50 text-center text-xs font-semibold uppercase tracking-wide text-gray-400">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                <div class="px-2 py-2.5">{{ $dow }}</div>
            @endforeach
        </div>

        {{-- Weeks --}}
        <div class="grid grid-cols-7">
            @foreach ($weeks as $week)
                @foreach ($week as $day)
                    @php
                        $key = $day->toDateString();
                        $events = $byDay[$key] ?? collect();
                        $inMonth = $day->month === $month->month;
                        $isToday = $day->isToday();
                    @endphp
                    <div class="min-h-[7rem] border-b border-r border-gray-100 p-1.5 {{ $inMonth ? '' : 'bg-gray-50/60' }}">
                        <div class="mb-1 flex items-center justify-between px-0.5">
                            <span class="grid h-6 w-6 place-items-center rounded-full text-xs font-semibold {{ $isToday ? 'bg-[var(--color-primary)] text-white' : ($inMonth ? 'text-[var(--color-heading)]' : 'text-gray-300') }}">{{ $day->day }}</span>
                            @if ($events->count() > 3)<span class="text-[10px] font-semibold text-[var(--color-muted)]">{{ $events->count() }}</span>@endif
                        </div>
                        <div class="space-y-1">
                            @foreach ($events->take(3) as $ev)
                                <a href="{{ route('admin.leads.show', $ev->lead_id) }}"
                                   title="{{ $ev->typeLabel() }} · {{ $ev->scheduled_at->format('h:i A') }} · {{ $ev->lead?->full_name }}"
                                   class="flex items-center gap-1.5 rounded-md bg-gray-50 px-1.5 py-1 text-[11px] hover:bg-gray-100">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $statusDot[$ev->effectiveStatus()] ?? 'bg-gray-400' }}"></span>
                                    <span class="truncate font-medium text-[var(--color-heading)]">{{ $ev->scheduled_at->format('H:i') }} {{ $ev->lead?->full_name }}</span>
                                </a>
                            @endforeach
                            @if ($events->count() > 3)
                                <a href="{{ route('admin.follow-ups.index', ['view' => 'all']) }}" class="block px-1.5 text-[10px] font-semibold text-[var(--color-primary)] hover:underline">+{{ $events->count() - 3 }} more</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- Legend --}}
    <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-[var(--color-muted)]">
        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-orange-500"></span> Pending</span>
        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-red-500"></span> Overdue</span>
        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Done</span>
        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-gray-400"></span> Cancelled</span>
    </div>
@endsection
