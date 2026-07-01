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
                <x-admin.field label="Sort order" name="sort_order" type="number" :value="$product->sort_order" />
                <x-admin.field label="Currency" name="currency" :value="$product->currency ?? 'USD'" />
            </div>
            <x-admin.field name="is_featured" type="checkbox" label="Featured product" :value="$product->is_featured" />
        </div>
    </div>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">Stats & media</h3>
        <div class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-admin.field label="Rating" name="rating" type="number" :value="$product->rating" hint="0–5" />
                <x-admin.field label="Reviews count" name="reviews_count" type="number" :value="$product->reviews_count" />
                <x-admin.field label="Sales count" name="sales_count" type="number" :value="$product->sales_count" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Thumbnail</label>
                    @if ($product->thumbnail)<img src="{{ \App\Http\Resources\ProductResource::media($product->thumbnail) }}" class="mb-2 h-20 rounded-lg border border-gray-100 object-cover">@endif
                    <input type="file" name="thumbnail" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                </div>
                <x-admin.field label="Thumbnail alt" name="thumbnail_alt" :value="$product->thumbnail_alt" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Hero image</label>
                    @if ($product->hero_image)<img src="{{ \App\Http\Resources\ProductResource::media($product->hero_image) }}" class="mb-2 h-20 rounded-lg border border-gray-100 object-cover">@endif
                    <input type="file" name="hero_image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                </div>
                <x-admin.field label="Hero alt" name="hero_alt" :value="$product->hero_alt" />
            </div>
            <x-admin.field label="Overview" name="overview" type="textarea" :rows="5" :value="$product->overview" />
        </div>
    </div>
</div>
