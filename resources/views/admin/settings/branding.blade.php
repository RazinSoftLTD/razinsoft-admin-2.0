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


        @php
            // Each field's placeholder is the value the software would use if left blank, so the
            // form itself tells you what "empty" means instead of making you save to find out.
            $card = 'mt-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm';
            $label = 'mb-1.5 block text-xs font-semibold text-[var(--color-heading)]';
            $input = 'h-11 w-full rounded-lg border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-0';
            $area = 'w-full rounded-lg border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-0';
            $socials = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'x' => 'X / Twitter'];
            $savedSocial = $brand->social ?? [];
        @endphp

        {{-- ───────── Basic information ───────── --}}
        <div class="{{ $card }}">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Basic information</h2>
            <p class="mt-0.5 text-xs text-[var(--color-muted)]">
                Who runs this. Used on the website, on invoices and in the mail this system sends.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="b-company" class="{{ $label }}">Company name</label>
                    <input id="b-company" name="company_name" value="{{ old('company_name', $brand->company_name) }}"
                           placeholder="{{ config('brand.vendor') }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="b-email" class="{{ $label }}">Support email</label>
                    <input id="b-email" name="support_email" type="email" value="{{ old('support_email', $brand->support_email) }}"
                           placeholder="{{ config('brand.support_email') }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="b-phone" class="{{ $label }}">Phone</label>
                    <input id="b-phone" name="phone" value="{{ old('phone', $brand->phone) }}"
                           placeholder="Leave empty to hide it" class="{{ $input }}">
                </div>
                <div>
                    <label for="b-site" class="{{ $label }}">Website</label>
                    <input id="b-site" name="website_url" type="url" value="{{ old('website_url', $brand->website_url) }}"
                           placeholder="{{ config('brand.website') }}" class="{{ $input }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="b-address" class="{{ $label }}">Address</label>
                    <input id="b-address" name="address" value="{{ old('address', $brand->address) }}"
                           placeholder="{{ config('brand.address') }}" class="{{ $input }}">
                </div>
            </div>
        </div>

        {{-- ───────── Website header ───────── --}}
        <div class="{{ $card }}">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Website header</h2>
            <p class="mt-0.5 text-xs text-[var(--color-muted)]">
                The button on the right of the public site's header. The logo above is used here too.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="b-cta" class="{{ $label }}">Button label</label>
                    <input id="b-cta" name="header_cta_label" maxlength="40" value="{{ old('header_cta_label', $brand->header_cta_label) }}"
                           placeholder="Get Started" class="{{ $input }}">
                </div>
                <div>
                    <label for="b-cta-url" class="{{ $label }}">Button link</label>
                    <input id="b-cta-url" name="header_cta_url" value="{{ old('header_cta_url', $brand->header_cta_url) }}"
                           placeholder="/#pricing" class="{{ $input }}">
                </div>
            </div>
        </div>

        {{-- ───────── Website footer ───────── --}}
        <div class="{{ $card }}">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Website footer</h2>
            <p class="mt-0.5 text-xs text-[var(--color-muted)]">
                The paragraph under the logo, the line at the very bottom, and which networks to link.
            </p>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="b-about" class="{{ $label }}">About paragraph</label>
                    <textarea id="b-about" name="footer_about" rows="3" maxlength="400"
                              placeholder="Falls back to your tagline" class="{{ $area }}">{{ old('footer_about', $brand->footer_about) }}</textarea>
                </div>
                <div>
                    <label for="b-note" class="{{ $label }}">Bottom line</label>
                    <input id="b-note" name="footer_note" maxlength="200" value="{{ old('footer_note', $brand->footer_note) }}"
                           placeholder="© {{ date('Y') }} {{ $brand->productName() }}. All rights reserved." class="{{ $input }}">
                </div>

                <div>
                    <p class="{{ $label }}">Social links</p>
                    <p class="mb-2 text-xs text-[var(--color-muted)]">Leave one empty and its icon disappears.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($socials as $key => $name)
                            <div>
                                <label for="b-s-{{ $key }}" class="mb-1 block text-[11px] text-[var(--color-muted)]">{{ $name }}</label>
                                <input id="b-s-{{ $key }}" name="social[{{ $key }}]" type="url"
                                       value="{{ old('social.'.$key, $savedSocial[$key] ?? (config('brand.social.'.$key) ?: '')) }}"
                                       placeholder="https://…" class="{{ $input }}">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ───────── Login ───────── --}}
        <div class="{{ $card }}">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Login screens</h2>
            <p class="mt-0.5 text-xs text-[var(--color-muted)]">
                Shown above the sign-in form, on both the panel and the customer site.
            </p>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="b-lh" class="{{ $label }}">Heading</label>
                    <input id="b-lh" name="login_heading" maxlength="120" value="{{ old('login_heading', $brand->login_heading) }}"
                           placeholder="Welcome back" class="{{ $input }}">
                </div>
                <div>
                    <label for="b-ls" class="{{ $label }}">Sub-heading</label>
                    <input id="b-ls" name="login_subheading" maxlength="250" value="{{ old('login_subheading', $brand->login_subheading) }}"
                           placeholder="Sign in to your {{ $brand->productName() }} account." class="{{ $input }}">
                </div>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-2">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save branding</button>
            <a href="{{ route('admin.dashboard') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Done</a>
        </div>
    </form>
@endsection
