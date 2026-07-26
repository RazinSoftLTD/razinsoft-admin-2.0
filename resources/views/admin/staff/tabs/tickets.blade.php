<section class="rounded-xl border border-gray-100 bg-white shadow-sm">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
        <h2 class="text-sm font-bold text-[var(--color-heading)]">Assigned tickets</h2>
        <span class="text-xs text-gray-400">{{ $tickets->total() }} ticket(s)</span>
    </header>
    <div class="overflow-x-auto">
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
                @include('admin.staff.tabs._empty', ['cols' => 5, 'icon' => 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7Z', 'title' => 'No tickets assigned', 'hint' => 'Support tickets routed to this employee will be listed here.'])
            @endforelse
        </tbody>
    </table>
    </div>
    @if ($tickets->hasPages())
        <div class="border-t border-gray-100 px-5 py-3">{{ $tickets->links() }}</div>
    @endif
</section>
