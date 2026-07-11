@extends('admin.layouts.app')
@section('title', $ticket->subject)

@php
    $me = auth()->user();
    $statusChip = fn ($s) => match ($s) {
        'open' => ['Open', 'text-red-600', 'bg-red-500'],
        'pending' => ['Pending', 'text-amber-600', 'bg-amber-400'],
        'resolved' => ['Resolved', 'text-emerald-600', 'bg-emerald-500'],
        default => ['Closed', 'text-gray-500', 'bg-gray-400'],
    };
    [$sl, $sc, $sd] = $statusChip($ticket->status);
    $prioColor = ['urgent' => 'text-red-600', 'high' => 'text-orange-600', 'medium' => 'text-blue-600', 'low' => 'text-gray-500'];

    // Build a single chronological message stream: opening message + replies.
    $stream = collect([[
        'admin' => false,
        'name' => $ticket->client->name ?? 'Customer',
        'message' => $ticket->message,
        'attachment' => $ticket->attachment,
        'at' => $ticket->created_at,
    ]])->concat($ticket->replies->map(fn ($r) => [
        'admin' => $r->is_admin,
        'name' => $r->author->name ?? ($r->is_admin ? 'Support' : ($ticket->client->name ?? 'Customer')),
        'message' => $r->message,
        'attachment' => $r->attachment,
        'at' => $r->created_at,
    ]))->values();

    // "Seen" shows under the last support message once the customer opened the ticket after it.
    $lastAdminIdx = $stream->filter(fn ($m) => $m['admin'])->keys()->last();
    $lastAdminAt = $lastAdminIdx !== null ? $stream[$lastAdminIdx]['at'] : null;
    $customerSaw = $ticket->customer_seen_at && $lastAdminAt && $ticket->customer_seen_at->greaterThanOrEqualTo($lastAdminAt);
@endphp

