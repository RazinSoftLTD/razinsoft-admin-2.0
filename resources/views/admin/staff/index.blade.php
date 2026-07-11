@extends('admin.layouts.app')
@section('title', 'Employees')

@section('content')
    <div x-data="{ selRole: {} }">
        {{-- Filter bar --}}
        <form method="GET" class="mb-5 flex flex-wrap items-center gap-x-6 gap-y-3 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="text-sm text-[var(--color-muted)]">Employee</span>
                <select name="role" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    <option value="">All</option>
                    <option value="admin" @selected($role === 'admin')>Admin</option>
                    <option value="staff" @selected($role === 'staff')>Staff</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm text-[var(--color-muted)]">Designation</span>
                <select name="designation" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    <option value="">All</option>
                    @foreach ($designations as $d)<option value="{{ $d->id }}" @selected((string) $designationId === (string) $d->id)>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div class="relative min-w-56 flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14ZM21 21l-4.3-4.3"/></svg>
                <input name="search" value="{{ $search }}" placeholder="Start typing to search" class="h-9 w-full rounded-lg border border-gray-200 pl-9 pr-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
            </div>
        </form>

        {{-- Actions --}}
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.staff.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Employee
            </a>
        </div>

        {{-- Table --}}
        <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="w-10 px-5 py-3"><input type="checkbox" x-on:change="$root.querySelectorAll('.row-check').forEach(c => c.checked = $event.target.checked)" class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"></th>
                            <th class="px-5 py-3">Employee ID</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Email</th>
                            <th class="px-5 py-3">User Role</th>
                            <th class="px-5 py-3">Reporting To</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($staff as $s)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4 align-top"><input type="checkbox" value="{{ $s->id }}" class="row-check h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"></td>
                                <td class="px-5 py-4 align-top font-semibold text-[var(--color-heading)]">{{ $s->employee_code ?? '—' }}</td>
                                <td class="px-5 py-4 align-top">
                                    <div class="flex items-center gap-3">
                                        @if ($s->photo_url)
                                            <img src="{{ $s->photo_url }}" alt="" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span class="grid h-9 w-9 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]">{{ strtoupper(substr($s->name, 0, 1)) }}</span>
                                        @endif
                                        <span class="leading-tight">
                                            <span class="block font-semibold text-[var(--color-heading)]">{{ $s->name }}</span>
                                            @if ($s->designation)<span class="block text-xs text-[var(--color-muted)]">{{ $s->designation->name }}</span>@endif
                                            @if ($s->employment_type === 'probation')<span class="mt-1 inline-block rounded bg-teal-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">On Probation</span>@endif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 align-top text-[var(--color-muted)]">{{ $s->email }}</td>
                                <td class="px-5 py-4 align-top">
                                    <form method="POST" action="{{ route('admin.staff.role', $s) }}">
                                        @csrf @method('PATCH')
                                        <select name="role_id" onchange="this.form.submit()" class="h-9 w-40 rounded-lg border border-gray-200 bg-white px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                            <option value="">No role</option>
                                            @foreach ($roles as $r)<option value="{{ $r->id }}" @selected($s->role_id == $r->id)>{{ $r->name }}</option>@endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="px-5 py-4 align-top text-[var(--color-muted)]">{{ $s->reportsTo->name ?? '—' }}</td>
                                <td class="px-5 py-4 align-top">
                                    @if ($s->status === \App\Models\User::STATUS_ACTIVE)
                                        <span class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Active</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-400"><span class="h-2 w-2 rounded-full bg-gray-300"></span> Inactive</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="relative flex justify-end" x-data="{ open: false, x: 0, y: 0, place(b) { const r = b.getBoundingClientRect(); this.y = r.bottom + 4; this.x = r.right; } }">
                                        <button @click="open = !open; if (open) place($el)" @click.outside="open = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                                        </button>
                                        <div x-show="open" x-cloak @click="open = false" :style="`top:${y}px; left:${x - 176}px`" class="fixed z-50 w-44 rounded-lg border border-gray-100 bg-white py-1 text-sm shadow-xl ring-1 ring-black/5">
                                            @if ($s->isStaff())
                                                <a href="{{ route('admin.staff.edit', $s) }}" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">Edit</a>
                                                <a href="{{ route('admin.staff.permissions', $s) }}" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">Permissions</a>
                                                <div class="my-1 border-t border-gray-100"></div>
                                                <form method="POST" action="{{ route('admin.staff.destroy', $s) }}" onsubmit="return confirm('Remove this employee?')">
                                                    @csrf @method('DELETE')
                                                    <button class="block w-full px-4 py-2 text-left text-red-600 hover:bg-red-50">Delete</button>
                                                </form>
                                            @else
                                                <span class="block px-4 py-2 text-gray-400">Admin — protected</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400">No employees found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-gray-100 px-5 py-3 text-sm text-[var(--color-muted)]">
                <form method="GET" class="flex items-center gap-2">
                    @foreach (request()->except(['per_page', 'page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <span>Show</span>
                    <select name="per_page" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        @foreach ([10, 25, 50, 100] as $n)<option value="{{ $n }}" @selected((int) request('per_page', 10) === $n)>{{ $n }}</option>@endforeach
                    </select>
                    <span>entries</span>
                </form>
                @if ($staff->total())<span>Showing {{ $staff->firstItem() }} to {{ $staff->lastItem() }} of {{ $staff->total() }} entries</span>@endif
                <div>{{ $staff->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </div>
@endsection
