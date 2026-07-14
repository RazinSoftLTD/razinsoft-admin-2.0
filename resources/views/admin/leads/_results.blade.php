@php
    $statusBadge = [
        'new' => 'bg-blue-50 text-blue-700',
        'qualified' => 'bg-emerald-50 text-emerald-700',
        'unqualified' => 'bg-red-50 text-red-600',
    ];
    $q = trim((string) request('search'));
@endphp

@if ($q !== '')
    <p class="mb-3 text-sm text-[var(--color-muted)]">
        <span class="font-semibold text-[var(--color-heading)]">{{ $leads->total() }}</span> result{{ $leads->total() === 1 ? '' : 's' }} for “<span class="font-semibold text-[var(--color-heading)]">{{ $q }}</span>”
    </p>
@endif

{{-- Table --}}
<div class="rounded-xl border border-gray-100 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-4 py-3 font-semibold">Lead ID</th>
                    <th class="px-4 py-3 font-semibold">Lead</th>
                    <th class="px-4 py-3 font-semibold">Phone</th>
                    <th class="px-4 py-3 font-semibold">Lead Quality</th>
                    <th class="px-4 py-3 font-semibold">Assigned To</th>
                    <th class="px-4 py-3 font-semibold">Created</th>
                    <th class="px-4 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($leads as $lead)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.leads.show', $lead) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $lead->lead_code }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.leads.show', $lead) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $lead->full_name }}</a>
                            <p class="text-xs text-[var(--color-muted)]">{{ $lead->company_name ?: ($lead->email ?: '—') }}</p>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $phoneDisplay = trim(($lead->dial_code ?? '').' '.$lead->phone);
                                $waNumber = preg_replace('/\D/', '', ($lead->dial_code ?? '').$lead->phone);
                            @endphp
                            @if ($lead->is_whatsapp && $waNumber)
                                <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-medium text-emerald-600 hover:underline" title="Open in WhatsApp">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1a10 10 0 0 1-3-1.8 11 11 0 0 1-2.3-2.9c-.5-.8-.6-1.5-.6-1.8 0-.5.5-1.2.8-1.5.2-.2.4-.2.6-.2h.5c.2 0 .4 0 .5.4l.7 1.7c.1.2 0 .4-.1.5l-.4.5c-.1.2-.3.3-.1.6.3.5.8 1.2 1.4 1.7.7.6 1.3.8 1.6 1 .2 0 .4 0 .5-.1l.6-.7c.2-.2.3-.2.5-.1l1.6.8c.2.1.4.2.4.3.1.2.1.6-.1 1.1Z"/></svg>
                                    {{ $phoneDisplay }}
                                </a>
                            @else
                                <span class="text-[var(--color-muted)]">{{ $phoneDisplay ?: '—' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('admin.leads.status', $lead) }}">
                                @csrf
                                <select name="lead_status" onchange="this.form.submit()" title="Change status"
                                        class="cursor-pointer appearance-none rounded-full border-0 py-1 pl-2.5 pr-6 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] {{ $statusBadge[$lead->lead_status] ?? 'bg-gray-100 text-gray-600' }}"
                                        style="background-image:url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%223%22 viewBox=%220 0 24 24%22><path d=%22m6 9 6 6 6-6%22/></svg>');background-repeat:no-repeat;background-position:right 0.4rem center;background-size:0.7em;">
                                    @foreach (\App\Models\Lead::STATUSES as $sk => $sl)<option value="{{ $sk }}" @selected($lead->lead_status === $sk)>{{ $sl }}</option>@endforeach
                                </select>
                            </form>
                        </td>
                        <td class="px-4 py-3">
                            @if ($lead->assignee)
                                <div class="flex items-center gap-2">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[11px] font-bold text-[var(--color-primary)]">{{ strtoupper(substr($lead->assignee->name, 0, 1)) }}</span>
                                    <span class="text-[var(--color-heading)]">{{ $lead->assignee->name }}</span>
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-[var(--color-muted)]">
                            <p>{{ $lead->created_at->format('d M Y') }}</p>
                            <p class="text-xs">{{ $lead->created_at->format('h:i A') }}</p>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @php $me = auth()->user(); @endphp
                            <div class="relative inline-block text-left" x-data="{ open: false }">
                                <button type="button" @click="open = !open" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-gray-100" title="Actions">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                </button>
                                <div x-show="open" @click.outside="open = false" x-cloak
                                     class="absolute right-0 z-20 mt-1 w-48 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 text-left shadow-lg">
                                    <a href="{{ route('admin.leads.show', $lead) }}" class="flex items-center gap-2.5 px-3 py-2 text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="2.5"/></svg> View
                                    </a>
                                    @if ($me->allows('leads', 'edit'))
                                        <a href="{{ route('admin.leads.edit', $lead) }}" class="flex items-center gap-2.5 px-3 py-2 text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg> Edit
                                        </a>
                                    @endif
                                    @if ($me->allows('deals', 'create'))
                                        <form method="POST" action="{{ route('admin.leads.convert-deal', $lead) }}">
                                            @csrf
                                            <button type="submit" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l4-4 3 3 5-6"/></svg> Convert Deal
                                            </button>
                                        </form>
                                    @endif
                                    @if ($me->allows('leads', 'edit'))
                                        @if ($lead->isConverted())
                                            <span class="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg> Already a Client</span>
                                        @else
                                            <form method="POST" action="{{ route('admin.leads.convert', $lead) }}" onsubmit="return confirm('Convert this lead into a client?')">
                                                @csrf
                                                <button type="submit" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0"/></svg> Convert Client
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                    @if ($me->allows('leads', 'delete'))
                                        <div class="my-1 border-t border-gray-100"></div>
                                        <form method="POST" action="{{ route('admin.leads.destroy', $lead) }}" onsubmit="return confirm('Delete this lead?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg> Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                            @if ($q !== '')
                                No leads match “<span class="font-semibold text-[var(--color-heading)]">{{ $q }}</span>”. Try a different search.
                            @else
                                No leads found — <a href="{{ route('admin.leads.create') }}" class="font-semibold text-[var(--color-primary)] hover:underline">add your first lead</a>.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Footer: row count · per-page · pagination --}}
<div class="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
    <div class="flex items-center gap-4 text-sm text-[var(--color-muted)]">
        <span>Showing <span class="font-semibold text-[var(--color-heading)]">{{ $leads->count() ? $leads->firstItem() : 0 }}</span>–<span class="font-semibold text-[var(--color-heading)]">{{ $leads->lastItem() ?? 0 }}</span> of <span class="font-semibold text-[var(--color-heading)]">{{ $leads->total() }}</span></span>
        <form method="GET" class="flex items-center gap-2">
            @foreach (request()->except('per_page', 'page') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <label class="hidden sm:inline">Show</label>
            <select name="per_page" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                @foreach ([10, 25, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach
            </select>
        </form>
    </div>
    <div>{{ $leads->links('admin.partials._pagination') }}</div>
</div>
