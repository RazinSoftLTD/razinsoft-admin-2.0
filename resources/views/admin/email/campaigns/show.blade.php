@extends('admin.layouts.app')
@section('title', $campaign->name)

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <nav class="mb-1.5 flex items-center gap-2 text-sm text-[var(--color-muted)]">
                <a href="{{ route('admin.email.campaigns') }}" class="hover:text-[var(--color-heading)]">Manual Email</a>
                <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                <span class="truncate text-[var(--color-heading)]">{{ $campaign->name }}</span>
            </nav>
            <h1 class="truncate text-xl font-bold text-[var(--color-heading)]">{{ $campaign->subject }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-2">
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $campaign->statusTone() }}">{{ $campaign->statusLabel() }}</span>
                @if ($campaign->scheduled_at && $campaign->status === 'scheduled')
                    <span class="text-xs text-gray-400">for {{ $campaign->scheduled_at->format('d M Y, g:i A') }}</span>
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($campaign->isEditable())
                <a href="{{ route('admin.email.campaigns.edit', $campaign) }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Edit</a>
            @endif
            @if (! in_array($campaign->status, ['sent', 'cancelled'], true))
                <form method="POST" action="{{ route('admin.email.campaigns.cancel', $campaign) }}" onsubmit="return confirm('Stop this campaign? Anything already queued still goes out.')">
                    @csrf
                    <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Progress --}}
    <section class="mb-5 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-bold text-[var(--color-heading)]">
                {{ number_format($progress['done']) }} of {{ number_format($progress['total']) }} queued
            </p>
            <span class="text-sm font-semibold text-[var(--color-muted)]">{{ $progress['percent'] }}%</span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
            <div class="h-2 rounded-full bg-[var(--color-primary)]" style="width:{{ $progress['percent'] }}%"></div>
        </div>
        @if ($campaign->status === 'sending')
            <p class="mt-2 text-xs text-[var(--color-muted)]">Queued 100 at a time, a minute apart, so the provider is never flooded. Reload to see progress.</p>
        @endif
    </section>

    <div class="grid gap-5 xl:grid-cols-3">
        <section class="rounded-xl border border-gray-100 bg-white shadow-sm xl:col-span-2">
            <header class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Recipients</h2>
                <span class="text-xs text-gray-400">{{ number_format($recipients->total()) }}</span>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" style="min-width:520px">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Recipient</th>
                            <th class="px-5 py-3 font-semibold">Queued</th>
                            <th class="px-5 py-3 font-semibold">Delivery</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($recipients as $r)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-[var(--color-heading)]">{{ $r->email }}</p>
                                    @if ($r->name)<p class="text-xs text-gray-400">{{ $r->name }}</p>@endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $r->status === 'skipped' ? 'bg-amber-50 text-amber-700' : ($r->status === 'queued' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600') }}">
                                        {{ ucfirst($r->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($r->log)
                                        <a href="{{ route('admin.email.logs.show', $r->log_id ?? $r->email_log_id) }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">
                                            {{ ucfirst($r->log->status) }}{{ $r->log->first_opened_at ? ' · opened' : '' }}
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-12 text-center text-sm text-gray-400">The recipient list is built when the send starts.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($recipients->hasPages())
                <div class="border-t border-gray-100 px-5 py-3">{{ $recipients->links() }}</div>
            @endif
        </section>

        <section class="self-start rounded-xl border border-gray-100 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-5 py-3.5">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Details</h2>
            </header>
            <dl class="space-y-3 p-5 text-sm">
                @foreach ([
                    'Audience' => \App\Services\Email\CampaignAudience::TYPES[$campaign->audience['type'] ?? 'all'] ?? '—',
                    'Template' => $campaign->template?->name ?? 'Sent as written',
                    'SMTP account' => $campaign->config?->name ?? 'Default',
                    'Created by' => $campaign->creator?->name,
                    'Started' => $campaign->started_at?->format('d M Y, g:i A'),
                    'Finished' => $campaign->finished_at?->format('d M Y, g:i A'),
                ] as $label => $value)
                    @if ($value)
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-[var(--color-muted)]">{{ $label }}</dt>
                            <dd class="text-right font-medium text-[var(--color-heading)]">{{ $value }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        </section>
    </div>
@endsection
