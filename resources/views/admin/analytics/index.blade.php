@extends('admin.layouts.app')
@section('title', 'CRM Analytics')

@php
    $tabs = ['reports' => 'Reports', 'followups' => 'Follow-ups', 'country' => 'By Country'];
    $qs = request()->only(['date_range', 'from', 'to']);
    $tabUrl = fn ($t) => route('admin.analytics.index', array_merge($t === 'followups' ? [] : $qs, ['tab' => $t]));
    $range = request('date_range');
    $hasCustom = request('from') || request('to');
    $ranges = ['' => 'All Time', 'today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'];
@endphp

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Analytics</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; Analytics — lead &amp; deal reports, follow-ups and country insight.</p>
    </div>

    {{-- Tabs --}}
    <div class="mb-5 flex gap-1 overflow-x-auto border-b border-gray-100">
        @foreach ($tabs as $key => $label)
            <a href="{{ $tabUrl($key) }}" data-turbo="false" class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $tab === $key ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">{{ $label }}</a>
        @endforeach
    </div>

    {{-- Date range filter (reports & country) --}}
    @if (in_array($tab, ['reports', 'country']))
        <form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @foreach ($ranges as $key => $label)
                @php $active = ! $hasCustom && $range === $key; @endphp
                <a href="{{ route('admin.analytics.index', array_filter(['tab' => $tab, 'date_range' => $key])) }}"
                   class="rounded-lg border px-3 py-2 text-sm font-semibold transition {{ $active ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-gray-200 bg-white text-[var(--color-muted)] hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
            <span class="mx-1 h-6 w-px bg-gray-200"></span>
            <div class="flex items-center gap-2 rounded-lg border {{ $hasCustom ? 'border-[var(--color-primary)]' : 'border-gray-200' }} bg-white px-2 py-1">
                <input type="date" name="from" value="{{ request('from') }}" class="h-8 rounded-md border-0 px-1 text-sm focus:ring-0" title="From">
                <span class="text-xs text-gray-400">→</span>
                <input type="date" name="to" value="{{ request('to') }}" class="h-8 rounded-md border-0 px-1 text-sm focus:ring-0" title="To">
                <button class="rounded-md bg-[var(--color-primary)] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply</button>
            </div>
            @if ($range || $hasCustom)
                <a href="{{ route('admin.analytics.index', ['tab' => $tab]) }}" class="text-xs font-semibold text-gray-400 hover:text-red-500">Clear</a>
            @endif
        </form>
    @endif

    @includeWhen($tab === 'reports', 'admin.analytics.tabs.reports')
    @includeWhen($tab === 'followups', 'admin.analytics.tabs.followups')
    @includeWhen($tab === 'country', 'admin.analytics.tabs.country')
@endsection
