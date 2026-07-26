@extends('admin.layouts.app')
@section('title', 'Attendance History')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Attendance History</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Every recorded day with the method it came from.</p>
    </div>

    @include('admin.attendance._nav')

    <div class="mb-4 flex flex-wrap gap-3">
        <div class="rounded-xl border border-gray-100 bg-white px-5 py-3 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-400">Days</p>
            <p class="text-xl font-extrabold text-[var(--color-heading)]">{{ $totals['days'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white px-5 py-3 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-400">Total worked</p>
            <p class="text-xl font-extrabold text-[var(--color-heading)]">{{ \App\Models\Attendance::minutesLabel((int) $totals['worked']) }}</p>
        </div>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <input type="date" name="from" value="{{ $from }}" class="h-10 rounded-lg border-gray-200 text-sm">
        <input type="date" name="to" value="{{ $to }}" class="h-10 rounded-lg border-gray-200 text-sm">
        @if ($scopeAll)
            <select name="user" class="h-10 rounded-lg border-gray-200 text-sm">
                <option value="">All employees</option>
                @foreach ($staff as $p)<option value="{{ $p->id }}" @selected((int) request('user') === $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        @endif
        <select name="method" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="">All methods</option>
            @foreach (\App\Models\Attendance::METHODS as $k => $v)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="status" class="h-10 rounded-lg border-gray-200 text-sm">
            <option value="">All statuses</option>
            @foreach (\App\Models\Attendance::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
        </select>
        <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Filter</button>
        @if (request()->hasAny(['from', 'to', 'user', 'method', 'status']))
            <a href="{{ route('admin.attendance.history') }}" class="text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">Clear</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm" style="min-width:900px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Date</th>
                    @if ($scopeAll)<th class="px-5 py-3 font-semibold">Employee</th>@endif
                    <th class="px-5 py-3 font-semibold">Check In</th>
                    <th class="px-5 py-3 font-semibold">Check Out</th>
                    <th class="px-5 py-3 font-semibold">Worked</th>
                    <th class="px-5 py-3 font-semibold">Late</th>
                    <th class="px-5 py-3 font-semibold">Overtime</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $a)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ $a->work_date?->format('d M Y') }}</td>
                        @if ($scopeAll)<td class="px-5 py-3 text-[var(--color-muted)]">{{ $a->user?->name ?? '—' }}</td>@endif
                        <td class="px-5 py-3">
                            {{ $a->check_in_at?->format('g:i A') ?? '—' }}
                            @include('admin.attendance._method-badge', ['method' => $a->check_in_method])
                        </td>
                        <td class="px-5 py-3">
                            {{ $a->check_out_at?->format('g:i A') ?? '—' }}
                            @include('admin.attendance._method-badge', ['method' => $a->check_out_method])
                        </td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $a->workedLabel() }}</td>
                        <td class="px-5 py-3">{!! $a->late_minutes ? '<span class="font-semibold text-amber-600">'.\App\Models\Attendance::minutesLabel($a->late_minutes).'</span>' : '<span class="text-gray-300">—</span>' !!}</td>
                        <td class="px-5 py-3">{!! $a->overtime_minutes ? '<span class="font-semibold text-emerald-600">'.\App\Models\Attendance::minutesLabel($a->overtime_minutes).'</span>' : '<span class="text-gray-300">—</span>' !!}</td>
                        <td class="px-5 py-3">
                            @php $chip = ['present' => 'bg-emerald-50 text-emerald-700', 'late' => 'bg-amber-50 text-amber-700', 'half_day' => 'bg-sky-50 text-sky-700', 'absent' => 'bg-red-50 text-red-600']; @endphp
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $chip[$a->status] ?? 'bg-gray-100 text-gray-500' }}">{{ \App\Models\Attendance::STATUSES[$a->status] ?? $a->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-gray-300">Nothing recorded in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
