@php
    // Every field here falls back to something sensible on the website when left blank — the
    // hints say what that fallback is, so it is clear when writing something is worth the effort.
    $seo = $product->seo;
    $fallbackTitle = trim($product->name.' — '.$product->tagline);
    $titleLen = mb_strlen($seo?->seo_title ?: $fallbackTitle) + 12;   // " · RazinSoft"
@endphp

<div class="mt-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
    <h3 class="mb-1 text-sm font-bold uppercase tracking-wide text-gray-400">Search &amp; social (SEO)</h3>
    <p class="mb-4 text-sm text-[var(--color-muted)]">
        What Google and Facebook show. Leave a field blank and the website works it out from the product itself.
    </p>

    <div class="space-y-4">
        <div>
            <x-admin.field label="SEO title" name="seo_title" :value="$seo?->seo_title"
                           :placeholder="$fallbackTitle"
                           hint="Google cuts titles off around 60 characters, and it appends ' · RazinSoft' for you — so keep this under about 48." />
            <p class="mt-1 text-xs {{ $titleLen > 60 ? 'font-semibold text-amber-600' : 'text-[var(--color-muted)]' }}">
                Currently {{ $titleLen }} characters with the site name.
                {{ $titleLen > 60 ? 'Google will cut this off.' : 'Fits.' }}
            </p>
        </div>

        <x-admin.field label="Meta description" name="meta_description" type="textarea" :rows="3"
                       :value="$seo?->meta_description"
                       hint="The grey text under the title in Google. Aim for 120–160 characters. Blank falls back to the overview." />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field label="Focus keyword" name="focus_keyword" :value="$seo?->focus_keyword"
                           placeholder="e.g. multi vendor ecommerce app"
                           hint="What you want this page to be found for." />
            <x-admin.field label="SKU" name="sku" :value="$seo?->sku"
                           :placeholder="strtoupper(str_replace('-', '', $product->slug))"
                           hint="Product code in the structured data. Use the CodeCanyon item code if you have one." />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field label="Robots" name="robots" type="select" :value="$seo?->robots ?: 'index,follow'"
                           :options="['index,follow' => 'index,follow — let Google list it', 'noindex,follow' => 'noindex,follow — keep it out of Google']"
                           hint="Leave as index,follow unless you deliberately want this page hidden." />
            <x-admin.field label="Canonical URL" name="canonical_url" :value="$seo?->canonical_url"
                           :placeholder="'/products/'.$product->slug"
                           hint="Almost always leave blank. A wrong value here tells Google this page is a copy of another one." />
        </div>

        <details class="rounded-lg border border-gray-100 bg-gray-50 p-4">
            <summary class="cursor-pointer text-sm font-semibold text-[var(--color-heading)]">Social sharing &amp; structured data</summary>
            <div class="mt-4 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field label="OG title" name="og_title" :value="$seo?->og_title" hint="Blank uses the SEO title." />
                    <x-admin.field label="OG description" name="og_description" :value="$seo?->og_description" hint="Blank uses the meta description." />
                </div>
                <x-admin.field label="OG image path" name="og_image" :value="$seo?->og_image" hint="Blank uses the hero image, which is usually what you want." />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field label="Brand" name="brand" :value="$seo?->brand" placeholder="RazinSoft" />
                    <x-admin.field label="Software version" name="software_version" :value="$seo?->software_version" :placeholder="$product->version" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field label="Operating system" name="operating_system" :value="$seo?->operating_system" placeholder="Web, Android, iOS" />
                    <x-admin.field label="Application category" name="application_category" :value="$seo?->application_category" placeholder="BusinessApplication" />
                </div>
            </div>
        </details>
    </div>
</div>
