@extends('admin.layouts.app')
@section('title', 'Email — '.$log->subject)

@section('content')
    <div x-data="{ tab: 'html' }">
        {{-- Header --}}
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <nav class="mb-1.5 flex items-center gap-2 text-sm text-[var(--color-muted)]">
                    <a href="{{ route('admin.email.logs') }}" class="hover:text-[var(--color-heading)]">Email Logs</a>
                    <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                    <span class="truncate text-[var(--color-heading)]">{{ $log->to_email }}</span>
                </nav>
                <h1 class="truncate text-xl font-bold text-[var(--color-heading)]">{{ $log->subject }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $log->statusTone() }}">{{ $log->statusLabel() }}</span>
                    @if ($log->bounced)<span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-600">Bounced</span>@endif
                    @if ($log->complained)<span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-600">Reported as spam</span>@endif
                    <span class="text-xs text-gray-400">to {{ $log->to_name ? $log->to_name.' · ' : '' }}{{ $log->to_email }}</span>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($log->isRetryable())
                    <form method="POST" action="{{ route('admin.email.logs.retry', $log) }}">
                        @csrf
                        <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Retry</button>
                    </form>
                @endif
                @if (in_array($log->status, ['pending', 'sending'], true))
                    <form method="POST" action="{{ route('admin.email.logs.cancel', $log) }}">
                        @csrf
                        <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                    </form>
                @endif
                @if (auth()->user()->hasPermission('email.send'))
                    <form method="POST" action="{{ route('admin.email.logs.resend', $log) }}" onsubmit="return confirm('Send this message again to {{ $log->to_email }}?')">
                        @csrf
                        <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Resend</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($log->error)
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4">
                <p class="text-sm font-bold text-red-700">Delivery failed</p>
                <p class="mt-1 text-xs leading-relaxed text-red-700">{{ $log->error }}</p>
            </div>
        @endif

        <div class="grid gap-5 xl:grid-cols-3">
            {{-- The message itself --}}
            <div class="space-y-5 xl:col-span-2">
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex gap-1 border-b border-gray-100 px-5">
                        @foreach ([['html', 'HTML'], ['text', 'Plain text'], ['headers', 'Headers']] as [$key, $label])
                            <button type="button" @click="tab = '{{ $key }}'"
                                    :class="tab === '{{ $key }}' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]'"
                                    class="border-b-2 px-3 py-3 text-sm font-semibold transition">{{ $label }}</button>
                        @endforeach
                    </div>

                    <div x-show="tab === 'html'" class="bg-gray-50">
                        <iframe src="{{ route('admin.email.logs.body', $log) }}" class="w-full border-0 bg-white" style="height:560px" title="Message body"></iframe>
                    </div>

                    <div x-show="tab === 'text'" x-cloak class="p-5">
                        <pre class="whitespace-pre-wrap font-mono text-xs leading-relaxed text-[var(--color-heading)]">{{ $log->body_text ?: '(no plain-text part recorded)' }}</pre>
                    </div>

                    <div x-show="tab === 'headers'" x-cloak class="p-5">
                        <dl class="space-y-2 text-xs">
                            @foreach ([
                                'Message-ID' => '<'.$log->tracking_id.'@'.\App\Services\Email\EmailBodyBuilder::domain().'>',
                                'From' => ($log->config?->from_name ? $log->config->from_name.' <'.$log->config->from_email.'>' : $log->config?->from_email) ?: '—',
                                'Reply-To' => $log->config?->reply_to ?: '—',
                                'Return-Path' => $log->config?->return_path ?: '—',
                                'To' => $log->to_email,
                                'Subject' => $log->subject,
                                'X-Entity-Ref-ID' => $log->tracking_id,
                            ] as $name => $value)
                                <div class="flex flex-wrap gap-2 border-b border-gray-50 pb-2">
                                    <dt class="w-36 shrink-0 font-semibold text-gray-400">{{ $name }}</dt>
                                    <dd class="min-w-0 flex-1 break-all font-mono text-[var(--color-heading)]">{{ $value }}</dd>
                                </div>
                            @endforeach
                            @if ($log->cc)
                                <div class="flex gap-2"><dt class="w-36 shrink-0 font-semibold text-gray-400">Cc</dt><dd class="font-mono">{{ implode(', ', $log->cc) }}</dd></div>
                            @endif
                        </dl>
                    </div>
                </section>

                {{-- What happened after it left --}}
                @if ($log->opens->count() || $log->clicks->count())
                    <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                        <header class="border-b border-gray-100 px-5 py-3.5">
                            <h2 class="text-sm font-bold text-[var(--color-heading)]">Activity</h2>
                        </header>
                        <div class="divide-y divide-gray-100">
                            @foreach ($log->clicks as $click)
                                <div class="flex flex-wrap items-start gap-3 px-5 py-3">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-sky-50 text-sky-700">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6.5 21 14l-4 1-1 4-7.5-7.5M3 3l7 18 2.5-7.5L20 11 3 3Z"/></svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-[var(--color-heading)]">Clicked a link</p>
                                        <p class="truncate text-xs text-[var(--color-muted)]">{{ $click->url }}</p>
                                    </div>
                                    <span class="shrink-0 text-xs text-gray-400">{{ $click->clicked_at->format('d M, g:i A') }}</span>
                                </div>
                            @endforeach
                            @foreach ($log->opens as $open)
                                <div class="flex flex-wrap items-start gap-3 px-5 py-3">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-emerald-50 text-emerald-700">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7 9.75-7 9.75 7 9.75 7-3.5 7-9.75 7S2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-[var(--color-heading)]">Opened</p>
                                        <p class="truncate text-xs text-[var(--color-muted)]">{{ \Illuminate\Support\Str::limit($open->user_agent, 70) ?: 'Unknown client' }}</p>
                                    </div>
                                    <span class="shrink-0 text-xs text-gray-400">{{ $open->opened_at->format('d M, g:i A') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            {{-- Facts --}}
            <div class="space-y-5">
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <header class="border-b border-gray-100 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Delivery</h2>
                    </header>
                    <dl class="space-y-3 p-5 text-sm">
                        @foreach ([
                            'Queued' => $log->queued_at?->format('d M Y, g:i A'),
                            'Scheduled' => $log->scheduled_at?->format('d M Y, g:i A'),
                            'Sent' => $log->sent_at?->format('d M Y, g:i A'),
                            'Delivered' => $log->delivered_at?->format('d M Y, g:i A'),
                            'First opened' => $log->first_opened_at?->format('d M Y, g:i A'),
                            'First clicked' => $log->first_clicked_at?->format('d M Y, g:i A'),
                        ] as $label => $value)
                            @if ($value)
                                <div class="flex items-start justify-between gap-3">
                                    <dt class="text-[var(--color-muted)]">{{ $label }}</dt>
                                    <dd class="text-right font-medium text-[var(--color-heading)]">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach

                        @if ($log->deliverySeconds() !== null)
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-[var(--color-muted)]">Time to send</dt>
                                <dd class="text-right font-medium text-[var(--color-heading)]">{{ $log->deliverySeconds() }}s</dd>
                            </div>
                        @endif
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-[var(--color-muted)]">Attempts</dt>
                            <dd class="text-right font-medium text-[var(--color-heading)]">{{ $log->attempts }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <header class="border-b border-gray-100 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Source</h2>
                    </header>
                    <dl class="space-y-3 p-5 text-sm">
                        @foreach ([
                            'SMTP account' => $log->config?->name,
                            'Template' => $log->template?->name,
                            'Module' => $log->module ? ucfirst($log->module) : null,
                            'Sent by' => $log->creator?->name,
                            'Opens' => $log->open_count ?: null,
                            'Clicks' => $log->click_count ?: null,
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

                @if ($log->attachments->count())
                    <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                        <header class="border-b border-gray-100 px-5 py-3.5">
                            <h2 class="text-sm font-bold text-[var(--color-heading)]">Attachments</h2>
                        </header>
                        <div class="divide-y divide-gray-100">
                            @foreach ($log->attachments as $file)
                                <a href="{{ \App\Http\Resources\ProductResource::media($file->path) }}" target="_blank" rel="noopener"
                                   class="flex items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-gray-50">
                                    <span class="min-w-0 truncate font-medium text-[var(--color-primary)]">{{ $file->name }}</span>
                                    <span class="shrink-0 text-xs text-gray-400">{{ $file->sizeLabel() }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                <form method="POST" action="{{ route('admin.email.logs.destroy', $log) }}" onsubmit="return confirm('Delete this log entry?')">
                    @csrf @method('DELETE')
                    <button class="text-sm font-semibold text-red-600 hover:underline">Delete log entry</button>
                </form>
            </div>
        </div>
    </div>
@endsection
