@php
    $statusBadge = [
        'new' => 'bg-blue-50 text-blue-700',
        'qualified' => 'bg-emerald-50 text-emerald-700',
        'unqualified' => 'bg-red-50 text-red-600',
    ];
    $q = trim((string) request('search'));
    $me = auth()->user();
    $canCompleteFu = $me->hasPermission('follow_ups.complete');
    $canCreateFu = $me->hasPermission('follow_ups.create');
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
                    <th class="px-4 py-3 font-semibold">Next Follow-up</th>
                    <th class="px-4 py-3 font-semibold">Follow-up</th>
                    <th class="px-4 py-3 font-semibold">Lead Quality</th>
                    <th class="px-4 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($leads as $lead)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.leads.show', $lead) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $lead->lead_code }}</a>
                            <p class="mt-0.5 text-xs text-[var(--color-muted)]" title="Created {{ $lead->created_at->format('d M Y, h:i A') }}">{{ $lead->created_at->format('d M Y') }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.leads.show', $lead) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $lead->full_name }}</a>
                            @php
                                $matches = collect($leadClients[$lead->id] ?? []);
                                $leadClient = $matches->first();
                            @endphp
                            @if ($lead->company_name)
                                <p class="text-xs text-[var(--color-muted)]">{{ $lead->company_name }}</p>
                            @endif
                            @if ($lead->email)
                                <p class="truncate text-xs text-[var(--color-muted)]">{{ $lead->email }}</p>
                            @elseif (! $lead->company_name)
                                <p class="text-xs text-[var(--color-muted)]">—</p>
                            @endif
                            {{-- What they are interested in. --}}
                            <x-admin.interest-labels :model="$lead" class="mt-1" />

                            {{-- Already a client (matched on a shared phone number or email). --}}
                            @if ($leadClient)
                                <a href="{{ $matches->count() > 1 ? route('admin.clients.index', ['search' => $leadClient->email ?: $leadClient->name]) : route('admin.clients.show', $leadClient->id) }}"
                                   title="{{ $matches->count() > 1 ? $matches->count().' clients share this contact: '.$matches->pluck('name')->join(', ') : 'Open client '.$leadClient->name }}"
                                   class="mt-0.5 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 hover:bg-emerald-100">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path stroke-linecap="round" d="M5 20a7 7 0 0 1 14 0"/></svg>
                                    {{ $matches->count() > 1 ? $matches->count().' clients' : 'Client' }}
                                </a>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $fullNumber = trim(($lead->dial_code ? $lead->dial_code : '').$lead->phone);
                                $waNumber = preg_replace('/\D/', '', ($lead->dial_code ?? '').$lead->phone);
                            @endphp
                            @if ($lead->phone)
                                <span class="inline-flex items-center gap-1.5" x-data="{ copied: false }">
                                    @if ($lead->dial_code)
                                        <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-[var(--color-heading)]">{{ $lead->dial_code }}</span>
                                    @endif
                                    @if ($lead->is_whatsapp && $waNumber)
                                        <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-medium text-emerald-600 hover:underline" title="Open in WhatsApp">
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1a10 10 0 0 1-3-1.8 11 11 0 0 1-2.3-2.9c-.5-.8-.6-1.5-.6-1.8 0-.5.5-1.2.8-1.5.2-.2.4-.2.6-.2h.5c.2 0 .4 0 .5.4l.7 1.7c.1.2 0 .4-.1.5l-.4.5c-.1.2-.3.3-.1.6.3.5.8 1.2 1.4 1.7.7.6 1.3.8 1.6 1 .2 0 .4 0 .5-.1l.6-.7c.2-.2.3-.2.5-.1l1.6.8c.2.1.4.2.4.3.1.2.1.6-.1 1.1Z"/></svg>
                                            {{ $lead->phone }}
                                        </a>
                                    @else
                                        <span class="text-[var(--color-muted)]">{{ $lead->phone }}</span>
                                    @endif
                                    {{-- copy: dial code + number together --}}
                                    <button type="button"
                                            @click="navigator.clipboard.writeText(@js($fullNumber)); copied = true; setTimeout(() => copied = false, 1200)"
                                            class="rounded p-1 text-gray-300 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Copy {{ $fullNumber }}">
                                        <svg x-show="!copied" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                        <svg x-show="copied" x-cloak class="h-3.5 w-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>
                                    </button>
                                </span>
                            @else
                                <span class="text-[var(--color-muted)]">—</span>
                            @endif
                            {{-- Country (from the stored country or parsed from the phone) + current local time there --}}
                            @php $geo = \App\Support\CountryTime::forLead($lead->country, $lead->phone); @endphp
                            @if ($geo && $geo['tz'])
                                <p class="mt-1 flex items-center gap-1.5 text-xs text-[var(--color-muted)]">
                                    <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                                    @if ($geo['country'])<span>{{ $geo['country'] }}</span><span class="text-gray-300">·</span>@endif
                                    <span class="tabular-nums font-medium text-[var(--color-heading)]" data-country-tz="{{ $geo['tz'] }}" title="Current local time{{ $geo['country'] ? ' in '.$geo['country'] : '' }}">{{ now()->setTimezone($geo['tz'])->format('h:i A') }}</span>
                                </p>
                            @endif
                        </td>
                        {{-- Next pending follow-up (with a details popover) --}}
                        <td class="px-4 py-3">
                            @if ($lead->nextFollowUp)
                                @php $nfu = $lead->nextFollowUp; @endphp
                                <div class="flex items-center gap-1.5">
                                    <div>
                                        <p class="font-medium {{ $nfu->isOverdue() ? 'text-red-600' : 'text-[var(--color-heading)]' }}">{{ $nfu->scheduled_at->format('d M Y') }}</p>
                                        <p class="text-xs text-[var(--color-muted)]">{{ $nfu->scheduled_at->format('h:i A') }}</p>
                                    </div>
                                    <div class="relative" x-data="{ o: false }" @mouseenter="o = true" @mouseleave="o = false">
                                        <button type="button" @click="o = !o" class="grid h-5 w-5 place-items-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Follow-up details">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v5m0-8h.01"/></svg>
                                        </button>
                                        <div x-show="o" x-cloak @click.outside="o = false"
                                             class="absolute left-1/2 top-full z-30 mt-1.5 w-60 -translate-x-1/2 rounded-xl border border-gray-100 bg-white p-3 text-left shadow-xl">
                                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-400">Next Follow-up</p>
                                            <dl class="space-y-1.5 text-xs">
                                                <div class="flex justify-between gap-2"><dt class="text-gray-400">Type</dt><dd class="font-medium text-[var(--color-heading)]">{{ $nfu->typeLabel() }}</dd></div>
                                                <div class="flex justify-between gap-2"><dt class="text-gray-400">When</dt><dd class="font-medium text-[var(--color-heading)]">{{ $nfu->scheduled_at->format('d M Y, h:i A') }}</dd></div>
                                                <div class="flex justify-between gap-2"><dt class="text-gray-400">Priority</dt><dd class="font-medium text-[var(--color-heading)]">{{ $nfu->priorityLabel() }}</dd></div>
                                                <div class="flex justify-between gap-2"><dt class="text-gray-400">Assigned</dt><dd class="font-medium text-[var(--color-heading)]">{{ $nfu->assignee?->name ?? 'Unassigned' }}</dd></div>
                                                <div class="flex items-center justify-between gap-2"><dt class="text-gray-400">Status</dt><dd><span class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 {{ $nfu->statusBadge() }}">{{ $nfu->statusLabel() }}</span></dd></div>
                                            </dl>
                                            @if ($nfu->note)
                                                <p class="mt-2 border-t border-gray-50 pt-2 text-xs text-[var(--color-muted)]">{{ $nfu->note }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        {{-- Latest follow-up status — click a pending one to Mark Done right from the list --}}
                        <td class="px-4 py-3">
                            @php $fuStatus = $lead->followUpStatus(); @endphp
                            @if ($lead->nextFollowUp && $canCompleteFu)
                                <button type="button" x-data
                                        @click="$dispatch('open-done', { action: '{{ route('admin.leads.follow-ups.complete', [$lead, $lead->nextFollowUp]) }}', leadName: @js($lead->full_name), followUpTitle: @js($lead->nextFollowUp->typeLabel().' · '.$lead->nextFollowUp->scheduled_at->format('d M Y')) })"
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 transition hover:opacity-80 {{ $lead->nextFollowUp->statusBadge() }}" title="Mark this follow-up done">
                                    {{ $lead->nextFollowUp->statusLabel() }}
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                                </button>
                            @elseif ($fuStatus)
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $fuStatus->statusBadge() }}">{{ $fuStatus->statusLabel() }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        {{-- Lead status/quality --}}
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
                                    @if ($canCreateFu)
                                        <button type="button"
                                                @click="open = false; $dispatch('open-schedule', { action: '{{ route('admin.leads.follow-ups.store', $lead) }}', leadName: @js($lead->full_name) })"
                                                class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" d="M12 13v4M10 15h4"/></svg> Add Follow-up
                                        </button>
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
