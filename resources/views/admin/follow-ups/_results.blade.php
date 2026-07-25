@php
    $priorityBadge = ['high' => 'bg-red-50 text-red-600', 'medium' => 'bg-amber-50 text-amber-700', 'low' => 'bg-gray-100 text-gray-500'];
    $typeIcon = [
        'call' => 'M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z',
        'whatsapp' => 'M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Z',
        'meeting' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        'email' => 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2 8 6 8-6',
        'sms' => 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z',
        'other' => 'M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    ];
    $me = auth()->user();
    $canComplete = $me->hasPermission('follow_ups.complete');
    $canCreate = $me->hasPermission('follow_ups.create');
    $canEdit = $me->hasPermission('follow_ups.edit');
    $canDelete = $me->hasPermission('follow_ups.delete');
@endphp

<div class="rounded-xl border border-gray-100 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-4 py-3 font-semibold">Lead</th>
                    <th class="px-4 py-3 font-semibold">Company</th>
                    <th class="px-4 py-3 font-semibold">Contact</th>
                    <th class="px-4 py-3 font-semibold">Type</th>
                    <th class="px-4 py-3 font-semibold">Assigned</th>
                    <th class="px-4 py-3 font-semibold">Scheduled</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Priority</th>
                    <th class="px-4 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($followUps as $f)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.leads.show', $f->lead_id) }}" class="font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $f->lead?->full_name ?? '—' }}</a>
                        </td>
                        <td class="px-4 py-3 text-[var(--color-muted)]">{{ $f->lead?->company_name ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($f->lead?->phone)
                                <span class="text-[var(--color-muted)]">{{ trim(($f->lead->dial_code ?? '').' '.$f->lead->phone) }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 font-medium text-[var(--color-heading)]">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $typeIcon[$f->type] ?? $typeIcon['other'] }}"/></svg>
                                {{ $f->typeLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($f->assignee)
                                <div class="flex items-center gap-2">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[11px] font-bold text-[var(--color-primary)]">{{ strtoupper(substr($f->assignee->name, 0, 1)) }}</span>
                                    <span class="text-[var(--color-heading)]">{{ $f->assignee->name }}</span>
                                </div>
                            @else
                                <span class="text-gray-400">Unassigned</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-[var(--color-heading)]">{{ $f->scheduled_at->format('d M Y') }}</p>
                            <p class="text-xs text-[var(--color-muted)]">{{ $f->scheduled_at->format('h:i A') }}</p>
                        </td>
                        <td class="px-4 py-3">
                            @if ($canComplete && $f->isPending())
                                <button type="button" x-data
                                        @click="$dispatch('open-done', { action: '{{ route('admin.leads.follow-ups.complete', [$f->lead_id, $f->id]) }}', leadName: @js($f->lead?->full_name), followUpTitle: @js($f->typeLabel().' · '.$f->scheduled_at->format('d M Y')) })"
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 transition hover:opacity-80 {{ $f->statusBadge() }}" title="Mark this follow-up done">
                                    {{ $f->statusLabel() }}
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                                </button>
                            @else
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $f->statusBadge() }}">{{ $f->statusLabel() }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $priorityBadge[$f->priority] ?? 'bg-gray-100 text-gray-500' }}">{{ $f->priorityLabel() }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{-- Teleported to <body> so the menu is never clipped by the table's overflow. --}}
                            <div class="relative inline-block text-left" x-data="rowMenu()">
                                <button type="button" @click="toggle($event)" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-gray-100" title="Actions">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                </button>
                                <template x-teleport="body">
                                    <div x-show="open" x-cloak>
                                        <div class="fixed inset-0 z-50" @click="open = false"></div>
                                        <div x-ref="menu" :style="`position:fixed; top:${y}px; left:${x}px`" class="z-[60] w-48 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 text-left shadow-xl">
                                            <a href="{{ route('admin.leads.show', $f->lead_id) }}" class="flex items-center gap-2.5 px-3 py-2 text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="2.5"/></svg> View Lead
                                            </a>
                                            @if ($canEdit && $f->isPending())
                                                <button type="button"
                                                        @click="open = false; $dispatch('open-schedule', { action: '{{ route('admin.leads.follow-ups.update', [$f->lead_id, $f->id]) }}', method: 'PUT', leadName: @js($f->lead?->full_name), type: @js($f->type), priority: @js($f->priority), user_id: {{ $f->user_id ?? 'null' }}, scheduled_at: @js($f->scheduled_at->format('Y-m-d H:i:s')), note: @js($f->note) })"
                                                        class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg> Edit
                                                </button>
                                            @endif
                                            @if ($canCreate)
                                                <button type="button"
                                                        @click="open = false; $dispatch('open-schedule', { action: '{{ route('admin.leads.follow-ups.store', $f->lead_id) }}', leadName: @js($f->lead?->full_name) })"
                                                        class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" d="M12 13v4M10 15h4"/></svg> Add Follow-up
                                                </button>
                                            @endif
                                            @if ($canDelete && $f->isPending())
                                                <div class="my-1 border-t border-gray-100"></div>
                                                <form method="POST" action="{{ route('admin.leads.follow-ups.destroy', [$f->lead_id, $f->id]) }}" onsubmit="return confirm('Delete this follow-up?')" data-turbo="false">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg> Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-gray-400">No follow-ups here. Schedule one from a lead's detail page.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
    <div class="flex items-center gap-4 text-sm text-[var(--color-muted)]">
        <span>Showing <span class="font-semibold text-[var(--color-heading)]">{{ $followUps->count() ? $followUps->firstItem() : 0 }}</span>–<span class="font-semibold text-[var(--color-heading)]">{{ $followUps->lastItem() ?? 0 }}</span> of <span class="font-semibold text-[var(--color-heading)]">{{ $followUps->total() }}</span></span>
        <form method="GET" class="flex items-center gap-2">
            @foreach (request()->except('per_page', 'page') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <label class="hidden sm:inline">Show</label>
            <select name="per_page" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                @foreach ([10, 25, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach
            </select>
        </form>
    </div>
    <div>{{ $followUps->links('admin.partials._pagination') }}</div>
</div>
