@extends('admin.layouts.app')
@section('title', 'Designations')

@section('content')
    <div x-data="{ edit: { open: false, id: null, name: '' } }">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Designations</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Job titles employees can be assigned to.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[320px_1fr]">
            {{-- Add form --}}
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm lg:sticky lg:top-20 lg:self-start">
                <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Add Designations</h2>
                <form method="POST" action="{{ route('admin.designations.store') }}" class="space-y-3">
                    @csrf
                    <input name="name" value="{{ old('name') }}" required placeholder="e.g. {{ 'designation' === 'designation' ? 'Senior Developer' : 'Engineering' }}" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <button class="w-full rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                </form>
            </div>

            {{-- List --}}
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Name</th>
                            <th class="px-5 py-3 font-semibold">Employees</th>
                            <th class="px-5 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 font-medium text-[var(--color-heading)]">{{ $item->name }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $item->employees_count }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <button type="button" @click="edit = { open: true, id: {{ $item->id }}, name: @js($item->name) }" class="text-sm font-semibold text-[var(--color-primary)] hover:underline">Edit</button>
                                        <form method="POST" action="{{ route('admin.designations.destroy', $item) }}" onsubmit="return confirm('Delete this?')">
                                            @csrf @method('DELETE')
                                            <button class="text-sm font-semibold text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-12 text-center text-gray-400">No Designations yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Edit modal --}}
        <div x-show="edit.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="edit.open = false">
            <form method="POST" :action="'{{ url('admin/designations') }}/' + edit.id" class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                @csrf @method('PUT')
                <h3 class="text-base font-bold text-[var(--color-heading)]">Edit Designations</h3>
                <input name="name" x-model="edit.name" required class="mt-4 h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="edit.open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                </div>
            </form>
        </div>
    </div>
@endsection
