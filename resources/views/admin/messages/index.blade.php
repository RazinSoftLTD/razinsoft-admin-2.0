@extends('admin.layouts.app')
@section('title', 'Contact Us')

@section('content')
    @php
        $me = auth()->user();
        $canEdit = $me->hasPermission('messages.edit');
        $canDelete = $me->hasPermission('messages.delete');
    @endphp

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-[var(--color-heading)]">Contact Us</h1>
            <p class="mt-0.5 text-sm text-[var(--color-muted)]">{{ $messages->total() }} enquiry(ies) from the website.</p>
        </div>
    </div>

    {{-- Filter bar --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
        <div class="relative min-w-[220px] flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            <input type="text" name="search" value="{{ $search }}" placeholder="Search name, email or phone…" class="h-9 w-full rounded-lg border-gray-200 pl-9 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
        </div>
        <div class="flex items-center gap-2">
            <span class="text-sm text-[var(--color-muted)]">Status</span>
            <select name="status" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                <option value="">All</option>
                @foreach (\App\Models\ContactMessage::STATUSES as $k => $label)
                    <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="h-9 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Filter</button>
        @if ($search || $status)<a href="{{ route('admin.messages.index') }}" class="text-sm font-semibold text-[var(--color-muted)] hover:underline">Reset</a>@endif
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                        <th class="px-5 py-3 font-semibold">ID</th>
                        <th class="px-5 py-3 font-semibold">Name</th>
                        <th class="px-5 py-3 font-semibold">Email</th>
                        <th class="px-5 py-3 font-semibold">Phone</th>
                        <th class="px-5 py-3 font-semibold">Created At</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($messages as $m)
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/60 {{ $m->is_read ? '' : 'bg-[var(--color-primary-soft)]/30' }}">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.messages.show', $m) }}" class="font-semibold text-[var(--color-primary)] hover:underline">#{{ $m->id }}</a>
                            </td>
                            <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ $m->name }}</td>
                            <td class="px-5 py-3"><a href="mailto:{{ $m->email }}" class="text-[var(--color-primary)] hover:underline">{{ $m->email }}</a></td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $m->phone ?: '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $m->created_at->format('d M Y, g:i A') }}</td>
                            <td class="px-5 py-3">
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('admin.messages.status', $m) }}">
                                        @csrf @method('PATCH')
                                        <select name="status" onchange="this.form.submit()" class="h-8 rounded-lg border-0 px-2 text-xs font-semibold {{ $m->statusStyle() }} focus:ring-1 focus:ring-[var(--color-primary)]">
                                            @foreach (\App\Models\ContactMessage::STATUSES as $k => $label)
                                                <option value="{{ $k }}" @selected($m->status === $k)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $m->statusStyle() }}">{{ $m->statusLabel() }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.messages.show', $m) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-indigo-50">View</a>
                                    @if ($canDelete)
                                        <x-admin.del-button :action="route('admin.messages.destroy', $m)" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">No enquiries found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $messages->links() }}</div>
@endsection
