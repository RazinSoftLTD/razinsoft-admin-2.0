<div class="space-y-6">
    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">Basic info</h3>
        <div class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field label="Name" name="name" :value="$product->name" required />
                <x-admin.field label="Slug" name="slug" :value="$product->slug" hint="Auto from name if blank." />
            </div>
            <x-admin.field label="Tagline" name="tagline" :value="$product->tagline" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field label="Category" name="category" :value="$product->category" />
                <x-admin.field label="Badge" name="badge" type="select" :value="$product->badge" :options="['' => 'None', 'best_seller' => 'Best Seller', 'new' => 'New', 'free' => 'Free']" />
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-admin.field label="Version" name="version" :value="$product->version" placeholder="1.0.0" />
                <x-admin.field label="Serial (sort order)" name="sort_order" type="number" min="0" :value="$product->sort_order" hint="Lower number shows first on the website." />
                <x-admin.field label="Currency" name="currency" :value="$product->currency ?? 'USD'" />
            </div>
            <div class="flex flex-wrap gap-x-8 gap-y-2">
                <x-admin.field name="is_featured" type="checkbox" label="Featured product" :value="$product->is_featured" />
                <x-admin.field name="for_home" type="checkbox" label="Show on homepage (max 6)" :value="$product->for_home" />
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">Stats & media</h3>
        <div class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-admin.field label="Rating" name="rating" type="number" step="0.1" min="0" max="5" :value="$product->rating" hint="0–5 (e.g. 4.5)" />
                <x-admin.field label="Reviews count" name="reviews_count" type="number" :value="$product->reviews_count" />
                <x-admin.field label="Sales count" name="sales_count" type="number" :value="$product->sales_count" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Thumbnail</label>
                    @if ($product->thumbnail)<img src="{{ \App\Http\Resources\ProductResource::media($product->thumbnail) }}" class="mb-2 h-20 rounded-lg border border-gray-100 object-cover">@endif
                    <input type="file" name="thumbnail" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                    <p class="mt-1 text-xs text-[var(--color-muted)]">{{ \App\Support\ImageSpecs::hint('product') }}</p>
                </div>
                <x-admin.field label="Thumbnail alt" name="thumbnail_alt" :value="$product->thumbnail_alt" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Hero image</label>
                    @if ($product->hero_image)<img src="{{ \App\Http\Resources\ProductResource::media($product->hero_image) }}" class="mb-2 h-20 rounded-lg border border-gray-100 object-cover">@endif
                    <input type="file" name="hero_image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                    <p class="mt-1 text-xs text-[var(--color-muted)]">{{ \App\Support\ImageSpecs::hint('product') }}</p>
                </div>
                <x-admin.field label="Hero alt" name="hero_alt" :value="$product->hero_alt" />
            </div>
            <x-admin.field label="Overview" name="overview" type="textarea" :rows="5" :value="$product->overview" />

            {{-- "Try It Live" section background on the public product page --}}
            <div x-data="{ color: @js($product->try_it_live_bg ?: '') }">
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">“Try It Live” background</label>
                <div class="flex flex-wrap items-center gap-3">
                    <input type="color" :value="color || '#f8fafc'" @input="color = $event.target.value"
                           class="h-10 w-14 cursor-pointer rounded-lg border border-gray-200 p-1">
                    <input type="text" name="try_it_live_bg" x-model="color" maxlength="20"
                           placeholder="#f8fafc (leave blank for default)"
                           class="h-10 w-44 rounded-lg border border-gray-200 px-3 text-sm">
                    <button type="button" @click="color = ''" x-show="color" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">Reset</button>
                    <span class="ml-auto inline-flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 text-xs text-[var(--color-muted)]">
                        Preview
                        <span class="h-5 w-16 rounded" :style="`background: ${color || '#f8fafc'}`"></span>
                    </span>
                </div>
                <p class="mt-1 text-xs text-[var(--color-muted)]">Colour behind the product page’s “Try It Live” cards. Blank = website default.</p>
            </div>
        </div>
    </div>
</div>
