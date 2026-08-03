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
<div x-data="{ form: false, editing: null }" @keydown.escape.window="form = false">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">WhatsApp Traffic</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; WhatsApp Traffic &rsaquo; Before Leads</p>
        </div>

        <form method="GET" class="relative order-last w-full min-w-[12rem] flex-1 lg:order-none lg:mx-2 lg:w-auto">
            @foreach (request()->except('search', 'page') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
            <input name="search" type="text" value="{{ request('search') }}" autocomplete="off"
                   placeholder="Search by number, name, interest or remark…"
                   class="h-11 w-full rounded-lg border border-gray-200 bg-white pl-11 pr-4 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
        </form>

        @if ($can('create'))
            <button type="button" @click="editing = null; form = true"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                Record Enquiry
            </button>
        @endif
    </div>

    {{-- Today at a glance. Deliberately not affected by the filters below: this line answers
         "what came in today", and it should say the same thing whatever the list is showing. --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Enquiries today', $today['total'], null, 'text-[var(--color-heading)]'],
            ['Conversations started', $today['started'], $pct($today['started'], $today['total']).'% replied', 'text-emerald-600'],
            ['Relevant', $today['relevant'], $pct($today['relevant'], $today['total']).'% of traffic', 'text-blue-600'],
            ['Converted to leads', $today['converted'], $pct($today['converted'], $today['relevant']).'% of relevant', 'text-purple-600'],
        ] as [$label, $value, $sub, $tone])
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</p>
                <p class="mt-1.5 text-2xl font-bold {{ $tone }}">{{ number_format($value) }}</p>
                <p class="mt-0.5 text-xs text-[var(--color-muted)]">{{ $sub ?? now()->format('d M Y') }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Per number, today. The reason the module exists: four numbers, four ad budgets. --}}
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
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
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
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
        <form method="GET" class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
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
                                @if ($row->isConverted())
                                    <a href="{{ route('admin.leads.show', $row->lead_id) }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">
                                        {{ $row->lead?->lead_code ?: 'View lead' }}
                                    </a>
                                @else
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($can('convert') && auth()->user()->allows('leads', 'create') && $row->is_relevant)
                                            <form method="POST" action="{{ route('admin.whatsapp-inquiries.convert', $row) }}">
                                                @csrf
                                                <button class="rounded-lg bg-[var(--color-primary-soft)] px-2.5 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-[var(--color-primary)] hover:text-white">Convert</button>
                                            </form>
                                        @endif
                                        @if ($can('edit'))
                                            @php
                                                // Built here rather than inline: Blade cannot parse
                                                // @json with an array literal inside an attribute.
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
                                            <button type="button" @click="editing = {{ $rowJson }}; form = true"
                                                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Edit">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4l10-10a2.8 2.8 0 1 0-4-4L4 16v4Z"/></svg>
                                            </button>
                                        @endif
                                        @if ($can('delete'))
                                            <form method="POST" action="{{ route('admin.whatsapp-inquiries.destroy', $row) }}" onsubmit="return confirm('Remove this enquiry?')">
                                                @csrf @method('DELETE')
                                                <button class="rounded-lg p-1.5 text-gray-300 hover:bg-red-50 hover:text-red-600" title="Remove">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
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
@endsection
