@extends('admin.layouts.app')
@section('title', 'Staff')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Staff / Employees</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">{{ $staff->total() }} staff member(s) — they log in to the panel and get leads assigned.</p>
        </div>
        <a href="{{ route('admin.staff.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Staff
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Staff</th>
                        <th class="px-5 py-3 font-semibold">Email</th>
                        <th class="px-5 py-3 font-semibold">Job Title</th>
                        <th class="px-5 py-3 font-semibold">Assigned Leads</th>
                        <th class="px-5 py-3 font-semibold">Access</th>
                        <th class="px-5 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($staff as $s)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($s->photo_url)
                                        <img src="{{ $s->photo_url }}" alt="{{ $s->name }}" class="h-9 w-9 rounded-full object-cover">
                                    @else
                                        <span class="grid h-9 w-9 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]">{{ strtoupper(substr($s->name, 0, 1)) }}</span>
                                    @endif
                                    <span class="font-semibold text-[var(--color-heading)]">{{ $s->name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $s->email }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $s->job_title ?? '—' }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $s->assigned_leads_count }}</td>
                            <td class="px-5 py-3">
                                @php $overrides = count((array) $s->permissions); @endphp
                                @if ($s->assignedRole)
                                    <span class="rounded-full bg-[var(--color-primary-soft)] px-2.5 py-0.5 text-[11px] font-semibold text-[var(--color-primary)]">{{ $s->assignedRole->name }}</span>
                                @else
                                    <span class="text-xs text-gray-400">No role</span>
                                @endif
                                @if ($overrides)<span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500" title="Per-user overrides">+{{ $overrides }} custom</span>@endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.staff.permissions', $s) }}" class="rounded-lg p-2 text-gray-400 hover:bg-amber-50 hover:text-amber-600" title="Permissions">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.5 7.5a4.5 4.5 0 1 0-4.9 4.48L4 19v2h3l1-1v-2h2l1-1v-2l1.02-1.02A4.5 4.5 0 0 0 15.5 7.5Z M16.5 7h.01"/></svg>
                                    </a>
                                    <a href="{{ route('admin.staff.edit', $s) }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-primary)]" title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('admin.staff.destroy', $s) }}" onsubmit="return confirm('Remove this staff member?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">No staff yet — <a href="{{ route('admin.staff.create') }}" class="font-semibold text-[var(--color-primary)] hover:underline">add your first staff member</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $staff->links() }}</div>
@endsection
