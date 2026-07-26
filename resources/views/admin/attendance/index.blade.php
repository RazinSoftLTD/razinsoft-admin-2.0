@extends('admin.layouts.app')
@section('title', 'Attendance')

@php
    $me = auth()->user();
    $canManual = $me->allows('attendance', 'create') && $settings->allows(\App\Models\Attendance::METHOD_MANUAL);
    $canDelete = $me->allows('attendance', 'delete');
    $webOn = $settings->allows(\App\Models\Attendance::METHOD_WEB);
    $marked = $rows->keyBy('user_id');
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Attendance</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                Office hours {{ \Carbon\Carbon::parse($settings->office_start)->format('g:i A') }}–{{ \Carbon\Carbon::parse($settings->office_end)->format('g:i A') }}
                · {{ $settings->grace_minutes }} min grace
                @if (count($settings->enabledMethods()))
                    · Methods: {{ implode(', ', $settings->enabledMethods()) }}
                @endif
            </p>
        </div>
        @if ($canManual)
            <button type="button" @click="$dispatch('open-manual')" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Manual Entry
            </button>
        @endif
    </div>

    @include('admin.attendance._nav')

    @unless ($settings->attendance_enabled)
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Attendance is switched off in HR Settings — nothing is being recorded.
        </div>
    @endunless

    {{-- My own day --}}
    <div class="mb-6 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400">My attendance · {{ today()->format('d M Y') }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-4">
                    <div>
                        <p class="text-lg font-extrabold text-[var(--color-heading)]">
                            {{ $mine?->check_in_at?->format('g:i A') ?? '—' }}
                            @include('admin.attendance._method-badge', ['method' => $mine?->check_in_method])
                        </p>
                        <p class="text-xs text-[var(--color-muted)]">Check in</p>
                    </div>
                    <div>
                        <p class="text-lg font-extrabold text-[var(--color-heading)]">
                            {{ $mine?->check_out_at?->format('g:i A') ?? '—' }}
                            @include('admin.attendance._method-badge', ['method' => $mine?->check_out_method])
                        </p>
                        <p class="text-xs text-[var(--color-muted)]">Check out</p>
                    </div>
                    <div>
                        <p class="text-lg font-extrabold text-[var(--color-heading)]">{{ $mine ? $mine->workedLabel() : '—' }}</p>
                        <p class="text-xs text-[var(--color-muted)]">Worked</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if ($webOn)
                    <form method="POST" action="{{ route('admin.attendance.check-in') }}">
                        @csrf
                        <button @disabled($mine?->check_in_at)
                                class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] {{ $mine?->check_in_at ? 'cursor-not-allowed opacity-40' : '' }}">
                            {{ $mine?->check_in_at ? 'Checked in' : 'Check In' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.attendance.check-out') }}">
                        @csrf
                        <button @disabled(! $mine?->check_in_at || $mine?->check_out_at)
                                class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-heading)] transition hover:bg-gray-50 {{ (! $mine?->check_in_at || $mine?->check_out_at) ? 'cursor-not-allowed opacity-40' : '' }}">
                            {{ $mine?->check_out_at ? 'Checked out' : 'Check Out' }}
                        </button>
                    </form>
                @else
                    <p class="text-xs text-[var(--color-muted)]">Web check-in is off — attendance comes from {{ implode(' / ', $settings->enabledMethods()) ?: 'no method' }}.</p>
                @endif
            </div>
        </div>
    </div>

    @if ($scopeAll)
        {{-- Day summary --}}
        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['Present', $stats['present'], 'text-emerald-600'], ['Late', $stats['late'], 'text-amber-600'], ['Half day', $stats['half_day'], 'text-sky-600'], ['Not marked', $stats['absent'], 'text-red-600']] as [$label, $value, $tone])
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-400">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-extrabold {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <input type="date" name="date" value="{{ $date->toDateString() }}" class="h-10 rounded-lg border-gray-200 text-sm">
        @if ($scopeAll)
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Employee…" class="h-10 w-48 rounded-lg border-gray-200 text-sm">
            <select name="status" class="h-10 rounded-lg border-gray-200 text-sm">
                <option value="">All statuses</option>
                @foreach (\App\Models\Attendance::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select>
            <select name="method" class="h-10 rounded-lg border-gray-200 text-sm">
                <option value="">All methods</option>
                @foreach (\App\Models\Attendance::METHODS as $k => $v)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $v }}</option>@endforeach
            </select>
        @endif
        <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Filter</button>
        @if (request()->hasAny(['date', 'search', 'status', 'method']))
            <a href="{{ route('admin.attendance.index') }}" class="text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">Clear</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm" style="min-width:880px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Employee</th>
                    <th class="px-5 py-3 font-semibold">Check In</th>
                    <th class="px-5 py-3 font-semibold">Check Out</th>
                    <th class="px-5 py-3 font-semibold">Worked</th>
                    <th class="px-5 py-3 font-semibold">Late</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    @if ($canDelete)<th class="px-5 py-3 text-right font-semibold">Action</th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($staff as $person)
                    @php $a = $marked[$person->id] ?? null; @endphp
                    @continue(request('status') && ! $a)
                    @continue(request('method') && ! $a)
                    @continue(request('search') && ! str_contains(strtolower($person->name), strtolower(request('search'))))
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-[var(--color-heading)]">{{ $person->name }}</p>
                            <p class="text-xs text-gray-400">{{ $person->designation->name ?? '—' }}</p>
                        </td>
                        <td class="px-5 py-3">
                            @if ($a?->check_in_at)
                                <span class="font-medium text-[var(--color-heading)]">{{ $a->check_in_at->format('g:i A') }}</span>
                                @include('admin.attendance._method-badge', ['method' => $a->check_in_method])
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @if ($a?->check_out_at)
                                <span class="font-medium text-[var(--color-heading)]">{{ $a->check_out_at->format('g:i A') }}</span>
                                @include('admin.attendance._method-badge', ['method' => $a->check_out_method])
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $a ? $a->workedLabel() : '—' }}</td>
                        <td class="px-5 py-3">
                            @if ($a && $a->late_minutes > 0)
                                <span class="font-semibold text-amber-600">{{ \App\Models\Attendance::minutesLabel($a->late_minutes) }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @php
                                $chip = ['present' => 'bg-emerald-50 text-emerald-700', 'late' => 'bg-amber-50 text-amber-700', 'half_day' => 'bg-sky-50 text-sky-700', 'absent' => 'bg-red-50 text-red-600'];
                                $st = $a?->status ?? 'absent';
                            @endphp
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $chip[$st] ?? 'bg-gray-100 text-gray-500' }}">
                                {{ $a ? (\App\Models\Attendance::STATUSES[$st] ?? $st) : 'Not marked' }}
                            </span>
                            @if ($a?->markedBy)<span class="ml-1 text-[10px] text-gray-400">by {{ $a->markedBy->name }}</span>@endif
                        </td>
                        @if ($canDelete)
                            <td class="px-5 py-3 text-right">
                                @if ($a)
                                    <form method="POST" action="{{ route('admin.attendance.destroy', $a) }}" onsubmit="return confirm('Remove {{ $person->name }}\'s attendance for this day?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-gray-300">No employees to show.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Manual entry --}}
    @if ($canManual)
        <div x-data="{ open: false }" @open-manual.window="open = true" @keydown.escape.window="open = false">
            <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="open = false"></div>
            <div x-show="open" x-cloak x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-20" @click.self="open = false">
                <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <div>
                            <h3 class="text-base font-bold text-[var(--color-heading)]">Manual Attendance</h3>
                            <p class="text-xs text-[var(--color-muted)]">HR entry — recorded as method “Manual”.</p>
                        </div>
                        <button type="button" @click="open = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('admin.attendance.manual') }}" class="space-y-4 p-5">
                        @csrf
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Employee <span class="text-red-500">*</span></label>
                            <select name="user_id" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                <option value="">Select employee</option>
                                @foreach ($staff as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Date <span class="text-red-500">*</span></label>
                            <input type="date" name="work_date" required value="{{ $date->toDateString() }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Check in</label>
                                <input type="time" name="check_in" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Check out</label>
                                <input type="time" name="check_out" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Status override</label>
                            <select name="status" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                                <option value="">Work it out from the times</option>
                                @foreach (\App\Models\Attendance::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Notes</label>
                            <textarea name="notes" rows="2" maxlength="500" class="w-full rounded-lg border-gray-200 text-sm" placeholder="e.g. Device was offline"></textarea>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
