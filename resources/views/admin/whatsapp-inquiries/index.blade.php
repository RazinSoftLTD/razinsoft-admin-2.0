@extends('admin.layouts.app')
@section('title', 'WhatsApp Traffic')

@php
    $can = fn ($a) => auth()->user()->allows('whatsapp_inquiries', $a);
    // Percentages are the whole point of the header — a raw count of started conversations says
    // nothing without the total it came from.
    $pct = fn ($n, $of) => $of > 0 ? round($n * 100 / $of) : 0;
    $rangeIsToday = $from === $to && $from === now()->toDateString();
@endphp

@section('content')
@php
    $activeFilters = collect(request()->only(['account', 'started', 'relevant', 'interest']))
        ->filter(fn ($v) => $v !== null && $v !== '')->count()
        + ($rangeIsToday ? 0 : 1);
@endphp
<div x-data="{ form: false, editing: null, panel: false }" @keydown.escape.window="form = false; panel = false">

    {{-- Header. Title, the four figures, search, then the filter — reading order matches how the
         page is used: see the day, look for something, narrow it down. --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">WhatsApp Traffic</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; WhatsApp Traffic &rsaquo; Before Leads</p>
        </div>

        @if ($can('create'))
            <button type="button" @click="editing = null; form = true"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                Record Enquiry
            </button>
        @endif
    </div>

    {{-- Today's four figures, search and the filter on one line. The figures are not affected by
         the filter: this row answers "what came in today", and narrowing the list below should not
         change what it says. --}}
    <div class="mb-4 flex flex-wrap items-stretch gap-3">
        @foreach ([
            ['Conversation Today', $today['total'], now()->format('d M Y'), 'text-[var(--color-heading)]'],
            ['Conversion Rate', $pct($today['started'], $today['total']).'%', $today['started'].' of '.$today['total'].' replied', 'text-emerald-600'],
            ['Relevant', $today['relevant'], $pct($today['relevant'], $today['total']).'% of traffic', 'text-blue-600'],
            ['Converted Lead', $today['converted'], $pct($today['converted'], $today['relevant']).'% of relevant', 'text-purple-600'],
        ] as [$label, $value, $sub, $tone])
            <div class="flex min-w-[10rem] flex-1 flex-col items-center justify-center rounded-xl border border-gray-100 bg-white px-4 py-3 text-center shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-xl font-bold {{ $tone }}">{{ is_int($value) ? number_format($value) : $value }}</p>
                <p class="mt-0.5 text-[11px] text-[var(--color-muted)]">{{ $sub }}</p>
            </div>
        @endforeach

        <form method="GET" class="relative min-w-[14rem] flex-1">
            @foreach (request()->except('search', 'page') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
            <input name="search" type="text" value="{{ request('search') }}" autocomplete="off"
                   placeholder="Search number, name, interest…"
                   class="h-full min-h-[4.25rem] w-full rounded-xl border border-gray-100 bg-white pl-11 pr-4 text-sm shadow-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
        </form>

        {{-- Last, on the right: the day is read first, the filter is what you reach for after. --}}
        <button type="button" @click="panel = true" title="Insights & filters"
                class="relative grid w-14 shrink-0 place-items-center rounded-xl border border-gray-100 bg-white text-[var(--color-heading)] shadow-sm hover:bg-gray-50">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M6 12h12M9 18h6"/></svg>
            @if ($activeFilters)
                <span class="absolute right-1.5 top-1.5 grid h-5 min-w-5 place-items-center rounded-full bg-[var(--color-primary)] px-1 text-[10px] font-bold text-white">{{ $activeFilters }}</span>
            @endif
        </button>
    </div>

    {{-- Insights & filters --}}
    <div x-show="panel" x-cloak class="fixed inset-0 z-40">
        <div x-show="panel" x-transition.opacity @click="panel = false" class="absolute inset-0 bg-black/30"></div>
        <div x-show="panel"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             class="absolute right-0 top-0 flex h-full w-96 max-w-full flex-col bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Insights &amp; filters</h2>
                <button type="button" @click="panel = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-gray-100">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto">
        {{-- Per number, today. The reason the module exists: four numbers, four ad budgets. --}}
        <div class="border-b border-gray-100 p-5">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Today by number</h2>
            <div class="mt-3 space-y-2.5">
                @forelse ($accounts as $a)
                    @php $row = $todayByNumber[$a->id] ?? null; $n = (int) ($row->total ?? 0); @endphp
                    <div>
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <span class="min-w-0 truncate font-semibold text-[var(--color-heading)]">{{ $a->name }}</span>
                            <span class="shrink-0 font-bold text-[var(--color-heading)]">{{ $n }}</span>
                        </div>
                        <div class="mt-1 flex items-center gap-2">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                                <span class="block h-full rounded-full bg-[var(--color-primary)]" style="width: {{ $pct($n, max($today['total'], 1)) }}%"></span>
                            </div>
                            <span class="shrink-0 text-[11px] text-[var(--color-muted)]">{{ (int) ($row->started ?? 0) }} replied</span>
                        </div>
                        <p class="mt-0.5 text-[11px] text-gray-400">{{ $a->display_number ?: 'number not set' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No WhatsApp numbers configured yet.</p>
                @endforelse
            </div>
        </div>

        {{-- What they asked about, over whatever range is being viewed. --}}
        <div class="border-b border-gray-100 p-5">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Interested in</h2>
            <p class="mt-0.5 text-[11px] text-gray-400">{{ $rangeIsToday ? 'Today' : \Illuminate\Support\Carbon::parse($from)->format('d M').' – '.\Illuminate\Support\Carbon::parse($to)->format('d M Y') }}</p>
            <div class="mt-3 space-y-2">
                @forelse ($interests as $i)
                    <div class="flex items-center gap-2">
                        <span class="min-w-0 flex-1 truncate text-sm text-[var(--color-heading)]">{{ $i->interest }}</span>
                        <div class="h-1.5 w-20 overflow-hidden rounded-full bg-gray-100">
                            <span class="block h-full rounded-full bg-emerald-500" style="width: {{ $pct($i->total, max($interests->max('total'), 1)) }}%"></span>
                        </div>
                        <span class="w-6 shrink-0 text-right text-xs font-bold text-[var(--color-heading)]">{{ $i->total }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Nothing recorded for this range yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" class="border-b border-gray-100 p-5">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Filter</h2>
            <div class="mt-3 space-y-3">
                <div class="grid grid-cols-2 gap-2">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold text-gray-400">From</span>
                        <input type="date" name="from" value="{{ $from }}" class="h-9 w-full rounded-lg border border-gray-200 px-2 text-xs focus:border-[var(--color-primary)] focus:outline-none">
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold text-gray-400">To</span>
                        <input type="date" name="to" value="{{ $to }}" class="h-9 w-full rounded-lg border border-gray-200 px-2 text-xs focus:border-[var(--color-primary)] focus:outline-none">
                    </label>
                </div>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold text-gray-400">Number</span>
                    <select name="account" class="h-9 w-full rounded-lg border border-gray-200 px-2 text-xs focus:border-[var(--color-primary)] focus:outline-none">
                        <option value="">All numbers</option>
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}" @selected(request('account') == $a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold text-gray-400">Conversation</span>
                        <select name="started" class="h-9 w-full rounded-lg border border-gray-200 px-2 text-xs focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">Any</option>
                            <option value="yes" @selected(request('started') === 'yes')>Started</option>
                            <option value="no" @selected(request('started') === 'no')>Not started</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold text-gray-400">Relevant</span>
                        <select name="relevant" class="h-9 w-full rounded-lg border border-gray-200 px-2 text-xs focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">Any</option>
                            <option value="yes" @selected(request('relevant') === 'yes')>Yes</option>
                            <option value="no" @selected(request('relevant') === 'no')>No</option>
                        </select>
                    </label>
                </div>
                <div class="flex gap-2">
                    <button class="flex-1 rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply</button>
                    <a href="{{ route('admin.whatsapp-inquiries.index') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-[var(--color-muted)] hover:bg-gray-50">Reset</a>
                </div>
                @unless ($rangeIsToday)
                    <p class="rounded-lg bg-gray-50 px-3 py-2 text-[11px] text-[var(--color-muted)]">
                        Showing {{ number_format($rangeSummary['total']) }} enquiries in this range —
                        {{ $rangeSummary['started'] }} started, {{ $rangeSummary['relevant'] }} relevant.
                    </p>
                @endunless
            </div>
        </form>
            </div>
        </div>
    </div>

    {{-- The list --}}
    <div class="mt-4 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Client number</th>
                        <th class="px-4 py-3 text-left">Contact Number</th>
                        <th class="px-4 py-3 text-center">Conversation</th>
                        <th class="px-4 py-3 text-center">Relevant</th>
                        <th class="px-4 py-3 text-left">Interested in</th>
                        <th class="px-4 py-3 text-left">Remarks</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($inquiries as $i => $row)
                        <tr class="hover:bg-gray-50/60">
                            {{-- The serial is the position in the list, not the id: the id jumps
                                 when a row is removed, and this column is read as a count. --}}
                            <td class="px-4 py-3 text-[var(--color-muted)]">{{ $inquiries->firstItem() + $i }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $row->inquiry_date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="font-semibold text-[var(--color-heading)]">{{ $row->client_number }}</span>
                                @if ($row->client_name)<span class="block text-xs text-[var(--color-muted)]">{{ $row->client_name }}</span>@endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="text-[var(--color-heading)]">{{ $row->account?->name ?? '—' }}</span>
                                <span class="block text-xs text-gray-400">{{ $row->companyNumberLabel() }}</span>
                            </td>
                            {{-- Both statuses flip in place. They change through the day — someone
                                 replies, someone decides it is worth chasing — and burying that in
                                 the edit form meant it did not get updated. --}}
                            @php
                                // Class names written out in full, never assembled from parts: this
                                // app ships a pre-built stylesheet and Tailwind only keeps what it
                                // can read literally in the source. The hover shades are inline for
                                // the same reason — they are not in the built file at all.
                                $onCls = ['conversation_started' => 'bg-emerald-50 text-emerald-700', 'is_relevant' => 'bg-blue-50 text-blue-700'];
                                $onHover = ['conversation_started' => '#a7f3d0', 'is_relevant' => '#bfdbfe'];
                            @endphp
                            @foreach ([['conversation_started', $row->conversation_started], ['is_relevant', $row->is_relevant]] as [$field, $on])
                                <td class="px-4 py-3 text-center">
                                    @if ($can('edit'))
                                        <form method="POST" action="{{ route('admin.whatsapp-inquiries.status', $row) }}" class="inline">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="field" value="{{ $field }}">
                                            <input type="hidden" name="value" value="{{ $on ? 0 : 1 }}">
                                            <button type="submit"
                                                    title="{{ $on ? 'Mark as No' : 'Mark as Yes' }}"
                                                    onmouseover="this.style.backgroundColor='{{ $on ? $onHover[$field] : '#e5e7eb' }}'"
                                                    onmouseout="this.style.backgroundColor=''"
                                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold transition {{ $on ? $onCls[$field] : 'bg-gray-100 text-gray-500' }}">
                                                {{ $on ? 'Yes' : 'No' }}
                                                <svg class="h-3 w-3 opacity-40" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 4v6h6M20 20v-6h-6M20 9A8 8 0 0 0 6 6M4 15a8 8 0 0 0 14 3"/></svg>
                                            </button>
                                        </form>
                                    @else
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $on ? $onCls[$field] : 'bg-gray-100 text-gray-500' }}">{{ $on ? 'Yes' : 'No' }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-3">{{ $row->interest ?: '—' }}</td>
                            <td class="max-w-[16rem] px-4 py-3 text-[var(--color-muted)]">
                                <span class="line-clamp-2">{{ $row->remarks ?: '—' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                @php
                                    $mi = 'flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-[var(--color-heading)] hover:bg-gray-50';
                                    $miDanger = 'flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50';
                                    // Built here rather than inline: Blade cannot parse @json with an
                                    // array literal inside an attribute.
                                    $rowJson = \Illuminate\Support\Js::from([
                                        'id' => $row->id,
                                        'inquiry_date' => $row->inquiry_date->toDateString(),
                                        'client_number' => $row->client_number,
                                        'client_name' => $row->client_name,
                                        'whatsapp_account_id' => $row->whatsapp_account_id,
                                        'conversation_started' => $row->conversation_started,
                                        'is_relevant' => $row->is_relevant,
                                        'interest' => $row->interest,
                                        'remarks' => $row->remarks,
                                    ]);
                                @endphp
                                {{-- One menu rather than three buttons in the row: the actions differ
                                     per row (convert only applies to relevant, unconverted enquiries),
                                     so a fixed strip of buttons left ragged gaps down the column. --}}
                                <div x-data="rowMenu()" class="relative inline-block">
                                    <button type="button" @click="toggle($event)" title="Actions"
                                            class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>

                                    {{-- Teleported and fixed-positioned: the table scrolls sideways, and
                                         an overflow container clips a menu that is merely absolute. --}}
                                    <template x-teleport="body">
                                        <div x-show="open" x-cloak>
                                            <div class="fixed inset-0 z-50" @click="open = false"></div>
                                            <div x-ref="menu" :style="`position:fixed; top:${y}px; left:${x}px`"
                                                 class="z-[60] w-52 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 text-left shadow-xl">
                                                @if ($row->isConverted())
                                                    <a href="{{ route('admin.leads.show', $row->lead_id) }}" class="{{ $mi }}">
                                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                                        View lead {{ $row->lead?->lead_code }}
                                                    </a>
                                                @elseif ($can('convert') && auth()->user()->allows('leads', 'create') && $row->is_relevant)
                                                    <form method="POST" action="{{ route('admin.whatsapp-inquiries.convert', $row) }}">
                                                        @csrf
                                                        <button class="{{ $mi }} font-semibold text-[var(--color-primary)]">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h13m0 0-5-5m5 5-5 5"/></svg>
                                                            Convert to Lead
                                                        </button>
                                                    </form>
                                                @endif

                                                @if ($can('edit'))
                                                    <button type="button" @click="open = false; editing = {{ $rowJson }}; form = true" class="{{ $mi }}">
                                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                                                        View / Edit
                                                    </button>
                                                @endif

                                                @if ($can('delete'))
                                                    <div class="my-1 border-t border-gray-100"></div>
                                                    <form method="POST" action="{{ route('admin.whatsapp-inquiries.destroy', $row) }}" onsubmit="return confirm('Remove this enquiry?')">
                                                        @csrf @method('DELETE')
                                                        <button class="{{ $miDanger }}">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                                            Delete
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
                        <tr><td colspan="9" class="px-4 py-12 text-center text-sm text-gray-400">No enquiries recorded for this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($inquiries->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $inquiries->links() }}</div>
        @endif
    </div>

    {{-- Record / edit. One form for both: the fields are identical, and two would drift apart. --}}
    <div x-show="form" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:p-8">
        <div @click.outside="form = false" class="w-full max-w-lg rounded-xl bg-white shadow-xl">
            <form method="POST" :action="editing ? '{{ url('admin/whatsapp-inquiries') }}/' + editing.id : '{{ route('admin.whatsapp-inquiries.store') }}'">
                @csrf
                <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]" x-text="editing ? 'Edit enquiry' : 'Record enquiry'"></h2>
                    <button type="button" @click="form = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Date</span>
                        <input type="date" name="inquiry_date" required :value="editing ? editing.inquiry_date?.slice(0,10) : '{{ now()->toDateString() }}'"
                               class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Our number</span>
                        <select name="whatsapp_account_id" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">— select —</option>
                            @foreach ($accounts as $a)
                                <option value="{{ $a->id }}" :selected="editing && editing.whatsapp_account_id == {{ $a->id }}">{{ $a->name }} @if ($a->display_number)· {{ $a->display_number }}@endif</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Client WhatsApp number</span>
                        <input name="client_number" required placeholder="+8801711…" :value="editing?.client_number ?? ''"
                               class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Client name <span class="text-gray-400">(optional)</span></span>
                        <input name="client_name" :value="editing?.client_name ?? ''"
                               class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Interested service / topic</span>
                        {{-- A list plus free text: a fixed list cannot cover something we do not sell
                             yet, and free text alone would split "Ready POS" from "ready pos" in the
                             report. The suggestions keep most entries spelled the same way. --}}
                        <input name="interest" list="wa-interests" :value="editing?.interest ?? ''"
                               class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <datalist id="wa-interests">
                            @foreach ($interestOptions as $opt)<option value="{{ $opt }}"></option>@endforeach
                        </datalist>
                    </label>

                    <label class="flex items-start gap-2.5">
                        <input type="hidden" name="conversation_started" value="0">
                        <input type="checkbox" name="conversation_started" value="1" :checked="editing ? !!editing.conversation_started : false"
                               class="mt-0.5 h-4 w-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                        <span>
                            <span class="block text-sm font-semibold text-[var(--color-heading)]">Conversation started</span>
                            <span class="block text-xs text-[var(--color-muted)]">Someone from our side replied.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2.5">
                        <input type="hidden" name="is_relevant" value="0">
                        <input type="checkbox" name="is_relevant" value="1" :checked="editing ? !!editing.is_relevant : false"
                               class="mt-0.5 h-4 w-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                        <span>
                            <span class="block text-sm font-semibold text-[var(--color-heading)]">Relevant</span>
                            <span class="block text-xs text-[var(--color-muted)]">Worth converting into a lead.</span>
                        </span>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Remarks <span class="text-gray-400">(optional)</span></span>
                        <textarea name="remarks" rows="3" x-text="editing?.remarks ?? ''"
                                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none"></textarea>
                    </label>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="form = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]" x-text="editing ? 'Save changes' : 'Record enquiry'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
    <style>[x-cloak]{display:none!important}</style>
    <script>
        function rowMenu() {
            return {
                open: false, x: 0, y: 0,
                toggle(e) {
                    if (this.open) { this.open = false; return; }
                    const r = e.currentTarget.getBoundingClientRect();
                    this.x = Math.max(8, r.right - 208);   // 208 = w-52
                    this.y = r.bottom + 4;
                    this.open = true;
                    // Measure once rendered and flip upward when the last rows would push it
                    // off the bottom of the window.
                    this.$nextTick(() => {
                        const m = this.$refs.menu;
                        if (!m) return;
                        const h = m.offsetHeight, vh = window.innerHeight;
                        if (r.bottom + 4 + h > vh - 8) {
                            const above = r.top - 4 - h;
                            this.y = above >= 8 ? above : Math.max(8, vh - h - 8);
                        }
                    });
                },
            };
        }
    </script>
@endsection