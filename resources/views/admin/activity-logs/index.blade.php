@extends('admin.layouts.app')
@section('title', 'Employee Activity')

@php
    $methodChip = [
        'POST' => 'bg-emerald-50 text-emerald-700', 'PUT' => 'bg-blue-50 text-blue-700',
        'PATCH' => 'bg-blue-50 text-blue-700', 'DELETE' => 'bg-red-50 text-red-600', 'GET' => 'bg-gray-100 text-gray-500',
    ];
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Employee Activity</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Who's active right now, most recent on top. Click an employee to see their full activity trail.</p>
    </div>

    @if ($employees->isEmpty())
        <div class="rounded-xl border border-gray-100 bg-white p-12 text-center text-gray-400">No activity recorded yet.</div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <ul class="divide-y divide-gray-100">
                @foreach ($employees as $row)
                    @php $log = $row['log']; @endphp
                    <li>
                        <a href="{{ route('admin.activity-logs.show', $log->user_id) }}" class="flex items-center gap-4 px-5 py-4 transition hover:bg-gray-50">
                            {{-- avatar --}}
                            @if ($log->user?->photo)
                                <img src="{{ asset('storage/'.$log->user->photo) }}" alt="" class="h-11 w-11 shrink-0 rounded-full border border-gray-200 object-cover">
                            @else
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]">{{ strtoupper(substr($log->user->name ?? '?', 0, 1)) }}</span>
                            @endif

                            {{-- name + last info --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="truncate font-bold text-[var(--color-heading)]">{{ $log->user->name }}</span>
                                    @if ($log->created_at && $log->created_at->gt(now()->subMinutes(10)))
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-600"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active now</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 flex items-center gap-1.5 truncate text-xs text-[var(--color-muted)]">
                                    <span class="inline-flex shrink-0 rounded-full px-1.5 py-0.5 font-semibold {{ $methodChip[$log->method] ?? 'bg-gray-100 text-gray-500' }}">{{ $log->verb() }}</span>
                                    <span class="truncate">{{ $log->module() }}</span>
                                    <span class="shrink-0 text-gray-300">·</span>
                                    <span class="shrink-0">{{ $row['total'] }} action{{ $row['total'] === 1 ? '' : 's' }}</span>
                                </p>
                            </div>

                            {{-- last active + chevron --}}
                            <div class="hidden shrink-0 text-right sm:block">
                                <p class="text-sm font-medium text-[var(--color-heading)]">{{ $log->created_at?->diffForHumans() }}</p>
                                <p class="text-[11px] text-gray-400">{{ $log->created_at?->format('d M Y, h:i A') }}</p>
                            </div>
                            <svg class="h-5 w-5 shrink-0 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 18 6-6-6-6"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
