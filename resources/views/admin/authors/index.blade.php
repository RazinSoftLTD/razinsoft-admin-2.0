@extends('admin.layouts.app')
@section('title', 'Authors')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <a href="{{ route('admin.articles.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to articles
        </a>
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Authors</h1>
    </div>

    <div class="max-w-2xl space-y-4">
        @forelse ($authors as $a)
            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm" x-data="{ edit: false }">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3" x-show="!edit">
                        @if ($a->photo)
                            <img src="{{ \App\Http\Resources\ProductResource::media($a->photo) }}" class="h-11 w-11 shrink-0 rounded-full border border-gray-100 object-cover">
                        @else
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]">{{ \Illuminate\Support\Str::of($a->name)->explode(' ')->map(fn($p)=>$p[0]??'')->take(2)->implode('') }}</span>
                        @endif
                        <div class="min-w-0">
                            <p class="font-semibold text-[var(--color-heading)]">{{ $a->name }}</p>
                            <p class="text-xs text-gray-400">{{ $a->role ?? 'Author' }} · {{ $a->articles_count }} article(s)</p>
                        </div>
                    </div>
                    <p class="font-semibold text-[var(--color-heading)]" x-show="edit">Edit author</p>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" @click="edit=!edit" class="text-xs font-semibold text-[var(--color-primary)] hover:underline" x-text="edit ? 'Close' : 'Edit'"></button>
                        <x-admin.del-button :action="route('admin.authors.destroy', $a)" />
                    </div>
                </div>

                <form x-show="edit" x-cloak method="POST" action="{{ route('admin.authors.update', $a) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf @method('PUT')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-admin.field label="Name" name="name" :value="$a->name" required />
                        <x-admin.field label="Role" name="role" :value="$a->role" placeholder="Content Writer" />
                    </div>
                    <x-admin.field label="Bio" name="bio" type="textarea" :rows="2" :value="$a->bio" />
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Profile photo</label>
                        <input type="file" name="photo" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                        <p class="mt-1 text-xs text-gray-400">Leave empty to keep the current photo.</p>
                    </div>
                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save author</button>
                </form>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-gray-200 bg-white py-10 text-center text-sm text-gray-400">No authors yet.</p>
        @endforelse

        <x-admin.add-form :action="route('admin.authors.store')" enctype="multipart/form-data" title="Add author">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-admin.field label="Name" name="name" required placeholder="Sarah Johnson" />
                <x-admin.field label="Role" name="role" placeholder="Content Writer" />
            </div>
            <x-admin.field label="Bio" name="bio" type="textarea" :rows="2" />
            <div>
                <label class="mb-1.5 block text-sm font-medium">Profile photo</label>
                <input type="file" name="photo" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
            </div>
        </x-admin.add-form>
    </div>
@endsection