@section('content')
    <a href="{{ route('admin.tickets.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Tickets
    </a>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        {{-- ===== Chat ===== --}}
        <div class="flex max-h-[calc(100vh-8rem)] flex-col overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            {{-- Chat header --}}
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
                <div class="min-w-0">
                    <h1 class="truncate font-bold text-[var(--color-heading)]">{{ $ticket->subject }}</h1>
                    <p class="text-xs text-[var(--color-muted)]">#{{ $ticket->ticket_number }} · {{ $ticket->categoryLabel() }}</p>
                </div>
                <span class="inline-flex shrink-0 items-center gap-1.5 text-sm font-medium {{ $sc }}"><span class="h-2 w-2 rounded-full {{ $sd }}"></span> {{ $sl }}</span>
            </div>

            {{-- Messages --}}
            <div id="chat-scroll" class="flex-1 space-y-4 overflow-y-auto bg-gray-50/50 px-5 py-5">
                @foreach ($stream as $m)
                    <div class="flex items-end gap-2 {{ $m['admin'] ? 'flex-row-reverse' : '' }}">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-bold {{ $m['admin'] ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-200 text-gray-600' }}">{{ strtoupper(substr($m['name'], 0, 1)) }}</span>
                        <div class="max-w-[75%]">
                            <div class="mb-1 flex items-center gap-2 text-xs text-[var(--color-muted)] {{ $m['admin'] ? 'justify-end' : '' }}">
                                <span class="font-semibold text-[var(--color-heading)]">{{ $m['name'] }}</span>
                                @if ($m['admin'])<span class="rounded-full bg-[var(--color-primary-soft)] px-1.5 text-[10px] font-semibold text-[var(--color-primary)]">Support</span>@endif
                                <span>{{ $m['at']?->diffForHumans() }}</span>
                            </div>
                            <div class="rounded-2xl px-4 py-2.5 text-sm {{ $m['admin'] ? 'rounded-br-sm bg-[var(--color-primary)] text-white' : 'rounded-bl-sm bg-white text-[var(--color-heading)] ring-1 ring-gray-100' }}">
                                <div class="prose prose-sm max-w-none prose-p:my-1 {{ $m['admin'] ? 'prose-invert' : '' }}">{!! $m['message'] !!}</div>@php /* customer HTML is sanitized server-side */ @endphp
                                @if ($m['attachment'])
                                    <a href="{{ asset('storage/'.$m['attachment']) }}" target="_blank" class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold {{ $m['admin'] ? 'text-white/90 hover:text-white' : 'text-[var(--color-primary)] hover:underline' }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21.4 11.1-8.5 8.5a5 5 0 0 1-7-7l8.5-8.5a3 3 0 0 1 4.3 4.3l-8.6 8.5a1 1 0 0 1-1.4-1.4l7.8-7.8"/></svg> Attachment
                                    </a>
                                @endif
                            </div>
                            @if ($lastAdminIdx === $loop->index)
                                <p class="mt-1 text-right text-[11px] {{ $customerSaw ? 'text-[var(--color-primary)]' : 'text-gray-400' }}">
                                    {{ $customerSaw ? '✓✓ Seen'.($ticket->customer_seen_at ? ' · '.$ticket->customer_seen_at->diffForHumans() : '') : '✓ Sent' }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
                <div id="bottom"></div>
            </div>

            {{-- Composer (fixed at the bottom) — rich text editor --}}
            @if ($ticket->status !== 'closed')
                <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data" id="reply-form" class="shrink-0 border-t border-gray-100 bg-white p-3">
                    @csrf
                    @if ($templates->isNotEmpty())
                        <div class="mb-2 flex items-center gap-2">
                            <select id="reply-template" class="h-9 w-56 rounded-lg border border-gray-200 bg-white px-2 text-sm text-[var(--color-muted)] focus:border-[var(--color-primary)] focus:outline-none">
                                <option value="">Insert reply template…</option>
                                @foreach ($templates as $tpl)<option value="{{ $tpl->id }}">{{ $tpl->title }}</option>@endforeach
                            </select>
                        </div>
                        <script>window.__replyTemplates = {!! $templates->mapWithKeys(fn ($t) => [$t->id => $t->body])->toJson() !!};</script>
                    @endif
                    <div class="rounded-xl border border-gray-200">
                        <div id="reply-editor" style="min-height:90px"></div>
                    </div>
                    <textarea name="message" id="reply-input" class="hidden"></textarea>
                    @error('message')<p class="mt-1 px-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <label class="inline-flex cursor-pointer items-center gap-1.5 text-sm text-gray-500 hover:text-[var(--color-heading)]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21.4 11.1-8.5 8.5a5 5 0 0 1-7-7l8.5-8.5a3 3 0 0 1 4.3 4.3l-8.6 8.5a1 1 0 0 1-1.4-1.4l7.8-7.8"/></svg>
                            <span id="attach-name">Attach file</span>
                            <input type="file" name="attachment" class="hidden" onchange="document.getElementById('attach-name').textContent = this.files[0]?.name || 'Attach file'">
                        </label>
                        <button class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg> Send reply
                        </button>
                    </div>
                </form>
            @else
                <p class="shrink-0 border-t border-gray-100 px-5 py-4 text-center text-sm text-[var(--color-muted)]">This ticket is closed. Set it to Open/Pending to reply.</p>
            @endif
        </div>

        {{-- ===== Sidebar ===== --}}
        <aside class="space-y-4">
            {{-- Status & Priority --}}
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Status &amp; Priority</h2>
                <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="space-y-3">
                    @csrf @method('PATCH')
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[var(--color-muted)]">Status</label>
                        <select name="status" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            @foreach (\App\Models\Ticket::STATUSES as $val => $label)<option value="{{ $val }}" @selected($ticket->status === $val)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[var(--color-muted)]">Priority</label>
                        <select name="priority" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            @foreach (\App\Models\Ticket::PRIORITIES as $val => $label)<option value="{{ $val }}" @selected($ticket->priority === $val)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <button class="w-full rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                </form>
            </div>

            {{-- Assign team --}}
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Assigned to</h2>
                <form method="POST" action="{{ route('admin.tickets.assign', $ticket) }}" class="flex gap-2">
                    @csrf @method('PATCH')
                    <select name="assigned_to" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <option value="">Unassigned</option>
                        @foreach ($team as $u)<option value="{{ $u->id }}" @selected($ticket->assigned_to == $u->id)>{{ $u->name }} ({{ ucfirst($u->role) }})</option>@endforeach
                    </select>
                    <button class="shrink-0 rounded-lg border border-gray-200 px-4 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Save</button>
                </form>
                @if ($ticket->assignee)
                    <p class="mt-2 flex items-center gap-2 text-sm text-[var(--color-muted)]">
                        <span class="grid h-6 w-6 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[10px] font-bold text-[var(--color-primary)]">{{ strtoupper(substr($ticket->assignee->name, 0, 1)) }}</span>
                        {{ $ticket->assignee->name }}
                    </p>
                @endif
            </div>

            {{-- Ticket meta --}}
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm text-sm">
                <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Details</h2>
                <div class="space-y-2">
                    <div class="flex justify-between gap-3"><span class="text-[var(--color-muted)]">Priority</span><span class="font-semibold {{ $prioColor[$ticket->priority] ?? '' }}">{{ $ticket->priorityLabel() }}</span></div>
                    <div class="flex justify-between gap-3"><span class="text-[var(--color-muted)]">Category</span><span class="font-medium text-[var(--color-heading)]">{{ $ticket->categoryLabel() }}</span></div>
                    <div class="flex justify-between gap-3"><span class="text-[var(--color-muted)]">Opened</span><span class="font-medium text-[var(--color-heading)]">{{ $ticket->created_at->format('d M Y') }}</span></div>
                </div>
            </div>

            {{-- Customer --}}
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Customer</h2>
                <p class="text-sm font-semibold text-[var(--color-heading)]">{{ $ticket->client->name ?? '—' }}</p>
                <p class="text-sm text-[var(--color-muted)]">{{ $ticket->client->email ?? '' }}</p>
                @if ($ticket->client)<a href="{{ route('admin.clients.show', $ticket->client) }}" class="mt-3 inline-block text-sm font-semibold text-[var(--color-primary)] hover:underline">View client →</a>@endif
            </div>

            {{-- Delete — super admin only --}}
            @if ($me->isAdmin())
                <form method="POST" action="{{ route('admin.tickets.destroy', $ticket) }}" onsubmit="return confirm('Delete this ticket permanently?')">
                    @csrf @method('DELETE')
                    <button class="w-full rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Delete ticket</button>
                </form>
            @endif
        </aside>
    </div>

    {{-- Rich-text reply editor (Quill) + chat autoscroll --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script>
        (function () {
            // Scroll the conversation to the newest message.
            const scroll = document.getElementById('chat-scroll');
            if (scroll) scroll.scrollTop = scroll.scrollHeight;

            const el = document.getElementById('reply-editor');
            const input = document.getElementById('reply-input');
            const form = document.getElementById('reply-form');
            if (!el || !input || !form) return;

            if (typeof Quill === 'undefined') {
                // CDN failed — fall back to a plain textarea.
                input.classList.remove('hidden');
                input.placeholder = 'Type your reply…';
                input.rows = 3;
                el.parentElement.classList.add('hidden');
                return;
            }

            const quill = new Quill('#reply-editor', {
                theme: 'snow',
                placeholder: 'Write a reply…',
                modules: {
                    toolbar: [
                        [{ header: [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ align: [] }],
                        [{ color: [] }, { background: [] }],
                        ['link', 'image', 'video'],
                        ['clean'],
                    ],
                },
            });

            form.addEventListener('submit', function (e) {
                const html = quill.getText().trim().length ? quill.root.innerHTML : '';
                if (!html) { e.preventDefault(); return; }
                input.value = html;
            });

            // Real-time: append incoming replies pushed via Pusher.
            window.__ticketAppendReply = function (d) {
                const scroll = document.getElementById('chat-scroll');
                if (!scroll || !d) return;
                const mine = !!d.is_admin; // support messages sit on the right in the admin view
                const wrap = document.createElement('div');
                wrap.className = 'flex items-end gap-2' + (mine ? ' flex-row-reverse' : '');
                const initial = (d.author || '?').charAt(0).toUpperCase();
                wrap.innerHTML =
                    '<span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-bold ' + (mine ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-200 text-gray-600') + '">' + initial + '</span>' +
                    '<div class="max-w-[75%]">' +
                    '<div class="mb-1 flex items-center gap-2 text-xs text-[var(--color-muted)] ' + (mine ? 'justify-end' : '') + '"><span class="font-semibold text-[var(--color-heading)]">' + (d.author || '') + '</span>' + (mine ? '<span class="rounded-full bg-[var(--color-primary-soft)] px-1.5 text-[10px] font-semibold text-[var(--color-primary)]">Support</span>' : '') + '<span>just now</span></div>' +
                    '<div class="rounded-2xl px-4 py-2.5 text-sm ' + (mine ? 'rounded-br-sm bg-[var(--color-primary)] text-white' : 'rounded-bl-sm bg-white text-[var(--color-heading)] ring-1 ring-gray-100') + '"><div class="prose prose-sm max-w-none ' + (mine ? 'prose-invert' : '') + '">' + (d.message || '') + '</div>' +
                    (d.attachment ? '<a href="' + d.attachment + '" target="_blank" class="mt-2 inline-flex text-xs font-semibold ' + (mine ? 'text-white/90' : 'text-[var(--color-primary)]') + '">Attachment</a>' : '') +
                    '</div></div>';
                const anchor = document.getElementById('bottom');
                scroll.insertBefore(wrap, anchor);
                scroll.scrollTop = scroll.scrollHeight;
            };

            // Insert a reply template into the editor (appended at the cursor / end).
            const tplSel = document.getElementById('reply-template');
            if (tplSel && window.__replyTemplates) {
                tplSel.addEventListener('change', function () {
                    const body = window.__replyTemplates[this.value];
                    if (!body) return;
                    const range = quill.getSelection(true);
                    quill.clipboard.dangerouslyPasteHTML(range ? range.index : quill.getLength(), body);
                    this.value = '';
                    quill.focus();
                });
            }
        })();
    </script>

    {{-- Real-time via Laravel Reverb (Pusher protocol). Enabled when a Reverb key is set. --}}
    @php $reverb = config('broadcasting.connections.reverb'); @endphp
    @if (! empty($reverb['key']))
        <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
        <script>
            (function () {
                try {
                    const pusher = new Pusher(@json($reverb['key']), {
                        wsHost: @json($reverb['options']['host'] ?? 'localhost'),
                        wsPort: {{ (int) ($reverb['options']['port'] ?? 8080) }},
                        wssPort: {{ (int) ($reverb['options']['port'] ?? 8080) }},
                        forceTLS: {{ ($reverb['options']['useTLS'] ?? false) ? 'true' : 'false' }},
                        enabledTransports: ['ws', 'wss'],
                        cluster: '',
                        disableStats: true,
                    });
                    const channel = pusher.subscribe('tickets.{{ $ticket->id }}');
                    channel.bind('reply.posted', function (data) {
                        if (window.__ticketAppendReply) window.__ticketAppendReply(data);
                    });
                } catch (e) { /* Reverb not reachable — ignore */ }
            })();
        </script>
    @endif
@endsection
