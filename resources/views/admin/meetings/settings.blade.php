@extends('admin.layouts.app')
@section('title', 'Booking Settings')

@php
    $days = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
    $active = old('working_days', $settings->workingDays());
@endphp

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Booking Settings</h1>
            <p class="text-sm text-[var(--color-muted)]">Configure the public Book-a-Meeting calendar.</p>
        </div>
        <a href="{{ route('admin.meetings.index') }}" class="text-sm font-semibold text-[var(--color-muted)] hover:underline">← Meetings</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.meetings.settings.update') }}" class="max-w-2xl">
        @csrf
        <div class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            {{-- Enabled toggle --}}
            <label class="flex items-center justify-between gap-4 rounded-lg border border-gray-100 bg-gray-50 px-4 py-3">
                <span>
                    <span class="block text-sm font-semibold text-[var(--color-heading)]">Accept bookings</span>
                    <span class="block text-xs text-[var(--color-muted)]">Turn the public calendar on or off.</span>
                </span>
                <input type="checkbox" name="is_enabled" value="1" @checked($settings->is_enabled) class="h-5 w-9 cursor-pointer appearance-none rounded-full bg-gray-300 transition checked:bg-[var(--color-primary)]" style="background-image:none">
            </label>

            {{-- Office hours --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Opens at</label>
                    <input type="time" name="start_time" value="{{ old('start_time', substr($settings->start_time, 0, 5)) }}" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Closes at</label>
                    <input type="time" name="end_time" value="{{ old('end_time', substr($settings->end_time, 0, 5)) }}" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Slot length (min)</label>
                    <input type="number" name="slot_minutes" value="{{ old('slot_minutes', $settings->slot_minutes) }}" min="15" max="480" step="15" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>
            </div>

            {{-- Working days --}}
            <div>
                <label class="mb-2 block text-sm font-medium text-[var(--color-heading)]">Working days</label>
                <div class="flex flex-wrap gap-2">
                    @foreach ($days as $num => $label)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="working_days[]" value="{{ $num }}" @checked(in_array($num, $active)) class="peer sr-only">
                            <span class="inline-block rounded-lg border border-gray-200 px-3.5 py-2 text-sm font-medium text-[var(--color-muted)] peer-checked:border-[var(--color-primary)] peer-checked:bg-[var(--color-primary-soft)] peer-checked:text-[var(--color-primary)]">{{ substr($label, 0, 3) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Range / lead --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Bookable up to (days ahead)</label>
                    <input type="number" name="advance_days" value="{{ old('advance_days', $settings->advance_days) }}" min="1" max="365" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Minimum notice (hours)</label>
                    <input type="number" name="lead_hours" value="{{ old('lead_hours', $settings->lead_hours) }}" min="0" max="168" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>
            </div>

            {{-- Default assignee --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Auto-assign new bookings to</label>
                <select name="default_assignee_id" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    <option value="">— Leave unassigned —</option>
                    @foreach ($employees as $e)
                        <option value="{{ $e->id }}" @selected($settings->default_assignee_id === $e->id)>{{ $e->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
        </div>

        <div class="mt-5">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save settings</button>
        </div>
    </form>
@endsection
