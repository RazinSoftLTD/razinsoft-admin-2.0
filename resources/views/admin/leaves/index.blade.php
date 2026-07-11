@extends('admin.layouts.app')
@section('title', 'Leave')

@php
    $statusChip = fn ($s) => match ($s) {
        'approved' => ['Approved', 'text-emerald-600', 'bg-emerald-50'],
        'rejected' => ['Rejected', 'text-red-600', 'bg-red-50'],
        default => ['Pending', 'text-amber-600', 'bg-amber-50'],
    };
    $tabs = ['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $seeAll ? 'Leave Requests' : 'My Leave' }}</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">{{ $seeAll ? 'Review and approve team leave requests.' : 'Request time off and track your leave.' }}</p>
        </div>
        <a href="{{ route('admin.leaves.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Request Leave
        </a>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($tabs as $key => $label)
            @php $n = $key === '' ? $counts['all'] : $counts[$key]; @endphp
            <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => 1]) }}"
               class="inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-semibold {{ $status === $key ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-gray-200 bg-white text-[var(--color-muted)] hover:bg-gray-50' }}">
                {{ $label }} <span class="rounded-full {{ $status === $key ? 'bg-white/20' : 'bg-gray-100' }} px-1.5 text-xs">{{ $n }}</span>
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        @if ($seeAll)<th class="px-5 py-3 font-semibold">Employee</th>@endif
                        <th class="px-5 py-3 font-semibold">Type</th>
                        <th class="px-5 py-3 font-semibold">Duration</th>
                        <th class="px-5 py-3 font-semibold">Days</th>
                        <th class="px-5 py-3 font-semibold">Reason</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($leaves as $l)
                        @php [$sl, $sc, $sbg] = $statusChip($l->status); @endphp
                        <tr class="hover:bg-gray-50">
                            @if ($seeAll)
                                <td class="px-5 py-3">
                                    <div class="font-medium text-[var(--color-heading)]">{{ $l->user->name ?? '—' }}</div>
                                    <div class="text-xs text-[var(--color-muted)]">{{ $l->user->designation->name ?? '' }}</div>
                                </td>
                            @endif
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $l->typeLabel() }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $l->from_date->format('d M') }} – {{ $l->to_date->format('d M, Y') }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $l->days() }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ \Illuminate\Support\Str::limit($l->reason, 40) ?: '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $sbg }} {{ $sc }}">{{ $sl }}</span>
                                @if ($l->reviewer)<div class="mt-0.5 text-[10px] text-gray-400">by {{ $l->reviewer->name }}</div>@endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($canApprove && $l->status === 'pending')
                                        <form method="POST" action="{{ route('admin.leaves.status', $l) }}">
                                            @csrf @method('PATCH')<input type="hidden" name="status" value="approved">
                                            <button class="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.leaves.status', $l) }}">
                                            @csrf @method('PATCH')<input type="hidden" name="status" value="rejected">
                                            <button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Reject</button>
                                        </form>
                                    @endif
                                    @if (($l->user_id === auth()->id() && $l->status === 'pending') || auth()->user()->isAdmin())
                                        <form method="POST" action="{{ route('admin.leaves.destroy', $l) }}" onsubmit="return confirm('Delete this leave request?')">
                                            @csrf @method('DELETE')
                                            <button class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $seeAll ? 7 : 6 }}" class="px-5 py-12 text-center text-gray-400">No leave requests.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $leaves->links() }}</div>
@endsection
