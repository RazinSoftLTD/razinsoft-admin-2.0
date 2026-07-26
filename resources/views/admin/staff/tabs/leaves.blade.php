@php $chip = ['approved' => 'bg-emerald-50 text-emerald-700', 'pending' => 'bg-amber-50 text-amber-700', 'rejected' => 'bg-red-50 text-red-600']; @endphp

<div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
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
                <tr><td colspan="6" class="px-5 py-12 text-center text-gray-300">No leave requests.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $leaves->links() }}</div>
