@extends('admin.layouts.app')
@section('title', ($promotion->exists ? 'Edit ' : 'Add ') . \App\Models\Promotion::TYPES[$promotion->type])

@section('content')
    @php
        $me = auth()->user();
        $canPublish = $me->hasPermission('promotion.publish');
        $specKey = $promotion->type === \App\Models\Promotion::TYPE_POPUP ? 'popup_banner' : 'banner';
        $isPopup = $promotion->type === \App\Models\Promotion::TYPE_POPUP;
    @endphp
    <div class="mx-auto max-w-3xl">
        <div class="mb-5 flex items-center gap-2">
            <a href="{{ route('admin.promotions.index') }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Back">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-lg font-bold text-[var(--color-heading)]">{{ $promotion->exists ? 'Edit' : 'Add' }} {{ \App\Models\Promotion::TYPES[$promotion->type] }}</h1>
        </div>

        <form method="POST" action="{{ $promotion->exists ? route('admin.promotions.update', $promotion) : route('admin.promotions.store') }}" enctype="multipart/form-data"
              class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            @csrf
            @if ($promotion->exists)@method('PUT')@endif

            @if ($promotion->exists)
                {{-- Type is locked once created — the image spec/aspect depends on it. --}}
                <input type="hidden" name="type" value="{{ $promotion->type }}">
                <p class="text-xs text-[var(--color-muted)]">Type: <span class="font-semibold text-[var(--color-heading)]">{{ \App\Models\Promotion::TYPES[$promotion->type] }}</span></p>
            @else
                <x-admin.field label="Type" name="type" type="select" :value="$promotion->type" :options="\App\Models\Promotion::TYPES" required />
            @endif

            <div>
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Image</label>
                @if ($promotion->image)
                    <img src="{{ \App\Http\Resources\ProductResource::media($promotion->image) }}" class="mb-2 {{ $isPopup ? 'h-32 w-32' : 'h-16 w-full' }} rounded-lg border border-gray-100 object-cover">
                @endif
                <input type="file" name="image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                <p class="mt-1 text-xs text-[var(--color-muted)]">
                    {{ \App\Support\ImageSpecs::hint($specKey) }} PNG, JPG, GIF (animated supported) or WEBP.
                    {{ $isPopup ? 'Shown as a modal once per page load; clicking it opens the All Products page.' : "Shown full-width above the site's menu; clicking it opens the All Products page." }}
                </p>
                @error('image')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field label="Starts at" name="starts_at" type="date" :value="optional($promotion->starts_at)->format('Y-m-d')" required />
                <x-admin.field label="Ends at" name="ends_at" type="date" :value="optional($promotion->ends_at)->format('Y-m-d')" required :hint="$isPopup ? null : 'Also drives the countdown timer on the banner.'" />
            </div>

            {{-- Publish control — only users with the publish permission see the live option. --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Visibility</label>
                @if ($canPublish)
                    <div class="flex flex-wrap gap-3">
                        <label class="flex flex-1 cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 has-[:checked]:border-[var(--color-primary)] has-[:checked]:bg-[var(--color-primary-soft)]">
                            <input type="radio" name="status" value="draft" class="mt-0.5 accent-[var(--color-primary)]" @checked(! $promotion->isPublished())>
                            <span><span class="block text-sm font-semibold text-[var(--color-heading)]">Draft</span><span class="block text-xs text-gray-500">Internal only — not on the website.</span></span>
                        </label>
                        <label class="flex flex-1 cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50">
                            <input type="radio" name="status" value="published" class="mt-0.5 accent-emerald-600" @checked($promotion->isPublished())>
                            <span><span class="block text-sm font-semibold text-[var(--color-heading)]">Published</span><span class="block text-xs text-gray-500">Live on the website during its schedule window.</span></span>
                        </label>
                    </div>
                @else
                    <input type="hidden" name="status" value="draft">
                    <p class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-700">Saved as a <span class="font-semibold">draft</span>. A user with publish rights will review and make it live.</p>
                @endif
            </div>

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.promotions.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $promotion->exists ? 'Save changes' : 'Create' }}</button>
            </div>
        </form>
    </div>
@endsection
