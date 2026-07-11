@extends('admin.layouts.app')
@section('title', 'Reschedule Meeting')

@section('content')
    <a href="{{ route('admin.meetings.show', $meeting) }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to meeting
    </a>

    <form method="POST" action="{{ route('admin.meetings.reschedule', $meeting) }}" class="max-w-lg">
        @csrf @method('PATCH')
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h1 class="text-base font-bold text-[var(--color-heading)]">Reschedule meeting <span class="font-mono text-[var(--color-primary)]">#{{ $meeting->id }}</span></h1>
            <p class="mb-5 text-sm text-[var(--color-muted)]">Currently {{ $meeting->date->format('D, d M Y') }} · {{ $meeting->slot_label }}</p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Date</label>
                    <input type="date" name="date" value="{{ old('date', $meeting->date->toDateString()) }}" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Time slot</label>
                    <select name="start" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                        @foreach ($windows as $w)
                            @php $label = \Illuminate\Support\Carbon::parse($w[0])->format('g:i A').' – '.\Illuminate\Support\Carbon::parse($w[1])->format('g:i A'); @endphp
                            <option value="{{ $w[0] }}" @selected(old('start', substr($meeting->start_time, 0, 5)) === $w[0])>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
        </div>

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save new time</button>
            <a href="{{ route('admin.meetings.show', $meeting) }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
@endsection
