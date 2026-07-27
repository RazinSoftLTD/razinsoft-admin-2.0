@extends('admin.layouts.app')
@section('title', 'Branding')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Branding</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">
            The name, marks and colour your team sees. Leave a field empty to keep what the software shipped with.
        </p>
    </div>

    @if (session('status'))<div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data" class="max-w-3xl">
        @csrf

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Name</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Product name</label>
                    <input type="text" name="product" value="{{ old('product', $brand->product) }}"
                           placeholder="{{ config('brand.product') }}" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                    <p class="mt-1 text-xs text-gray-400">Shown in the sidebar, the sign-in screen and every page title.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Tagline</label>
                    <input type="text" name="tagline" value="{{ old('tagline', $brand->tagline) }}"
                           placeholder="{{ config('brand.tagline') }}" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                </div>
            </div>
        </div>

        <div class="mt-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Marks</h2>
            <p class="mt-0.5 text-xs text-[var(--color-muted)]">PNG, JPG, WebP or SVG. The icon is used in the sidebar and as the browser tab icon, so it should be square.</p>

            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                @foreach ([['logo', 'Wordmark', 'h-9', 'Sign-in screen'], ['icon', 'Icon', 'h-10 w-10', 'Sidebar & tab']] as [$field, $label, $size, $where])
                    <div class="rounded-lg border border-gray-100 p-4">
                        <p class="text-xs font-semibold text-[var(--color-muted)]">{{ $label }} <span class="font-normal text-gray-400">· {{ $where }}</span></p>

                        <div class="mt-3 flex h-16 items-center justify-center rounded-lg bg-gray-50">
                            @php $url = $field === 'logo' ? $brand->logoUrl() : $brand->iconUrl(); @endphp
                            @if ($url)
                                <img src="{{ $url }}" alt="{{ $label }}" class="{{ $size }} w-auto object-contain">
                            @else
                                <span class="text-xs text-gray-400">Nothing set</span>
                            @endif
                        </div>

                        <input type="file" name="{{ $field }}" accept="image/*" class="mt-3 w-full text-xs text-[var(--color-muted)]">

                        @if ($brand->$field)
                            <button type="submit" formaction="{{ route('admin.branding.reset', $field) }}" formenctype="application/x-www-form-urlencoded"
                                    class="mt-2 text-xs font-semibold text-red-600 hover:underline">Reset to default</button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm"
             x-data="{ colour: @js($brand->primary ?: $brand->primaryColour()) }">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Colour</h2>
            <p class="mt-0.5 text-xs text-[var(--color-muted)]">
                One colour. The hover and tint shades are worked out from it, which keeps them in step.
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <input type="color" name="primary" x-model="colour" class="h-11 w-16 rounded-lg border-gray-200">
                <input type="text" x-model="colour" maxlength="7"
                       class="h-11 w-32 rounded-lg border-gray-200 font-mono text-sm" aria-label="Colour hex">

                <div class="flex items-center gap-2">
                    <span class="rounded-lg px-4 py-2 text-sm font-semibold text-white" :style="`background:${colour}`">Button</span>
                    <span class="h-9 w-9 rounded-lg border border-gray-200" :style="`background:${colour}`"></span>
                </div>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-2">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save branding</button>
            <a href="{{ route('admin.dashboard') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Done</a>
        </div>
    </form>
@endsection
