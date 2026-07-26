<div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
    <table class="w-full text-left text-sm" style="min-width:720px">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
            <tr>
                <th class="px-5 py-3 font-semibold">Subject</th>
                <th class="px-5 py-3 font-semibold">Client</th>
                <th class="px-5 py-3 font-semibold">Priority</th>
                <th class="px-5 py-3 font-semibold">Status</th>
                <th class="px-5 py-3 font-semibold">Opened</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($tickets as $t)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <a href="{{ route('admin.tickets.show', $t) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ \Illuminate\Support\Str::limit($t->subject, 60) }}</a>
                    </td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $t->client?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ ucfirst($t->priority ?? '—') }}</td>
                    <td class="px-5 py-3"><x-admin.status :status="$t->status" /></td>
                    <td class="px-5 py-3 text-[var(--color-muted)]">{{ $t->created_at?->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-12 text-center text-gray-300">No tickets assigned.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $tickets->links() }}</div>
