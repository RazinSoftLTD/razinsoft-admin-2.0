@extends('admin.layouts.app')
@section('title', 'Activity · '.$employee->name)

@php
    $methodChip = [
        'POST' => 'bg-emerald-50 text-emerald-700', 'PUT' => 'bg-blue-50 text-blue-700',
        'PATCH' => 'bg-blue-50 text-blue-700', 'DELETE' => 'bg-red-50 text-red-600', 'GET' => 'bg-gray-100 text-gray-500',
    ];
@endphp

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.activity-logs') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> All employees
        </a>
        <div class="mt-3 flex items-center gap-3">
            @if ($employee->photo)
                <img src="{{ asset('storage/'.$employee->photo) }}" alt="" class="h-12 w-12 rounded-full border border-gray-200 object-cover">
            @else
                <span class="grid h-12 w-12 place-items-center rounded-full bg-[var(--color-primary-soft)] text-base font-bold text-[var(--color-primary)]">{{ strtoupper(substr($employee->name, 0, 1)) }}</span>
            @endif
            <div>
                <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $employee->name }}</h1>
                <p class="text-sm text-[var(--color-muted)]">{{ $logs->total() }} recorded action{{ $logs->total() === 1 ? '' : 's' }}</p>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Action</label>
            <select name="method" class="h-10 w-40 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                <option value="">All actions</option>
                @foreach ($methods as $mv => $ml)<option value="{{ $mv }}" @selected(request('method') === $mv)>{{ $ml }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">When</label>
            <select name="date_range" class="h-10 w-36 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                <option value="">Any time</option>
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $dv => $dl)
                    <option value="{{ $dv }}" @selected(request('date_range') === $dv)>{{ $dl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="h-10 rounded-lg border border-gray-200 px-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="h-10 rounded-lg border border-gray-200 px-2 text-sm">
        </div>
        <button class="h-10 rounded-lg bg-[var(--color-primary)] px-5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Filter</button>
        <a href="{{ route('admin.activity-logs.show', $employee) }}" class="h-10 rounded-lg border border-gray-200 px-5 text-sm font-semibold leading-10 text-[var(--color-muted)] hover:bg-gray-50">Clear</a>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Action</th>
                        <th class="px-5 py-3 font-semibold">Details</th>
                        <th class="px-5 py-3 font-semibold">IP</th>
                        <th class="px-5 py-3 font-semibold">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $methodChip[$log->method] ?? 'bg-gray-100 text-gray-500' }}">{{ $log->verb() }}</span>
                                <span class="ml-1 text-[var(--color-heading)]">{{ $log->module() }}</span>
                            </td>
                            <td class="max-w-xs truncate px-5 py-3 text-xs text-[var(--color-muted)]" title="{{ $log->url }}">{{ \Illuminate\Support\Str::after($log->url, '/admin') ?: $log->url }}</td>
                            <td class="px-5 py-3 text-xs text-[var(--color-muted)]">{{ $log->ip ?? '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $log->created_at?->format('d M Y, h:i A') }} <span class="text-xs text-gray-400">({{ $log->created_at?->diffForHumans() }})</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-gray-400">No activity for this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
