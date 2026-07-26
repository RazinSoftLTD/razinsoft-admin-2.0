@php $chip = ['approved' => 'bg-emerald-50 text-emerald-700', 'pending' => 'bg-amber-50 text-amber-700', 'rejected' => 'bg-red-50 text-red-600']; @endphp

<section class="rounded-xl border border-gray-100 bg-white shadow-sm">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
        <h2 class="text-sm font-bold text-[var(--color-heading)]">Leave requests</h2>
        <span class="text-xs text-gray-400">{{ $leaves->total() }} request(s)</span>
    </header>
    <div class="overflow-x-auto">
    <table class="w-full text-left text-sm" style="min-width:760px">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
            <tr>
                <th class="px-5 py-3 font-semibold">Type</th>
                <th class="px-5 py-3 font-semibold">From</th>
                <th class="px-5 py-3 font-semibold">To</th>
                <th class="px-5 py-3 font-semibold">Days</th>
                <th class="px-5 py-3 font-semibold">Reason</th>
                <th class="px-5 py-3 font-semibold">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($leaves as $l)
                @php $days = $l->from_date && $l->to_date ? \Carbon\Carbon::parse($l->from_date)->diffInDays(\Carbon\Carbon::parse($l->to_date)) + 1 : 1; @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ ucfirst(str_replace('_', ' ', $l->leave_type)) }}</td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ \Carbon\Carbon::parse($l->from_date)->format('d M Y') }}</td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ \Carbon\Carbon::parse($l->to_date)->format('d M Y') }}</td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $days }}</td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ \Illuminate\Support\Str::limit($l->reason, 60) ?: '—' }}</td>
                    <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $chip[$l->status] ?? 'bg-gray-100 text-gray-500' }}">{{ ucfirst($l->status) }}</span></td>
                </tr>
            @empty
                @include('admin.staff.tabs._empty', ['cols' => 6, 'icon' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z', 'title' => 'No leave requests', 'hint' => 'Applications from HR &gt; Leave will be listed here.'])
            @endforelse
        </tbody>
    </table>
    </div>
    @if ($leaves->hasPages())
        <div class="border-t border-gray-100 px-5 py-3">{{ $leaves->links() }}</div>
    @endif
</section>
