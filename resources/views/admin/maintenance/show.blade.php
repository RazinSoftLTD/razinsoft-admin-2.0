@extends('admin.layouts.app')
@section('title', $maintenance->title)

@section('content')
@php
    $can = fn ($a) => auth()->user()->allows('maintenance', $a);
    $m = $maintenance;
    $health = $m->healthLabel();
    $tone = [
        'Active' => 'bg-emerald-50 text-emerald-700',
        'Expiring soon' => 'bg-amber-50 text-amber-700',
        'Expired' => 'bg-red-50 text-red-600',
        'Paused' => 'bg-gray-100 text-gray-500',
        'Ended' => 'bg-gray-100 text-gray-500',
    ];
    $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
@endphp

<div x-data="{ plan: false, editing: null, renew: false }">

<a href="{{ route('admin.maintenance.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg>
    Back to maintenance
</a>

<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
    <div>
        <div class="flex items-center gap-2">
            <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $m->title }}</h1>
            <span class="rounded-lg px-2 py-1 text-xs font-semibold {{ $tone[$health] ?? 'bg-gray-100 text-gray-500' }}">{{ $health }}</span>
        </div>
        <p class="mt-1 text-sm text-[var(--color-muted)]">
            {{ $m->code }} &middot; {{ $m->client?->name }}@if ($m->project) &middot; <a href="{{ route('admin.projects.show', $m->project_id) }}" class="hover:underline">{{ $m->project->name }}</a>@endif
        </p>
    </div>
    <div class="flex items-center gap-2">
        @if ($can('renew'))
            <button type="button" @click="renew = true" class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Renew</button>
        @endif
        @if ($can('edit'))
            <a href="{{ route('admin.maintenance.edit', $m) }}" class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Edit</a>
        @endif
    </div>
</div>

{{-- The renewal notice sits above everything, because a contract that has run out makes the rest
     of the page academic. --}}
@if ($m->needsRenewal())
    <div class="mb-4 rounded-xl border {{ $m->isExpired() ? 'border-red-100 bg-red-50' : 'border-amber-100 bg-amber-50' }} p-4">
        <p class="text-sm font-semibold {{ $m->isExpired() ? 'text-red-700' : 'text-amber-800' }}">
            @if ($m->isExpired())
                This contract ran out on {{ $m->ends_on->format('d M Y') }} — {{ abs($m->daysLeft()) }} days ago.
            @else
                This contract runs out on {{ $m->ends_on->format('d M Y') }}, in {{ $m->daysLeft() }} days.
            @endif
        </p>
        <p class="mt-1 text-sm {{ $m->isExpired() ? 'text-red-600' : 'text-amber-700' }}">
            Ask {{ $m->client?->name }} about the next term.
            @if ($m->assignee) {{ $m->assignee->name }} looks after this one. @endif
            @if ($can('renew')) <button type="button" @click="renew = true" class="font-semibold underline">Record a renewal</button>@endif
        </p>
    </div>
@endif

