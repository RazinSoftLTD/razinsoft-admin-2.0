@extends('admin.layouts.app')
@section('title', 'Recurring Invoices')

@php $cur = ['USD' => '$', 'BDT' => '৳', 'EUR' => '€', 'GBP' => '£']; @endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Recurring Invoices</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Auto-generate invoices on a schedule.</p>
        </div>
        <a href="{{ route('admin.recurring.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> New Recurring
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr><th class="px-5 py-3 font-semibold">Title / Client</th><th class="px-5 py-3 font-semibold">Interval</th><th class="px-5 py-3 font-semibold">Next Run</th><th class="px-5 py-3 font-semibold">Generated</th><th class="px-5 py-3 font-semibold">Active</th><th class="px-5 py-3 text-right font-semibold">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($profiles as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3"><p class="font-semibold text-[var(--color-heading)]">{{ $p->title ?: 'Recurring' }}</p><p class="text-xs text-[var(--color-muted)]">{{ $p->client?->name ?? '—' }}</p></td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ \App\Models\RecurringInvoice::INTERVALS[$p->interval] ?? $p->interval }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->next_run_at->format('d M Y') }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $p->generated_count }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $p->active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">{{ $p->active ? 'Active' : 'Paused' }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <form method="POST" action="{{ route('admin.recurring.run', $p) }}" onsubmit="return confirm('Generate an invoice now?')">@csrf
                                        <button class="rounded-lg p-2 text-emerald-600 hover:bg-emerald-50" title="Generate now"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3l14 9-14 9V3Z"/></svg></button>
                                    </form>
                                    <a href="{{ route('admin.recurring.edit', $p) }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Edit"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></a>
                                    <form method="POST" action="{{ route('admin.recurring.destroy', $p) }}" onsubmit="return confirm('Delete this profile?')">@csrf @method('DELETE')<button class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg></button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">No recurring profiles yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $profiles->links() }}</div>
@endsection
