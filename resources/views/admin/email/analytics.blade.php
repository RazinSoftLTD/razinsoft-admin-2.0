@extends('admin.layouts.app')
@section('title', 'Email Analytics')

@php
    // A dash, not 0%, when there is nothing to divide by — "0% opened" and "nothing sent yet"
    // are very different things.
    $pct = fn (?float $v) => $v === null ? '—' : $v.'%';
    $peak = max(1, $daily->max('total'));
@endphp

@section('content')
    <x-admin.email-shell>

    {{-- Range --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Last {{ $days }} days</h2>
            <p class="text-xs text-[var(--color-muted)]">Rates are worked out against what was delivered, not everything queued.</p>
        </div>
        <div class="flex gap-1 rounded-lg border border-gray-200 bg-white p-1">
            @foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $value => $label)
                <a href="{{ route('admin.email.analytics', ['days' => $value]) }}"
                   class="rounded-md px-3 py-1.5 text-xs font-semibold transition {{ $days === $value ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    {{-- Headline numbers --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Sent', number_format($summary['sent']), 'Delivered '.number_format($summary['delivered']), 'text-emerald-700 bg-emerald-50'],
            ['Open rate', $pct($summary['open_rate']), number_format($summary['opened']).' opened', 'text-sky-700 bg-sky-50'],
            ['Click rate', $pct($summary['click_rate']), $pct($summary['click_to_open_rate']).' of those who opened', 'text-violet-700 bg-violet-50'],
            ['Failed', number_format($summary['failed']), $pct($summary['failure_rate']).' of everything queued', 'text-red-600 bg-red-50'],
        ] as [$label, $value, $note, $tone])
            <div class="rounded-xl border border-gray-100 bg-white px-5 py-4 shadow-sm">
                <p class="text-[11px] uppercase tracking-wide text-gray-400">{{ $label }}</p>
                <p class="mt-1 inline-flex rounded-lg px-2 py-0.5 text-2xl font-extrabold leading-tight {{ $tone }}">{{ $value }}</p>
                <p class="mt-1 text-xs text-[var(--color-muted)]">{{ $note }}</p>
            </div>
        @endforeach
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Bounce rate', $pct($summary['bounce_rate']), number_format($summary['bounced']).' bounced'],
            ['Spam complaints', number_format($summary['complained']), $pct($summary['complaint_rate']).' of sent'],
            ['Avg. time to send', $summary['avg_delivery_seconds'] === null ? '—' : $summary['avg_delivery_seconds'].'s', 'Queued until the provider accepted it'],
            ['Total queued', number_format($summary['total']), 'Everything in the range'],
        ] as [$label, $value, $note])
            <div class="rounded-xl border border-gray-100 bg-white px-5 py-4 shadow-sm">
                <p class="text-[11px] uppercase tracking-wide text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-xl font-extrabold leading-tight text-[var(--color-heading)]">{{ $value }}</p>
                <p class="mt-1 text-xs text-[var(--color-muted)]">{{ $note }}</p>
            </div>
        @endforeach
    </div>

    {{-- Anything above a percent or so of complaints is a real problem, so it is called out. --}}
    @if ($summary['complaint_rate'] !== null && $summary['complaint_rate'] >= 0.1)
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-5 py-4">
            <p class="text-sm font-bold text-red-700">Spam complaints are high</p>
            <p class="mt-0.5 text-xs leading-relaxed text-red-700">
                {{ $pct($summary['complaint_rate']) }} of sent mail was reported as spam. Mailbox providers start
                filtering a domain above roughly 0.1%. Check what is being sent, and to whom.
            </p>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-3">
        {{-- Daily chart. Server-rendered bars: the panel's CSS is precompiled, so no chart library. --}}
        <section class="rounded-xl border border-gray-100 bg-white shadow-sm xl:col-span-2">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Daily sending</h2>
                <div class="flex flex-wrap gap-3 text-xs text-[var(--color-muted)]">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[var(--color-primary)]"></span> Sent</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-400"></span> Opened</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-red-500"></span> Failed</span>
                </div>
            </header>

            <div class="p-5">
                @if ($daily->sum('total') === 0)
                    <p class="py-12 text-center text-sm text-gray-400">Nothing was sent in this range.</p>
                @else
                    <div class="flex items-end gap-1 overflow-x-auto" style="height:200px">
                        @foreach ($daily as $day)
                            <div class="flex min-w-0 flex-1 flex-col items-center justify-end gap-0.5" style="min-width:8px;height:100%"
                                 title="{{ $day['label'] }} — {{ $day['sent'] }} sent, {{ $day['opened'] }} opened, {{ $day['failed'] }} failed">
                                @if ($day['failed'])
                                    <span class="w-full rounded-sm bg-red-500" style="height:{{ max(2, round($day['failed'] / $peak * 100)) }}%"></span>
                                @endif
                                @if ($day['opened'])
                                    <span class="w-full rounded-sm bg-emerald-400" style="height:{{ max(2, round($day['opened'] / $peak * 100)) }}%"></span>
                                @endif
                                @if ($day['sent'])
                                    <span class="w-full rounded-sm bg-[var(--color-primary)]" style="height:{{ max(2, round($day['sent'] / $peak * 100)) }}%"></span>
                                @endif
                                @if (! $day['total'])
                                    <span class="w-full rounded-sm bg-gray-100" style="height:2px"></span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex justify-between text-[11px] text-gray-400">
                        <span>{{ $daily->first()['label'] }}</span>
                        <span>{{ $daily->last()['label'] }}</span>
                    </div>
                @endif
            </div>
        </section>

        {{-- Volume by period --}}
        <section class="self-start rounded-xl border border-gray-100 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-5 py-3.5">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Volume</h2>
            </header>
            <dl class="space-y-3 p-5 text-sm">
                @foreach (['Today' => $periods['today'], 'This week' => $periods['week'], 'This month' => $periods['month'], 'This year' => $periods['year']] as $label => $value)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[var(--color-muted)]">{{ $label }}</dt>
                        <dd class="font-bold text-[var(--color-heading)]">{{ number_format($value) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- Top templates --}}
        <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-5 py-3.5">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Most-used templates</h2>
            </header>
            @if ($topTemplates->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-gray-400">No template mail in this range.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($topTemplates as $t)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-[var(--color-heading)]">{{ $t['name'] }}</p>
                                <p class="text-xs text-[var(--color-muted)]">{{ number_format($t['total']) }} sent · {{ number_format($t['opened']) }} opened</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-700">{{ $pct($t['open_rate']) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Top SMTP accounts --}}
        <section class="rounded-xl border border-gray-100 bg-white shadow-sm xl:col-span-2">
            <header class="border-b border-gray-100 px-5 py-3.5">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">SMTP accounts</h2>
            </header>
            @if ($topConfigs->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-gray-400">Nothing sent in this range.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($topConfigs as $c)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-[var(--color-heading)]">{{ $c['name'] }}</p>
                                <p class="text-xs text-[var(--color-muted)]">{{ number_format($c['sent']) }} sent · {{ number_format($c['failed']) }} failed</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ ($c['failure_rate'] ?? 0) > 5 ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $pct($c['failure_rate']) }} failed
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
    </x-admin.email-shell>
@endsection
