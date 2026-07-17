@extends('admin.layouts.app')
@section('title', 'Project Config')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Project Config</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Settings &rsaquo; Project Config — manage everything project & task management uses.</p>
    </div>

    @if (session('error'))<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>@endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Categories --}}
        <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Project Categories</h2>
                <p class="text-xs text-[var(--color-muted)]">Shown in the project form's category dropdown.</p>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.project-config.categories.store') }}" class="mb-4 flex items-center gap-2">
                    @csrf
                    <input type="text" name="name" required placeholder="New category name" class="h-10 flex-1 rounded-lg border-gray-200 text-sm">
                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                </form>
                @error('name')<p class="mb-3 text-xs text-red-600">{{ $message }}</p>@enderror
                <ul class="divide-y divide-gray-50">
                    @forelse ($categories as $cat)
                        <li class="flex items-center gap-2 py-2.5" x-data="{ edit: false }">
                            <template x-if="!edit">
                                <span class="flex flex-1 items-center justify-between">
                                    <span class="text-sm text-[var(--color-heading)]">{{ $cat->name }}</span>
                                    <span class="flex items-center gap-1">
                                        <button type="button" @click="edit = true" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg></button>
                                        <form method="POST" action="{{ route('admin.project-config.categories.destroy', $cat) }}" onsubmit="return confirm('Remove this category?')">@csrf @method('DELETE')
                                            <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                                        </form>
                                    </span>
                                </span>
                            </template>
                            <form x-show="edit" x-cloak method="POST" action="{{ route('admin.project-config.categories.update', $cat) }}" class="flex flex-1 items-center gap-2">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $cat->name }}" required class="h-9 flex-1 rounded-lg border-gray-200 text-sm">
                                <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                                <button type="button" @click="edit = false" class="text-xs text-gray-400">Cancel</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-gray-300">No categories yet.</li>
                    @endforelse
                </ul>
            </div>
        </section>

        {{-- Default board columns --}}
        <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Default Board Columns</h2>
                <p class="text-xs text-[var(--color-muted)]">New projects start with these columns. Each project can then add or remove its own.</p>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.project-config.columns.store') }}" class="mb-4 flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="text" name="name" required placeholder="Column name" class="h-10 flex-1 rounded-lg border-gray-200 text-sm">
                    <input type="color" name="color" value="#3b82f6" class="h-10 w-12 cursor-pointer rounded-lg border-gray-200 p-1">
                    <label class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_done" value="1" class="rounded accent-[var(--color-primary)]"> Done</label>
                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                </form>
                <ul class="space-y-2">
                    @foreach ($columns as $col)
                        <li class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2.5" x-data="{ edit: false }">
                            <template x-if="!edit">
                                <span class="flex flex-1 items-center gap-2">
                                    <span class="h-3 w-3 rounded-full" style="background: {{ $col->color }}"></span>
                                    <span class="flex-1 text-sm font-medium text-[var(--color-heading)]">{{ $col->name }}</span>
                                    @if ($col->is_done)<span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-600">DONE</span>@endif
                                    @if ($col->is_excluded)<span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-500">EXCLUDED</span>@endif
                                    <button type="button" @click="edit = true" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.9 4.5a2.1 2.1 0 0 1 3 3L8 19.5l-4 1 1-4L16.9 4.5Z"/></svg></button>
                                    <form method="POST" action="{{ route('admin.project-config.columns.destroy', $col) }}" onsubmit="return confirm('Remove this default column?')">@csrf @method('DELETE')
                                        <button class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                                    </form>
                                </span>
                            </template>
                            <form x-show="edit" x-cloak method="POST" action="{{ route('admin.project-config.columns.update', $col) }}" class="flex flex-1 flex-wrap items-center gap-2">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $col->name }}" required class="h-9 flex-1 rounded-lg border-gray-200 text-sm">
                                <input type="color" name="color" value="{{ $col->color }}" class="h-9 w-11 cursor-pointer rounded-lg border-gray-200 p-1">
                                <label class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_done" value="1" @checked($col->is_done) class="rounded accent-[var(--color-primary)]"> Done</label>
                                <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                                <button type="button" @click="edit = false" class="text-xs text-gray-400">Cancel</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-gray-400">Changing defaults affects <strong>new</strong> projects only — existing projects keep their own columns.</p>
            </div>
        </section>
    </div>
@endsection
