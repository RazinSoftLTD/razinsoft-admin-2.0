@extends('admin.layouts.app')
@section('title', $sectionTitle . ' · ' . $product->name)

@php
    $del = fn($rel, $id) => route('admin.products.relation.destroy', [$product, $rel, $id]);
    $add = fn($rel) => route('admin.products.relation.store', [$product, $rel]);
    $edit = fn($rel, $id) => route('admin.products.relation.update', [$product, $rel, $id]);
    $move = fn($id) => route('admin.products.gallery.move', [$product, $id]);
    $demoTypes = ['live' => 'Live Demo', 'admin' => 'Admin Demo', 'customer' => 'Customer Demo', 'web' => 'Web App', 'android' => 'Android App', 'ios' => 'iOS App', 'download' => 'Download', 'link' => 'Other Link'];
@endphp

@section('content')
    <a href="{{ route('admin.products.show', $product) }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to {{ $product->name }}
    </a>

    <h1 class="mb-5 text-xl font-bold text-[var(--color-heading)]">{{ $sectionTitle }}</h1>

    @switch($relation)
        @case('plans')
            <div class="max-w-3xl space-y-4">
                @foreach ($product->plans as $pl)
                    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm" x-data="{ edit: false }">
                        <div class="flex items-start justify-between gap-3">
                            <div x-show="!edit"><p class="font-bold">{{ $pl->name }} — ${{ number_format($pl->price,2) }} @if($pl->is_popular)<span class="ml-1 rounded bg-[var(--color-primary-soft)] px-1.5 py-0.5 text-[11px] font-semibold text-[var(--color-primary)]">Popular</span>@endif</p><p class="mt-1 text-xs text-gray-400">{{ collect($pl->perks)->implode(', ') }}</p></div>
                            <p x-show="edit" class="font-bold">Edit plan</p>
                            <div class="flex shrink-0 items-center gap-2">
                                <button type="button" @click="edit=!edit" class="text-xs font-semibold text-[var(--color-primary)] hover:underline" x-text="edit ? 'Close' : 'Edit'"></button>
                                <x-admin.del-button :action="$del('plans', $pl->id)" />
                            </div>
                        </div>
                        <form x-show="edit" x-cloak method="POST" action="{{ $edit('plans', $pl->id) }}" class="mt-3 space-y-3">
                            @csrf @method('PUT')
                            <div class="grid gap-3 sm:grid-cols-3">
                                <x-admin.field label="Name" name="name" :value="$pl->name" required />
                                <x-admin.field label="Price" name="price" type="number" :value="$pl->price" required />
                                <x-admin.field label="Sort order" name="sort_order" type="number" :value="$pl->sort_order" />
                            </div>
                            <x-admin.field label="Blurb" name="blurb" :value="$pl->blurb" />
                            <x-admin.field label="Perks (one per line)" name="perks" type="textarea" :value="collect($pl->perks)->implode(PHP_EOL)" />
                            <x-admin.field name="is_popular" type="checkbox" label="Most popular" :value="$pl->is_popular" />
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save plan</button>
                        </form>
                    </div>
                @endforeach
                <x-admin.add-form :action="$add('plans')" title="Add plan">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <x-admin.field label="Name" name="name" required />
                        <x-admin.field label="Price" name="price" type="number" required />
                        <x-admin.field label="Sort order" name="sort_order" type="number" />
                    </div>
                    <x-admin.field label="Blurb" name="blurb" />
                    <x-admin.field label="Perks (one per line)" name="perks" type="textarea" />
                    <x-admin.field name="is_popular" type="checkbox" label="Most popular" />
                </x-admin.add-form>
            </div>
            @break

        @case('features')
            <div class="max-w-3xl space-y-4">
                @foreach ($product->features as $f)
                    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm" x-data="{ edit: false }">
                        <div class="flex items-start justify-between gap-3">
                            <div x-show="!edit"><p class="font-bold">{{ $f->title }}</p><p class="text-xs text-[var(--color-primary)]">{{ $f->subtitle }}</p><p class="mt-1 text-sm text-gray-500">{{ $f->description }}</p></div>
                            <p x-show="edit" class="font-bold">Edit feature</p>
                            <div class="flex shrink-0 items-center gap-2">
                                <button type="button" @click="edit=!edit" class="text-xs font-semibold text-[var(--color-primary)] hover:underline" x-text="edit ? 'Close' : 'Edit'"></button>
                                <x-admin.del-button :action="$del('features', $f->id)" />
                            </div>
                        </div>
                        <form x-show="edit" x-cloak method="POST" action="{{ $edit('features', $f->id) }}" class="mt-3 space-y-3">
                            @csrf @method('PUT')
                            <div class="grid gap-3 sm:grid-cols-2"><x-admin.field label="Title" name="title" :value="$f->title" required /><x-admin.field label="Subtitle" name="subtitle" :value="$f->subtitle" /></div>
                            <x-admin.field label="Description" name="description" type="textarea" :value="$f->description" />
                            <div class="grid gap-3 sm:grid-cols-3"><x-admin.field label="Icon" name="icon" :value="$f->icon" /><x-admin.field label="Color" name="color" :value="$f->color" /><x-admin.field label="Sort order" name="sort_order" type="number" :value="$f->sort_order" /></div>
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save feature</button>
                        </form>
                    </div>
                @endforeach
                <x-admin.add-form :action="$add('features')" title="Add feature">
                    <div class="grid gap-3 sm:grid-cols-2"><x-admin.field label="Title" name="title" required /><x-admin.field label="Subtitle" name="subtitle" /></div>
                    <x-admin.field label="Description" name="description" type="textarea" />
                    <div class="grid gap-3 sm:grid-cols-3"><x-admin.field label="Icon" name="icon" /><x-admin.field label="Color" name="color" /><x-admin.field label="Sort order" name="sort_order" type="number" /></div>
                </x-admin.add-form>
            </div>
            @break

        @case('gallery')
            <div class="max-w-3xl space-y-5">
                @foreach ($product->galleryGroups as $g)
                    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm" x-data="{ edit: false }">
                        <div class="flex items-center justify-between">
                            <p class="font-bold" x-show="!edit">{{ $g->name }}</p>
                            <form x-show="edit" x-cloak method="POST" action="{{ $edit('gallery-groups', $g->id) }}" class="flex items-center gap-2">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $g->name }}" required class="h-9 rounded-lg border border-gray-200 px-2 text-sm">
                                <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                            </form>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="edit=!edit" class="text-xs font-semibold text-[var(--color-primary)] hover:underline" x-text="edit ? 'Close' : 'Rename'"></button>
                                <x-admin.del-button :action="$del('gallery-groups', $g->id)" />
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-5">
                            @foreach ($g->images as $iLoop => $img)
                                <div class="group relative" x-data="{ cap: false }">
                                    <span class="absolute left-1 top-1 z-10 rounded-md bg-black/50 px-1.5 text-[10px] font-bold text-white">{{ $iLoop + 1 }}</span>
                                    <img src="{{ \App\Http\Resources\ProductResource::media($img->image) }}" class="h-20 w-full rounded-lg border border-gray-100 object-cover">
                                    {{-- Reorder: move earlier / later within the group --}}
                                    <div class="absolute inset-x-1 bottom-1 flex justify-between opacity-0 transition-opacity group-hover:opacity-100">
                                        <form method="POST" action="{{ $move($img->id) }}">@csrf<input type="hidden" name="direction" value="up"><button {{ $iLoop === 0 ? 'disabled' : '' }} class="rounded-md bg-white/90 p-1 text-gray-700 shadow disabled:opacity-30" title="Move earlier"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg></button></form>
                                        <form method="POST" action="{{ $move($img->id) }}">@csrf<input type="hidden" name="direction" value="down"><button {{ $iLoop === $g->images->count() - 1 ? 'disabled' : '' }} class="rounded-md bg-white/90 p-1 text-gray-700 shadow disabled:opacity-30" title="Move later"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg></button></form>
                                    </div>
                                    <div class="absolute right-1 top-1 flex gap-1">
                                        <button type="button" @click="cap=!cap" class="rounded-md bg-white/90 p-1 text-gray-600 shadow" title="Edit caption"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                                        <form method="POST" action="{{ $del('gallery-images', $img->id) }}">@csrf @method('DELETE')<button class="rounded-md bg-white/90 p-1 text-red-600 shadow"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button></form>
                                    </div>
                                    <form x-show="cap" x-cloak method="POST" action="{{ $edit('gallery-images', $img->id) }}" class="mt-1 flex gap-1">
                                        @csrf @method('PUT')
                                        <input name="caption" value="{{ $img->caption }}" placeholder="Caption" class="h-8 w-full rounded border border-gray-200 px-1.5 text-xs">
                                        <button class="rounded bg-[var(--color-primary)] px-2 text-xs font-semibold text-white">✓</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ $add('gallery-images') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2">
                            @csrf <input type="hidden" name="gallery_group_id" value="{{ $g->id }}">
                            <input type="file" name="image" accept="image/*" required class="text-xs file:mr-2 file:rounded file:border-0 file:bg-[var(--color-primary-soft)] file:px-2 file:py-1 file:text-xs file:font-semibold file:text-[var(--color-primary)]">
                            <input type="text" name="caption" placeholder="Caption" class="h-9 rounded-lg border border-gray-200 px-2 text-sm">
                            <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Upload</button>
                            <p class="w-full text-xs text-[var(--color-muted)]">{{ \App\Support\ImageSpecs::hint('gallery') }}</p>
                        </form>
                    </div>
                @endforeach
                <x-admin.add-form :action="$add('gallery-groups')" title="Add gallery group">
                    <x-admin.field label="Group name" name="name" required placeholder="Website / Admin / Mobile App" />
                </x-admin.add-form>
            </div>
            @break

        @case('demos')
            <div class="max-w-3xl space-y-4">
                <p class="text-sm text-[var(--color-muted)]">Add any number of live demos, app downloads or links. They appear on the product page under <strong>Try It Live</strong>, in the order below.</p>
                @forelse ($product->demos as $d)
                    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm" x-data="{ edit: false }">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0" x-show="!edit">
                                <p class="font-bold">{{ $d->title }}
                                    <span class="ml-1 rounded bg-[var(--color-primary-soft)] px-1.5 py-0.5 text-[11px] font-semibold text-[var(--color-primary)]">{{ $demoTypes[$d->type] ?? $d->type }}</span>
                                    @if($d->badge)<span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-semibold text-gray-600">{{ $d->badge }}</span>@endif
                                </p>
                                @if($d->subtitle)<p class="text-xs text-gray-500">{{ $d->subtitle }}</p>@endif
                                <a href="{{ $d->url }}" target="_blank" class="text-xs text-[var(--color-primary)] hover:underline break-all">{{ $d->url }}</a>
                            </div>
                            <p x-show="edit" class="font-bold">Edit link</p>
                            <div class="flex shrink-0 items-center gap-2">
                                <button type="button" @click="edit=!edit" class="text-xs font-semibold text-[var(--color-primary)] hover:underline" x-text="edit ? 'Close' : 'Edit'"></button>
                                <x-admin.del-button :action="$del('demos', $d->id)" />
                            </div>
                        </div>
                        <form x-show="edit" x-cloak method="POST" action="{{ $edit('demos', $d->id) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                            @csrf @method('PUT')
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium">Type <span class="font-normal text-gray-400">(fallback icon)</span></label>
                                    <select name="type" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                        @foreach ($demoTypes as $val => $lbl)<option value="{{ $val }}" @selected($d->type === $val)>{{ $lbl }}</option>@endforeach
                                    </select>
                                </div>
                                <x-admin.field label="Title" name="title" :value="$d->title" required />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium">Icon <span class="font-normal text-gray-400">(optional image — overrides the type icon)</span></label>
                                <div class="flex items-center gap-3">
                                    @if ($d->icon)<img src="{{ \App\Http\Resources\ProductResource::media($d->icon) }}" class="h-9 w-9 rounded-md border border-gray-100 object-contain p-1">@endif
                                    <input type="file" name="icon" accept="image/*,.svg" class="text-xs file:mr-2 file:rounded file:border-0 file:bg-[var(--color-primary-soft)] file:px-2 file:py-1 file:text-xs file:font-semibold file:text-[var(--color-primary)]">
                                </div>
                                <p class="mt-1 text-xs text-[var(--color-muted)]">PNG/SVG, square recommended (e.g. 64×64). Leave empty to keep current / use the type icon.</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-admin.field label="Subtitle" name="subtitle" :value="$d->subtitle" />
                                <x-admin.field label="Badge" name="badge" :value="$d->badge" />
                            </div>
                            <x-admin.field label="URL" name="url" :value="$d->url" required />
                            <x-admin.field label="Sort order" name="sort_order" type="number" :value="$d->sort_order" />
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save link</button>
                        </form>
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-gray-200 bg-white py-8 text-center text-sm text-gray-400">No demo or download links yet.</p>
                @endforelse
                <x-admin.add-form :action="$add('demos')" enctype="multipart/form-data" title="Add demo / download link">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Type <span class="font-normal text-gray-400">(fallback icon)</span></label>
                            <select name="type" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                                @foreach ($demoTypes as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
                            </select>
                        </div>
                        <x-admin.field label="Title" name="title" required placeholder="Admin Demo" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Icon <span class="font-normal text-gray-400">(optional image — overrides the type icon)</span></label>
                        <input type="file" name="icon" accept="image/*,.svg" class="text-xs file:mr-2 file:rounded file:border-0 file:bg-[var(--color-primary-soft)] file:px-2 file:py-1 file:text-xs file:font-semibold file:text-[var(--color-primary)]">
                        <p class="mt-1 text-xs text-[var(--color-muted)]">PNG/SVG, square recommended (e.g. 64×64). Leave empty to use the type icon.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-admin.field label="Subtitle" name="subtitle" placeholder="Access the admin panel" />
                        <x-admin.field label="Badge (optional)" name="badge" placeholder="Live / APK / iOS" />
                    </div>
                    <x-admin.field label="URL" name="url" required placeholder="https://demo.example.com" />
                    <x-admin.field label="Sort order" name="sort_order" type="number" />
                </x-admin.add-form>
            </div>
            @break

        @case('tech')
            <div class="max-w-2xl space-y-4">
                <div class="space-y-2">
                    @foreach ($product->tech as $t)
                        <div class="rounded-lg border border-gray-100 bg-white px-4 py-2.5 shadow-sm" x-data="{ edit: false }">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold" x-show="!edit">{{ $t->name }}</span>
                                <form x-show="edit" x-cloak method="POST" action="{{ $edit('tech', $t->id) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $t->name }}" required placeholder="Name" class="h-9 rounded-lg border border-gray-200 px-2 text-sm">
                                    <input name="color" value="{{ $t->color }}" placeholder="Color" class="h-9 w-28 rounded-lg border border-gray-200 px-2 text-sm">
                                    <input name="sort_order" type="number" value="{{ $t->sort_order }}" placeholder="#" class="h-9 w-16 rounded-lg border border-gray-200 px-2 text-sm">
                                    <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                                </form>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="edit=!edit" class="text-xs font-semibold text-[var(--color-primary)] hover:underline" x-text="edit ? 'Close' : 'Edit'"></button>
                                    <x-admin.del-button :action="$del('tech', $t->id)" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <x-admin.add-form :action="$add('tech')" title="Add technology">
                    <div class="grid gap-3 sm:grid-cols-3"><x-admin.field label="Name" name="name" required /><x-admin.field label="Color" name="color" /><x-admin.field label="Sort order" name="sort_order" type="number" /></div>
                </x-admin.add-form>
            </div>
            @break

        @case('suitable')
            <div class="max-w-2xl space-y-4">
                <ul class="space-y-2">
                    @foreach ($product->suitableFor as $s)
                        <li class="rounded-lg border border-gray-100 bg-white px-4 py-2.5 text-sm shadow-sm" x-data="{ edit: false }">
                            <div class="flex items-center justify-between">
                                <span x-show="!edit">{{ $s->label }}</span>
                                <form x-show="edit" x-cloak method="POST" action="{{ $edit('suitable', $s->id) }}" class="flex flex-1 items-center gap-2">
                                    @csrf @method('PUT')
                                    <input name="label" value="{{ $s->label }}" required class="h-9 flex-1 rounded-lg border border-gray-200 px-2 text-sm">
                                    <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                                </form>
                                <div class="ml-2 flex items-center gap-2">
                                    <button type="button" @click="edit=!edit" class="text-xs font-semibold text-[var(--color-primary)] hover:underline" x-text="edit ? 'Close' : 'Edit'"></button>
                                    <x-admin.del-button :action="$del('suitable', $s->id)" />
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <x-admin.add-form :action="$add('suitable')" title="Add suitable-for"><x-admin.field label="Label" name="label" required /></x-admin.add-form>
            </div>
            @break

        @case('docs')
            <div class="max-w-2xl space-y-4">
                @foreach ($product->docs as $doc)
                    <div class="rounded-lg border border-gray-100 bg-white px-4 py-3 text-sm shadow-sm" x-data="{ edit: false }">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold" x-show="!edit">{{ $doc->title }}</span>
                            <p x-show="edit" class="font-semibold">Edit doc</p>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="edit=!edit" class="text-xs font-semibold text-[var(--color-primary)] hover:underline" x-text="edit ? 'Close' : 'Edit'"></button>
                                <x-admin.del-button :action="$del('docs', $doc->id)" />
                            </div>
                        </div>
                        <form x-show="edit" x-cloak method="POST" action="{{ $edit('docs', $doc->id) }}" class="mt-3 space-y-3">
                            @csrf @method('PUT')
                            <div class="grid gap-3 sm:grid-cols-3"><x-admin.field label="Title" name="title" :value="$doc->title" required /><x-admin.field label="Type" name="type" :value="$doc->type" /><x-admin.field label="URL" name="url" :value="$doc->url" /></div>
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save doc</button>
                        </form>
                    </div>
                @endforeach
                <x-admin.add-form :action="$add('docs')" title="Add doc">
                    <div class="grid gap-3 sm:grid-cols-3"><x-admin.field label="Title" name="title" required /><x-admin.field label="Type" name="type" /><x-admin.field label="URL" name="url" /></div>
                </x-admin.add-form>
            </div>
            @break

        @case('faqs')
            <div class="max-w-3xl space-y-4">
                @foreach ($product->faqs as $q)
                    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm" x-data="{ edit: false }">
                        <div class="flex items-start justify-between gap-3">
                            <div x-show="!edit"><p class="font-bold">{{ $q->question }}</p><p class="mt-1 text-sm text-gray-500">{{ $q->answer }}</p></div>
                            <p x-show="edit" class="font-bold">Edit FAQ</p>
                            <div class="flex shrink-0 items-center gap-2">
                                <button type="button" @click="edit=!edit" class="text-xs font-semibold text-[var(--color-primary)] hover:underline" x-text="edit ? 'Close' : 'Edit'"></button>
                                <x-admin.del-button :action="$del('faqs', $q->id)" />
                            </div>
                        </div>
                        <form x-show="edit" x-cloak method="POST" action="{{ $edit('faqs', $q->id) }}" class="mt-3 space-y-3">
                            @csrf @method('PUT')
                            <x-admin.field label="Question" name="question" :value="$q->question" required />
                            <x-admin.field label="Answer" name="answer" type="textarea" :value="$q->answer" />
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save FAQ</button>
                        </form>
                    </div>
                @endforeach
                <x-admin.add-form :action="$add('faqs')" title="Add FAQ"><x-admin.field label="Question" name="question" required /><x-admin.field label="Answer" name="answer" type="textarea" /></x-admin.add-form>
            </div>
            @break

        @case('files')
            <div class="max-w-2xl space-y-4">
                @foreach ($product->files as $file)
                    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm" x-data="{ edit: false }">
                        <div class="flex items-center justify-between">
                            <div x-show="!edit"><p class="font-bold">v{{ $file->version }} @if($file->is_latest)<span class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700">Latest</span>@endif</p><p class="text-xs text-gray-400">{{ $file->size }} · {{ $file->file_path }}</p></div>
                            <p x-show="edit" class="font-bold">Edit file details</p>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="edit=!edit" class="text-xs font-semibold text-[var(--color-primary)] hover:underline" x-text="edit ? 'Close' : 'Edit'"></button>
                                <x-admin.del-button :action="$del('files', $file->id)" />
                            </div>
                        </div>
                        <form x-show="edit" x-cloak method="POST" action="{{ $edit('files', $file->id) }}" class="mt-3 space-y-3">
                            @csrf @method('PUT')
                            <div class="grid gap-3 sm:grid-cols-2"><x-admin.field label="Version" name="version" :value="$file->version" required /><x-admin.field label="Size" name="size" :value="$file->size" /></div>
                            <x-admin.field label="Changelog" name="changelog" type="textarea" :value="$file->changelog" />
                            <x-admin.field name="is_latest" type="checkbox" label="Mark as latest" :value="$file->is_latest" />
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save details</button>
                        </form>
                    </div>
                @endforeach
                <x-admin.add-form :action="$add('files')" enctype="multipart/form-data" title="Upload source file">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-admin.field label="Version" name="version" required placeholder="1.0.0" />
                        <div><label class="mb-1.5 block text-sm font-medium">Zip file</label><input type="file" name="file" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]"></div>
                    </div>
                    <x-admin.field label="Changelog" name="changelog" type="textarea" />
                    <x-admin.field name="is_latest" type="checkbox" label="Mark as latest" :value="true" />
                </x-admin.add-form>
            </div>
            @break
    @endswitch
@endsection
