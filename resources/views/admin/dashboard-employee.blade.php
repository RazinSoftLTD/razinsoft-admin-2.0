@extends('admin.layouts.app')
@section('title', 'Dashboard')

@php
    $statusChip = fn ($s) => match ($s) {
        'open' => ['Open', 'text-red-600', 'bg-red-500'],
        'pending' => ['Pending', 'text-amber-600', 'bg-amber-400'],
        'resolved' => ['Resolved', 'text-emerald-600', 'bg-emerald-500'],
        default => ['Closed', 'text-gray-500', 'bg-gray-400'],
    };
@endphp

@section('content')
    <h1 class="mb-5 text-xl font-bold text-[var(--color-heading)]">Welcome {{ $me->name }}</h1>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ── Left: profile + tickets ── --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Profile card --}}
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-4">
                    @if ($me->photo_url)
                        <img src="{{ $me->photo_url }}" alt="" class="h-16 w-16 rounded-full border border-gray-200 object-cover">
                    @else
                        <span class="grid h-16 w-16 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xl font-bold text-[var(--color-primary)]">{{ strtoupper(substr($me->name, 0, 1)) }}</span>
                    @endif
                    <div class="flex-1">
                        <p class="text-lg font-bold text-[var(--color-heading)]">{{ $me->name }}</p>
                        <p class="text-sm text-[var(--color-muted)]">{{ $me->designation->name ?? 'Employee' }}</p>
                        <p class="mt-0.5 text-xs text-[var(--color-muted)]">Employee ID: {{ $me->employee_code ?? '—' }}</p>
                    </div>
                    <a href="{{ route('admin.my-profile.edit') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg> Edit Profile
                    </a>
                </div>
                <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-3"><dt class="text-[var(--color-muted)]">Department</dt><dd class="font-medium text-[var(--color-heading)]">{{ $me->department->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-[var(--color-muted)]">Reporting To</dt><dd class="font-medium text-[var(--color-heading)]">{{ $me->reportsTo->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-[var(--color-muted)]">Email</dt><dd class="truncate font-medium text-[var(--color-heading)]">{{ $me->email }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-[var(--color-muted)]">Joined</dt><dd class="font-medium text-[var(--color-heading)]">{{ optional($me->joining_date)->format('d M, Y') ?? '—' }}</dd></div>
                </dl>
            </div>

            {{-- My assigned tickets (only for employees with ticket access) --}}
            @if (auth()->user()->hasPermission('tickets.view'))
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">My Assigned Tickets</h2>
                    <a href="{{ route('admin.tickets.index') }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">View all</a>
                </div>
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Ticket</th>
                            <th class="px-5 py-3 font-semibold">Customer</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($assignedTickets as $t)
                            @php [$sl, $sc, $sd] = $statusChip($t->status); @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3"><a href="{{ route('admin.tickets.show', $t) }}" class="font-medium text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ \Illuminate\Support\Str::limit($t->subject, 40) }}</a><div class="text-xs text-[var(--color-muted)]">#{{ $t->ticket_number }}</div></td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $t->client->name ?? '—' }}</td>
                                <td class="px-5 py-3"><span class="inline-flex items-center gap-1.5 text-sm font-medium {{ $sc }}"><span class="h-2 w-2 rounded-full {{ $sd }}"></span> {{ $sl }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-10 text-center text-[var(--color-muted)]">No tickets assigned to you.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- ── Right: stats + birthdays ── --}}
        <div class="space-y-6">
            {{-- Ticket stats --}}
            @if (auth()->user()->hasPermission('tickets.view'))
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                    <p class="text-2xl font-bold text-red-600">{{ $ticketStats['open'] }}</p>
                    <p class="text-xs text-[var(--color-muted)]">Open</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                    <p class="text-2xl font-bold text-amber-600">{{ $ticketStats['pending'] }}</p>
                    <p class="text-xs text-[var(--color-muted)]">Pending</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                    <p class="text-2xl font-bold text-[var(--color-heading)]">{{ $ticketStats['total'] }}</p>
                    <p class="text-xs text-[var(--color-muted)]">Total</p>
                </div>
            </div>
            @endif

            {{-- Birthdays --}}
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Upcoming Birthdays</h2>
                <div class="space-y-3">
                    @forelse ($birthdays as $b)
                        <div class="flex items-center gap-3">
                            @if ($b->photo_url)<img src="{{ $b->photo_url }}" alt="" class="h-9 w-9 rounded-full object-cover">@else<span class="grid h-9 w-9 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]">{{ strtoupper(substr($b->name, 0, 1)) }}</span>@endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-[var(--color-heading)]">{{ $b->name }}</p>
                                <p class="truncate text-xs text-[var(--color-muted)]">{{ $b->designation->name ?? '' }}</p>
                            </div>
                            <span class="shrink-0 text-xs font-medium text-[var(--color-muted)]">{{ $b->next_birthday->format('d M') }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-[var(--color-muted)]">No birthdays on record.</p>
                    @endforelse
                </div>
            </div>

            {{-- Coming soon modules --}}
            <div class="rounded-xl border border-dashed border-gray-200 bg-white p-5 text-center shadow-sm">
                <p class="text-sm font-semibold text-[var(--color-heading)]">More coming soon</p>
                <p class="mt-1 text-xs text-[var(--color-muted)]">Tasks, Projects, Leave, Attendance, Holidays & Timelogs will appear here.</p>
            </div>
        </div>
    </div>
@endsection