<div class="grid gap-4 lg:grid-cols-3">
    {{-- Due now --}}
    <div class="lg:col-span-2">
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Due now</h2>
                <span class="text-xs text-[var(--color-muted)]">{{ $due->count() }} outstanding</span>
            </div>

            @if ($due->isEmpty())
                <p class="px-4 py-10 text-center text-sm text-gray-400">
                    @if ($m->status !== 'active') This contract is {{ strtolower($m->status) }}, so nothing is falling due.
                    @elseif ($m->tasks->where('is_active', true)->isEmpty()) No tasks in the plan yet.
                    @else Everything in the plan is up to date. @endif
                </p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($due as $d)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-[var(--color-heading)]">{{ $d['task']->title }}</p>
                                <p class="text-xs text-[var(--color-muted)]">
                                    {{ $d['task']->scheduleLabel() }} &middot; due {{ $d['due_on']->format('D d M Y') }}
                                    @if ($d['days_late'] > 0)
                                        <span class="font-semibold text-red-600">&middot; {{ $d['days_late'] }} {{ Str::plural('day', $d['days_late']) }} late</span>
                                    @endif
                                    @if ($d['task']->assignee) &middot; {{ $d['task']->assignee->name }} @endif
                                </p>
                            </div>
                            @if ($can('complete'))
                                <form method="POST" action="{{ route('admin.maintenance.tasks.complete', [$m, $d['task']]) }}">
                                    @csrf
                                    <input type="hidden" name="due_on" value="{{ $d['due_on']->toDateString() }}">
                                    <button class="rounded-lg bg-[var(--color-primary-soft)] px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-[var(--color-primary)] hover:text-white">Mark done</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- The plan --}}
        <div class="mt-4 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">The plan</h2>
                @if ($can('edit'))
                    <button type="button" @click="editing = null; plan = true" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">Add task</button>
                @endif
            </div>

            @if ($m->tasks->isEmpty())
                <p class="px-4 py-10 text-center text-sm text-gray-400">Nothing planned yet. Add what gets done daily, weekly or monthly.</p>
            @else
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($m->tasks as $t)
                            @php
                                $taskJson = \Illuminate\Support\Js::from([
                                    'id' => $t->id, 'title' => $t->title, 'description' => $t->description,
                                    'frequency' => $t->frequency, 'weekday' => $t->weekday,
                                    'day_of_month' => $t->day_of_month, 'assigned_to' => $t->assigned_to,
                                    'is_active' => $t->is_active,
                                ]);
                                $done = $t->runs->whereNotNull('completed_at');
                            @endphp
                            <tr class="{{ $t->is_active ? '' : 'bg-gray-50' }}">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-[var(--color-heading)]">
                                        {{ $t->title }}
                                        @unless ($t->is_active)<span class="ml-2 rounded bg-gray-200 px-1.5 py-0.5 text-xs font-semibold text-gray-500">paused</span>@endunless
                                    </p>
                                    <p class="text-xs text-[var(--color-muted)]">
                                        {{ $t->scheduleLabel() }}
                                        @if ($t->assignee) &middot; {{ $t->assignee->name }} @endif
                                        &middot; {{ $done->count() }} done
                                        @if ($last = $done->sortByDesc('completed_at')->first()) &middot; last {{ $last->completed_at->format('d M Y') }} @endif
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @if ($can('edit'))
                                        <button type="button" @click="editing = {{ $taskJson }}; plan = true" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">Edit</button>
                                        <form method="POST" action="{{ route('admin.maintenance.tasks.destroy', [$m, $t]) }}" class="inline" onsubmit="return confirm('Remove this task from the plan? Its history goes with it.')">
                                            @csrf @method('DELETE')
                                            <button class="ml-4 text-xs font-semibold text-gray-400 hover:text-red-600">Remove</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Contract facts + renewal history --}}
    <div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Contract</h2>
            <dl class="space-y-2.5 text-sm">
                @foreach ([
                    'Client' => $m->client?->name ?? '—',
                    'Term' => $m->starts_on->format('d M Y').' → '.$m->ends_on->format('d M Y'),
                    'Fee' => $m->fee ? $m->currency.' '.number_format((float) $m->fee, 2).' · '.(\App\Models\MaintenanceProject::CYCLES[$m->billing_cycle] ?? $m->billing_cycle) : '—',
                    'Looked after by' => $m->assignee?->name ?? 'Unassigned',
                ] as $label => $value)
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--color-muted)]">{{ $label }}</dt>
                        <dd class="text-right font-semibold text-[var(--color-heading)]">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($m->scope)
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">What is covered</p>
                    <p class="whitespace-pre-line text-sm text-[var(--color-muted)]">{{ $m->scope }}</p>
                </div>
            @endif
            @if ($m->notes)
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Internal notes</p>
                    <p class="whitespace-pre-line text-sm text-[var(--color-muted)]">{{ $m->notes }}</p>
                </div>
            @endif
        </div>

        <div class="mt-4 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Renewal history</h2>
            @if ($m->renewals->isEmpty())
                <p class="text-sm text-gray-400">Not renewed yet — this is the first term.</p>
            @else
                <ul class="space-y-3">
                    @foreach ($m->renewals as $r)
                        <li class="border-l-2 border-gray-100 pl-3">
                            <p class="text-sm font-semibold text-[var(--color-heading)]">{{ $r->starts_on->format('d M Y') }} → {{ $r->ends_on->format('d M Y') }}</p>
                            <p class="text-xs text-[var(--color-muted)]">
                                was {{ $r->previous_ends_on->format('d M Y') }}@if ($r->fee) &middot; {{ $m->currency }} {{ number_format((float) $r->fee, 2) }}@endif
                                @if ($r->renewedBy) &middot; {{ $r->renewedBy->name }} @endif
                            </p>
                            @if ($r->note)<p class="mt-1 text-xs text-[var(--color-muted)]">{{ $r->note }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

{{-- Plan drawer --}}
@if ($can('edit'))
<div x-show="plan" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="plan = false">
    <div class="absolute inset-0 bg-black/40" @click="plan = false"></div>
    <div style="width: 24rem" class="absolute inset-y-0 right-0 max-w-full overflow-y-auto bg-white shadow-xl"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
        <form method="POST" x-bind:action="editing ? '{{ route('admin.maintenance.tasks.store', $m) }}/' + editing.id : '{{ route('admin.maintenance.tasks.store', $m) }}'" class="p-5">
            @csrf
            <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-bold text-[var(--color-heading)]" x-text="editing ? 'Edit task' : 'Add task'"></h2>
                <button type="button" @click="plan = false" class="text-gray-400 hover:text-[var(--color-heading)]">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>

            {{-- One state object seeded from the row being edited, so every field comes back —
                 binding only some of them meant editing a task silently reset the rest. --}}
            <div class="space-y-4"
                 x-data="{ f: { title: '', description: '', frequency: 'daily', weekday: 1, day_of_month: 1, assigned_to: '', is_active: true } }"
                 x-effect="f = editing
                    ? { title: editing.title ?? '', description: editing.description ?? '', frequency: editing.frequency ?? 'daily',
                        weekday: editing.weekday ?? 1, day_of_month: editing.day_of_month ?? 1,
                        assigned_to: editing.assigned_to ?? '', is_active: !!editing.is_active }
                    : { title: '', description: '', frequency: 'daily', weekday: 1, day_of_month: 1, assigned_to: '', is_active: true }">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Task</label>
                    <input name="title" required x-model="f.title" placeholder="e.g. Database backup"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">How often</label>
                    <select name="frequency" x-model="f.frequency" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        @foreach (\App\Models\MaintenanceTask::FREQUENCIES as $v => $label)
                            <option value="{{ $v }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="f.frequency === 'weekly'" x-cloak>
                    <label class="mb-1.5 block text-sm font-medium">Which day</label>
                    <select name="weekday" x-model="f.weekday" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        @foreach ($weekdays as $i => $d)
                            <option value="{{ $i }}">{{ $d }}</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="f.frequency === 'monthly'" x-cloak>
                    <label class="mb-1.5 block text-sm font-medium">Day of the month</label>
                    <input name="day_of_month" type="number" min="1" max="31" x-model="f.day_of_month"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    <p class="mt-1 text-xs text-[var(--color-muted)]">29–31 falls on the last day in shorter months, so it is never skipped.</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Who does it</label>
                    <select name="assigned_to" x-model="f.assigned_to" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <option value="">Unassigned</option>
                        @foreach ($staff as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Notes</label>
                    <textarea name="description" rows="3" x-model="f.description"
                              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none"></textarea>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" x-model="f.is_active" class="rounded border-gray-300">
                    Active — falls due on schedule
                </label>
            </div>

            <div class="mt-5 flex gap-2">
                <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save task</button>
                <button type="button" @click="plan = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Renewal --}}
@if ($can('renew'))
<div x-show="renew" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" @keydown.escape.window="renew = false">
    <div class="absolute inset-0 bg-black/40" @click="renew = false"></div>
    <form method="POST" action="{{ route('admin.maintenance.renew', $m) }}" class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        @csrf
        <h3 class="text-base font-bold text-[var(--color-heading)]">Renew {{ $m->code }}</h3>
        <p class="mt-1 text-sm text-[var(--color-muted)]">The current term ends {{ $m->ends_on->format('d M Y') }}.</p>

        <div class="mt-4 space-y-3">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">New term starts</label>
                    <input type="date" name="starts_on" required value="{{ $m->ends_on->copy()->addDay()->toDateString() }}"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">and runs to</label>
                    <input type="date" name="ends_on" required value="{{ $m->ends_on->copy()->addDay()->addYear()->toDateString() }}"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium">Fee for the new term</label>
                <input type="number" step="0.01" min="0" name="fee" value="{{ $m->fee }}"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium">Note</label>
                <textarea name="note" rows="2" placeholder="What was agreed, and with whom"
                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none"></textarea>
            </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" @click="renew = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Record renewal</button>
        </div>
    </form>
</div>
@endif

<style>[x-cloak]{display:none!important}</style>
</div>
@endsection
