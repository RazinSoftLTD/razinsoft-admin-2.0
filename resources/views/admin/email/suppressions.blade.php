@extends('admin.layouts.app')
@section('title', 'Suppression List')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Email Manager</h1>
        <p class="text-sm text-[var(--color-muted)]">How mail is templated, queued, sent and tracked. SMTP accounts live under Email Config.</p>
    </div>

    @include('admin.email._nav')

    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
        <p class="text-sm font-bold text-amber-800">Nothing on this list will ever be emailed</p>
        <p class="mt-0.5 text-xs leading-relaxed text-amber-800">
            Addresses land here when mail hard-bounces, when someone reports it as spam, or when they unsubscribe.
            Continuing to mail those addresses is the fastest way to get a sending domain blocked, so every send is
            checked against this list first.
        </p>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-xl border border-gray-100 bg-white shadow-sm lg:col-span-2">
            <header class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Suppressed addresses</h2>
                <span class="text-xs text-gray-400">{{ number_format($suppressions->total()) }} total</span>
            </header>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" style="min-width:560px">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Address</th>
                            <th class="px-5 py-3 font-semibold">Reason</th>
                            <th class="px-5 py-3 font-semibold">Added</th>
                            <th class="px-5 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($suppressions as $s)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ $s->email }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">{{ $s->reasonLabel() }}</span>
                                    @if ($s->note)<p class="mt-1 max-w-xs truncate text-xs text-gray-400" title="{{ $s->note }}">{{ $s->note }}</p>@endif
                                </td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $s->created_at->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-right">
                                    @if (auth()->user()->hasPermission('email.configure'))
                                        <form method="POST" action="{{ route('admin.email.suppressions.destroy', $s) }}"
                                              onsubmit="return confirm('Allow email to {{ $s->email }} again? Only do this if you know the address is valid.')">
                                            @csrf @method('DELETE')
                                            <button class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-muted)] hover:bg-gray-50">Remove</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center">
                                    <span class="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-full bg-emerald-50 text-emerald-600">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>
                                    </span>
                                    <p class="text-sm font-semibold text-[var(--color-heading)]">No suppressed addresses</p>
                                    <p class="mt-1 text-xs text-gray-400">Nothing has bounced or been reported as spam.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($suppressions->hasPages())
                <div class="border-t border-gray-100 px-5 py-3">{{ $suppressions->links() }}</div>
            @endif
        </section>

        @if (auth()->user()->hasPermission('email.configure'))
            <section class="self-start rounded-xl border border-gray-100 bg-white shadow-sm">
                <header class="border-b border-gray-100 px-5 py-3.5">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Add an address</h2>
                </header>
                <form method="POST" action="{{ route('admin.email.suppressions.store') }}" class="space-y-3 p-5">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Email address <span class="text-red-500">*</span></label>
                        <input name="email" type="email" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Note</label>
                        <input name="note" maxlength="255" placeholder="Why is this being blocked?" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>
                    <button class="w-full rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Block this address</button>
                </form>
            </section>
        @endif
    </div>
@endsection
