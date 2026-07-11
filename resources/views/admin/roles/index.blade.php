@extends('admin.layouts.app')
@section('title', 'Roles & Permissions')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Roles &amp; Permissions</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Define what each role can do. Assign a role to staff, then fine-tune per person if needed.</p>
        </div>
        <a href="{{ route('admin.roles.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            New Role
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Role</th>
                    <th class="px-5 py-3 font-semibold">Permissions</th>
                    <th class="px-5 py-3 font-semibold">Staff</th>
                    <th class="px-5 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($roles as $role)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-[var(--color-heading)]">{{ $role->name }}
                                @if ($role->is_system)<span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500">system</span>@endif
                            </p>
                            @if ($role->description)<p class="text-xs text-[var(--color-muted)]">{{ $role->description }}</p>@endif
                        </td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ count($role->permissions ?? []) }} permission(s)</td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $role->users_count }}</td>
                        <td class="px-5 py-3">
                            <div class="relative flex justify-end" x-data="{ open: false, x: 0, y: 0, place(b) { const r = b.getBoundingClientRect(); this.y = r.bottom + 4; this.x = r.right; } }">
                                <button @click="open = !open; if (open) place($el)" @click.outside="open = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                                </button>
                                <div x-show="open" x-cloak @click="open = false" :style="`top:${y}px; left:${x - 192}px`" class="fixed z-50 w-48 rounded-lg border border-gray-100 bg-white py-1 text-sm shadow-xl ring-1 ring-black/5">
                                    @if ($role->name !== 'Root Admin')
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">Edit permission</a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.roles.duplicate', $role) }}">
                                        @csrf
                                        <button class="block w-full px-4 py-2 text-left text-[var(--color-heading)] hover:bg-gray-50">Duplicate permission</button>
                                    </form>
                                    @unless ($role->is_system)
                                        <div class="my-1 border-t border-gray-100"></div>
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete role “{{ $role->name }}”?')">
                                            @csrf @method('DELETE')
                                            <button class="block w-full px-4 py-2 text-left text-red-600 hover:bg-red-50">Delete permission</button>
                                        </form>
                                    @else
                                        <div class="my-1 border-t border-gray-100"></div>
                                        <span class="block px-4 py-2 text-xs text-gray-400">System role — can’t delete</span>
                                    @endunless
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
