@extends('admin.layouts.app')
@section('title', 'Email Logs')

@php
    $q = fn ($key, $default = '') => request($key, $default);
    $hasFilters = collect(['q','status','template','config','module','from','to','opened','clicked','bounced'])
        ->contains(fn ($k) => filled(request($k)));
@endphp

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Email Management</h1>
        <p class="text-sm text-[var(--color-muted)]">The SMTP accounts this system sends through, and how mail is templated, queued and tracked.</p>
    </div>

    @include('admin.email._nav')

    <div x-data="{ filters: {{ $hasFilters ? 'true' : 'false' }} }">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Every message <span class="font-normal text-gray-400">({{ number_format($logs->total()) }})</span></h2>
                <p class="text-xs text-[var(--color-muted)]">Everything the system has sent or tried to send.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.email.suppressions') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Suppression list</a>
                <button type="button" @click="filters = !filters" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                    Filters
                    @if ($hasFilters)<span class="rounded-full bg-[var(--color-primary)] px-1.5 text-[10px] font-bold text-white">on</span>@endif
                </button>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" x-show="filters" x-cloak class="mb-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Search</label>
                    <input name="q" value="{{ $q('q') }}" placeholder="Recipient or subject" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Status</label>
                    <select name="status" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                        <option value="">Any</option>
                        @foreach (\App\Models\EmailLog::STATUSES as $k => $v)<option value="{{ $k }}" @selected($q('status') === $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Template</label>
                    <select name="template" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                        <option value="">Any</option>
                        @foreach ($templates as $id => $name)<option value="{{ $id }}" @selected((string) $q('template') === (string) $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">SMTP account</label>
                    <select name="config" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                        <option value="">Any</option>
                        @foreach ($configs as $id => $name)<option value="{{ $id }}" @selected((string) $q('config') === (string) $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Module</label>
                    <select name="module" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                        <option value="">Any</option>
                        @foreach ($modules as $m)<option value="{{ $m }}" @selected($q('module') === $m)>{{ ucfirst($m) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">From date</label>
                    <input type="date" name="from" value="{{ $q('from') }}" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">To date</label>
                    <input type="date" name="to" value="{{ $q('to') }}" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Opened</label>
                    <select name="opened" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                        <option value="">Any</option>
                        <option value="yes" @selected($q('opened') === 'yes')>Opened</option>
                        <option value="no" @selected($q('opened') === 'no')>Sent but not opened</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Clicked</label>
                    <select name="clicked" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                        <option value="">Any</option>
                        <option value="yes" @selected($q('clicked') === 'yes')>Clicked a link</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 self-end pb-2 text-sm text-[var(--color-muted)]">
                    <input type="checkbox" name="bounced" value="1" @checked(request()->boolean('bounced')) class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]">
                    Bounced only
                </label>
            </div>

            <div class="mt-4 flex gap-2">
                <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply</button>
                @if ($hasFilters)
                    <a href="{{ route('admin.email.logs') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Clear</a>
                @endif
            </div>
        </form>

        <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" style="min-width:860px">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Recipient</th>
                            <th class="px-5 py-3 font-semibold">Subject</th>
                            <th class="px-5 py-3 font-semibold">Sent</th>
                            <th class="px-5 py-3 font-semibold">Engagement</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.email.logs.show', $log) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $log->to_email }}</a>
                                    @if ($log->module)<p class="text-xs text-gray-400">{{ ucfirst($log->module) }}</p>@endif
                                </td>
                                <td class="px-5 py-3">
                                    <p class="max-w-sm truncate text-[var(--color-heading)]">{{ $log->subject }}</p>
                                    @if ($log->template)<p class="text-xs text-gray-400">{{ $log->template->name }}</p>@endif
                                </td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">
                                    {{ $log->sent_at?->format('d M, g:i A') ?? $log->created_at->format('d M, g:i A') }}
                                    @if ($log->config)<p class="text-xs text-gray-400">{{ $log->config->name }}</p>@endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="flex flex-wrap gap-1">
                                        @if ($log->open_count)
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Opened{{ $log->open_count > 1 ? ' ×'.$log->open_count : '' }}</span>
                                        @endif
                                        @if ($log->click_count)
                                            <span class="rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-700">Clicked{{ $log->click_count > 1 ? ' ×'.$log->click_count : '' }}</span>
                                        @endif
                                        @if ($log->bounced)
                                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-600">Bounced</span>
                                        @endif
                                        @if ($log->complained)
                                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-600">Spam report</span>
                                        @endif
                                        @if (! $log->open_count && ! $log->click_count && ! $log->bounced && ! $log->complained)
                                            <span class="text-xs text-gray-300">—</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $log->statusTone() }}">{{ $log->statusLabel() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center">
                                    <span class="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-full bg-gray-50 text-gray-300">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                                    </span>
                                    <p class="text-sm font-semibold text-[var(--color-heading)]">{{ $hasFilters ? 'Nothing matches those filters' : 'No email sent yet' }}</p>
                                    <p class="mt-1 text-xs text-gray-400">{{ $hasFilters ? 'Try widening the date range.' : 'Everything the system sends will be listed here.' }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="border-t border-gray-100 px-5 py-3">{{ $logs->links() }}</div>
            @endif
        </section>
    </div>
@endsection
