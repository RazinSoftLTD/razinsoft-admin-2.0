@extends('admin.layouts.app')
@section('title', 'Book Meeting')

@php
    $chip = fn ($s) => match ($s) {
        'pending'   => ['Pending', 'bg-amber-50 text-amber-700 ring-amber-200'],
        'confirmed' => ['Confirmed', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'completed' => ['Completed', 'bg-gray-100 text-gray-600 ring-gray-200'],
        'cancelled' => ['Cancelled', 'bg-red-50 text-red-600 ring-red-200'],
        default     => [ucfirst($s), 'bg-gray-100 text-gray-600 ring-gray-200'],
    };
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Book Meeting</h1>
            <p class="text-sm text-[var(--color-muted)]">{{ $seeAll ? 'All consultation bookings.' : 'Meetings assigned to you.' }}</p>
        </div>
        @if (auth()->user()->allows('meetings', 'settings'))
            <a href="{{ route('admin.meetings.settings') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>
                Booking Settings
            </a>
        @endif
    </div>

    {{-- Stats --}}
    <div class="mb-5 grid grid-cols-3 gap-3 sm:max-w-lg">
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm"><p class="text-2xl font-bold text-[var(--color-heading)]">{{ $stats['today'] }}</p><p class="text-xs text-[var(--color-muted)]">Today</p></div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm"><p class="text-2xl font-bold text-amber-600">{{ $stats['pending'] }}</p><p class="text-xs text-[var(--color-muted)]">Pending</p></div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm"><p class="text-2xl font-bold text-[var(--color-primary)]">{{ $stats['upcoming'] }}</p><p class="text-xs text-[var(--color-muted)]">Upcoming</p></div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <select name="status" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-200 px-3 text-sm">
            <option value="">All statuses</option>
            @foreach (\App\Models\Meeting::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <select name="scope" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-200 px-3 text-sm">
            <option value="">All dates</option>
            <option value="upcoming" @selected(request('scope') === 'upcoming')>Upcoming only</option>
        </select>
        @if (request('status') || request('scope'))
            <a href="{{ route('admin.meetings.index') }}" class="text-sm font-semibold text-[var(--color-muted)] hover:underline">Reset</a>
        @endif
    </form>

    <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-4 py-3 font-semibold">ID</th>
                    <th class="px-4 py-3 font-semibold">Client</th>
                    <th class="hidden px-4 py-3 font-semibold lg:table-cell">Schedule</th>
                    <th class="px-4 py-3 font-semibold">Assign</th>
                    <th class="hidden px-4 py-3 font-semibold md:table-cell">Follow-up</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($meetings as $m)
                    @php [$sl, $sc] = $chip($m->status); @endphp
                    <tr class="hover:bg-gray-50">
                        {{-- ID --}}
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.meetings.show', $m) }}" class="font-mono text-sm font-semibold text-[var(--color-primary)] hover:underline">#{{ $m->id }}</a>
                        </td>
                        {{-- Client --}}
                        <td class="px-4 py-3">
                            <div class="font-medium text-[var(--color-heading)]">{{ $m->name }}</div>
                            <div class="text-xs text-[var(--color-muted)]">{{ $m->email }}</div>
                        </td>
                        {{-- Schedule --}}
                        <td class="hidden px-4 py-3 lg:table-cell">
                            <div class="text-[var(--color-heading)]">{{ $m->date->format('D, d M Y') }}</div>
                            <div class="text-xs text-[var(--color-muted)]">{{ $m->slot_label }}</div>
                        </td>
                        {{-- Assign --}}
                        <td class="px-4 py-3">
                            @if ($canAssign)
                                <form method="POST" action="{{ route('admin.meetings.quick', $m) }}">
                                    @csrf @method('PATCH')
                                    <select name="assigned_to" onchange="this.form.submit()" class="h-9 w-36 rounded-lg border border-gray-200 px-2 text-xs">
                                        <option value="">— Unassigned —</option>
                                        @foreach ($employees as $e)
                                            <option value="{{ $e->id }}" @selected($m->assigned_to === $e->id)>{{ $e->name }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @else
                                <span class="text-[var(--color-muted)]">{{ $m->assignee->name ?? '—' }}</span>
                            @endif
                        </td>
                        {{-- Follow-up --}}
                        <td class="hidden px-4 py-3 md:table-cell">
                            @if ($canEdit)
                                <form method="POST" action="{{ route('admin.meetings.quick', $m) }}">
                                    @csrf @method('PATCH')
                                    <input type="date" name="follow_up_date" value="{{ optional($m->follow_up_date)->toDateString() }}" onchange="this.form.submit()" class="h-9 w-36 rounded-lg border border-gray-200 px-2 text-xs">
                                </form>
                            @else
                                <span class="text-[var(--color-muted)]">{{ optional($m->follow_up_date)->format('d M Y') ?? '—' }}</span>
                            @endif
                        </td>
                        {{-- Status --}}
                        <td class="px-4 py-3">
                            @if ($canEdit)
                                <form method="POST" action="{{ route('admin.meetings.quick', $m) }}">
                                    @csrf @method('PATCH')
                                    <select name="status" onchange="this.form.submit()" class="h-9 rounded-full border px-2.5 text-xs font-semibold {{ $sc }}">
                                        @foreach (\App\Models\Meeting::STATUSES as $st)
                                            <option value="{{ $st }}" @selected($m->status === $st)>{{ ucfirst($st) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @else
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $sc }}">{{ $sl }}</span>
                            @endif
                        </td>
                        {{-- Action --}}
                        <td class="px-4 py-3 text-right">
                            <div class="relative inline-block text-left" x-data="{ open: false }">
                                <button type="button" @click="open = !open" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-gray-100">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                </button>
                                <div x-show="open" @click.outside="open = false" x-cloak
                                     class="absolute right-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg">
                                    <a href="{{ route('admin.meetings.show', $m) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> View
                                    </a>
                                    @if ($canEdit)
                                        <a href="{{ route('admin.meetings.edit', $m) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" d="M12 14v3M10.5 15.5h3"/></svg> Edit time
                                        </a>
                                    @endif
                                    @if ($canDelete)
                                        <form method="POST" action="{{ route('admin.meetings.destroy', $m) }}" onsubmit="return confirm('Delete meeting #{{ $m->id }}?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7"/></svg> Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-[var(--color-muted)]">No meetings found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $meetings->links() }}</div>
@endsection
