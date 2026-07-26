@extends('admin.layouts.app')
@section('title', 'Email Queue')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Email Management</h1>
        <p class="text-sm text-[var(--color-muted)]">The SMTP accounts this system sends through, and how mail is templated, queued and tracked.</p>
    </div>

    @include('admin.email._nav')

    {{-- A stopped worker is the usual reason "email is broken", so it is said plainly. --}}
    @unless ($workerRunning)
        <div class="mb-4 flex flex-wrap items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
            <div class="min-w-0">
                <p class="text-sm font-bold text-amber-800">Nothing is draining the queue</p>
                <p class="mt-0.5 text-xs text-amber-800">
                    Messages have been waiting more than five minutes. Start a worker on the server:
                    <code class="rounded bg-white px-1.5 py-0.5 text-[11px]">php artisan queue:work --queue=default</code>
                    — under Supervisor or PM2 so it restarts on its own.
                </p>
            </div>
        </div>
    @endunless

    {{-- Counts --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            ['Pending', $counts['pending'], 'text-gray-600 bg-gray-100'],
            ['Sending', $counts['sending'], 'text-sky-700 bg-sky-50'],
            ['Failed', $counts['failed'], 'text-red-600 bg-red-50'],
            ['Scheduled', $counts['scheduled'], 'text-violet-700 bg-violet-50'],
            ['Sent', $counts['sent'], 'text-emerald-700 bg-emerald-50'],
            ['Cancelled', $counts['cancelled'], 'text-gray-500 bg-gray-100'],
        ] as [$label, $value, $tone])
            <div class="rounded-xl border border-gray-100 bg-white px-4 py-3.5 shadow-sm">
                <p class="text-[11px] uppercase tracking-wide text-gray-400">{{ $label }}</p>
                <p class="mt-1 inline-flex rounded-full px-2 py-0.5 text-lg font-extrabold leading-tight {{ $tone }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Waiting to send</h2>
            <p class="text-xs text-[var(--color-muted)]">Failed messages are listed first. Retrying one puts it back at the front of the queue.</p>
        </div>
        @if ($counts['failed'])
            <form method="POST" action="{{ route('admin.email.queue.retry-all') }}" onsubmit="return confirm('Re-queue all {{ $counts['failed'] }} failed message(s)?')">
                @csrf
                <button class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M20 9A8 8 0 0 0 5.6 5.6M4 15a8 8 0 0 0 14.4 3.4"/></svg>
                    Retry all failed
                </button>
            </form>
        @endif
    </div>

    <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" style="min-width:820px">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Recipient</th>
                        <th class="px-5 py-3 font-semibold">Subject</th>
                        <th class="px-5 py-3 font-semibold">Account</th>
                        <th class="px-5 py-3 font-semibold">Queued</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.email.logs.show', $log) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $log->to_email }}</a>
                                @if ($log->to_name)<p class="text-xs text-gray-400">{{ $log->to_name }}</p>@endif
                            </td>
                            <td class="px-5 py-3">
                                <p class="max-w-xs truncate text-[var(--color-heading)]">{{ $log->subject }}</p>
                                @if ($log->template)<p class="text-xs text-gray-400">{{ $log->template->name }}</p>@endif
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $log->config?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">
                                {{ $log->queued_at?->diffForHumans() ?? '—' }}
                                @if ($log->scheduled_at && $log->scheduled_at->isFuture())
                                    <p class="text-xs text-violet-600">Scheduled {{ $log->scheduled_at->format('d M, g:i A') }}</p>
                                @endif
                                @if ($log->attempts > 1)<p class="text-xs text-gray-400">{{ $log->attempts }} attempts</p>@endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $log->statusTone() }}">{{ $log->statusLabel() }}</span>
                                @if ($log->error)
                                    <p class="mt-1 max-w-xs truncate text-xs text-red-600" title="{{ $log->error }}">{{ $log->error }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-1.5">
                                    @if ($log->isRetryable())
                                        <form method="POST" action="{{ route('admin.email.logs.retry', $log) }}">
                                            @csrf
                                            <button class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-heading)] hover:bg-gray-50">Retry</button>
                                        </form>
                                    @endif
                                    @if (in_array($log->status, ['pending', 'sending'], true))
                                        <form method="POST" action="{{ route('admin.email.logs.cancel', $log) }}">
                                            @csrf
                                            <button class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <span class="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-full bg-emerald-50 text-emerald-600">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>
                                </span>
                                <p class="text-sm font-semibold text-[var(--color-heading)]">The queue is empty</p>
                                <p class="mt-1 text-xs text-gray-400">Everything queued has been sent.</p>
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
@endsection
