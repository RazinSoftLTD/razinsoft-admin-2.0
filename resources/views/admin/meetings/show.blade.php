@extends('admin.layouts.app')
@section('title', 'Meeting Details')

@php
    $me = auth()->user();
    $canEdit = $me->allows('meetings', 'edit');
    $canAssign = $me->allows('meetings', 'assign');
    $chip = fn ($s) => match ($s) {
        'pending'   => ['Pending', 'bg-amber-50 text-amber-700 ring-amber-200'],
        'confirmed' => ['Confirmed', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'completed' => ['Completed', 'bg-gray-100 text-gray-600 ring-gray-200'],
        'cancelled' => ['Cancelled', 'bg-red-50 text-red-600 ring-red-200'],
        default     => [ucfirst($s), 'bg-gray-100 text-gray-600 ring-gray-200'],
    };
    [$sl, $sc] = $chip($meeting->status);
@endphp

@section('content')
    <a href="{{ route('admin.meetings.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to meetings
    </a>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Details --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-[var(--color-primary)]">{{ $meeting->date->format('l, F j, Y') }}</p>
                        <h1 class="mt-1 text-2xl font-bold text-[var(--color-heading)]">{{ $meeting->slot_label }}</h1>
                    </div>
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $sc }}">{{ $sl }}</span>
                </div>

                <dl class="mt-6 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2">
                    <div><dt class="text-xs uppercase tracking-wide text-gray-400">Name</dt><dd class="mt-0.5 font-medium text-[var(--color-heading)]">{{ $meeting->name }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-gray-400">Email</dt><dd class="mt-0.5 font-medium text-[var(--color-heading)]">{{ $meeting->email }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-gray-400">Phone</dt><dd class="mt-0.5 font-medium text-[var(--color-heading)]">{{ trim(($meeting->dial_code ?? '').' '.($meeting->phone ?? '')) ?: '—' }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-gray-400">Company</dt><dd class="mt-0.5 font-medium text-[var(--color-heading)]">{{ $meeting->company ?: '—' }}</dd></div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Client account</dt>
                        <dd class="mt-0.5 font-medium text-[var(--color-heading)]">
                            @if ($meeting->client)
                                <a href="{{ route('admin.clients.show', $meeting->client) }}" class="text-[var(--color-primary)] hover:underline">{{ $meeting->client->name }}</a>
                                <span class="ml-1 rounded-full bg-[var(--color-primary-soft)] px-2 py-0.5 text-[11px] font-semibold text-[var(--color-primary)]">Client</span>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($meeting->notes)
                    <div class="mt-5 border-t border-gray-100 pt-5">
                        <p class="text-xs uppercase tracking-wide text-gray-400">What they want to discuss</p>
                        <div class="prose prose-sm mt-1 max-w-none text-sm text-[var(--color-heading)] [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5">{!! $meeting->notes !!}</div>
                    </div>
                @endif

                <p class="mt-5 border-t border-gray-100 pt-4 text-xs text-[var(--color-muted)]">Booked {{ $meeting->created_at->format('d M Y, g:i A') }} · {{ $meeting->created_at->diffForHumans() }}</p>
            </div>
        </div>

        {{-- Manage --}}
        <div class="space-y-6">
            <form method="POST" action="{{ route('admin.meetings.update', $meeting) }}" class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                @csrf @method('PATCH')
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Manage</h2>

                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Status</label>
                <select name="status" @disabled(! $canEdit) class="mb-4 h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    @foreach (\App\Models\Meeting::STATUSES as $s)
                        <option value="{{ $s }}" @selected($meeting->status === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>

                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Assigned to</label>
                <select name="assigned_to" @disabled(! $canAssign) class="mb-4 h-11 w-full rounded-lg border border-gray-200 px-3 text-sm {{ ! $canAssign ? 'bg-gray-50 text-gray-400' : '' }}">
                    <option value="">— Unassigned —</option>
                    @foreach ($employees as $e)
                        <option value="{{ $e->id }}" @selected($meeting->assigned_to === $e->id)>{{ $e->name }}</option>
                    @endforeach
                </select>

                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Meeting link</label>
                <input name="meeting_link" value="{{ old('meeting_link', $meeting->meeting_link) }}" @disabled(! $canEdit) placeholder="https://meet.google.com/…" class="mb-4 h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">

                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Internal notes</label>
                <textarea name="admin_notes" rows="3" @disabled(! $canEdit) placeholder="Private notes for the team" class="mb-4 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">{{ old('admin_notes', $meeting->admin_notes) }}</textarea>

                @if ($errors->any())
                    <div class="mb-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif

                @if ($canEdit)
                    <button class="w-full rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                @endif
            </form>

            @if ($meeting->meeting_link)
                <a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-[var(--color-primary)] shadow-sm hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5h5v5M19 5l-9 9M19 13v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/></svg>
                    Join meeting
                </a>
            @endif

            @if ($me->allows('meetings', 'delete'))
                <form method="POST" action="{{ route('admin.meetings.destroy', $meeting) }}" onsubmit="return confirm('Delete this meeting?')">
                    @csrf @method('DELETE')
                    <button class="w-full rounded-lg border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Delete meeting</button>
                </form>
            @endif
        </div>
    </div>
@endsection
