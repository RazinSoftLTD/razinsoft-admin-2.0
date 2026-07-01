@extends('admin.layouts.app')
@section('title', 'Blog Categories')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <a href="{{ route('admin.articles.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to articles
        </a>
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Blog Categories</h1>
    </div>

    <div class="max-w-2xl space-y-4">
        @forelse ($categories as $c)
            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm" x-data="{ edit: false }">
                <div class="flex items-center justify-between gap-3">
                    <div x-show="!edit">
                        <p class="font-semibold text-[var(--color-heading)]">{{ $c->name }}</p>
                        <p class="text-xs text-gray-400">/{{ $c->slug }} · {{ $c->articles_count }} article(s)</p>
                    </div>
                    <form x-show="edit" x-cloak method="POST" action="{{ route('admin.article-categories.update', $c) }}" class="flex flex-1 items-center gap-2">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $c->name }}" required class="h-9 flex-1 rounded-lg border border-gray-200 px-3 text-sm">
                        <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                    </form>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" @click="edit=!edit" class="text-xs font-semibold text-[var(--color-primary)] hover:underline" x-text="edit ? 'Close' : 'Edit'"></button>
                        <x-admin.del-button :action="route('admin.article-categories.destroy', $c)" />
                    </div>
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-gray-200 bg-white py-10 text-center text-sm text-gray-400">No categories yet.</p>
        @endforelse

        <x-admin.add-form :action="route('admin.article-categories.store')" title="Add category">
            <x-admin.field label="Name" name="name" required placeholder="AI & Automation" />
        </x-admin.add-form>
    </div>
@endsection
