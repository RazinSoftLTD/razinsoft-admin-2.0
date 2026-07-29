@extends('admin.layouts.app')
@section('title', 'Manual Email')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Email Manager</h1>
        <p class="text-sm text-[var(--color-muted)]">How mail is templated, queued, sent and tracked. SMTP accounts live under Email Config.</p>
    </div>

    @include('admin.email._nav')

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Manual email</h2>
            <p class="text-xs text-[var(--color-muted)]">One message to many people. Sent in batches so a large send never floods the provider.</p>
        </div>
        <a href="{{ route('admin.email.campaigns.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            New Email
        </a>
    </div>

    <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" style="min-width:760px">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Name</th>
                        <th class="px-5 py-3 font-semibold">Subject</th>
                        <th class="px-5 py-3 font-semibold">Recipients</th>
                        <th class="px-5 py-3 font-semibold">When</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($campaigns as $c)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.email.campaigns.show', $c) }}" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $c->name }}</a>
                                @if ($c->creator)<p class="text-xs text-gray-400">by {{ $c->creator->name }}</p>@endif
                            </td>
                            <td class="px-5 py-3"><p class="max-w-sm truncate text-[var(--color-heading)]">{{ $c->subject }}</p></td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ number_format($c->total_recipients) }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">
                                @if ($c->status === 'scheduled' && $c->scheduled_at)
                                    {{ $c->scheduled_at->format('d M, g:i A') }}
                                @elseif ($c->finished_at)
                                    {{ $c->finished_at->format('d M, g:i A') }}
                                @else
                                    {{ $c->created_at->format('d M, g:i A') }}
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $c->statusTone() }}">{{ $c->statusLabel() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center">
                                <span class="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-full bg-gray-50 text-gray-300">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                                </span>
                                <p class="text-sm font-semibold text-[var(--color-heading)]">No manual email yet</p>
                                <p class="mt-1 text-xs text-gray-400">Write one and choose who it goes to.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($campaigns->hasPages())
            <div class="border-t border-gray-100 px-5 py-3">{{ $campaigns->links() }}</div>
        @endif
    </section>
@endsection
