@extends('admin.layouts.app')
@section('title', 'Invoice Templates')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Invoice Templates</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Reusable line-item presets for the Create Invoice page.</p>
        </div>
        <a href="{{ route('admin.invoice-templates.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> New Template
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr><th class="px-5 py-3 font-semibold">Name</th><th class="px-5 py-3 font-semibold">Items</th><th class="px-5 py-3 font-semibold">Currency</th><th class="px-5 py-3 text-right font-semibold">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($templates as $t)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $t->name }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ count($t->items) }} line(s)</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $t->currency }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.invoices.create', ['template' => $t->id]) }}" class="rounded-lg p-2 text-emerald-600 hover:bg-emerald-50" title="Use in new invoice"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4a1 1 0 0 0 1 1h4M5 3h9l5 5v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg></a>
                                    <a href="{{ route('admin.invoice-templates.edit', $t) }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Edit"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></a>
                                    <form method="POST" action="{{ route('admin.invoice-templates.destroy', $t) }}" onsubmit="return confirm('Delete this template?')">@csrf @method('DELETE')<button class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg></button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-gray-400">No templates yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $templates->links() }}</div>
@endsection
