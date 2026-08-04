<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        // Full list (no pagination) so the whole catalogue can be drag-reordered.
        $products = Product::with('firstPlan')->withMin('plans', 'price')
            ->orderBy('sort_order')->orderBy('id')->get();

        // Homepage tab: only products flagged for_home, in their own drag order.
        $homeProducts = Product::with('firstPlan')->withMin('plans', 'price')
            ->where('for_home', true)->orderBy('home_order')->orderBy('id')->get();

        return view('admin.products.index', compact('products', 'homeProducts'));
    }

    /** Persist the All-Products drag order into sort_order (array of ids, top → bottom). */
    public function reorder(Request $request)
    {
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);
        $pos = 1;
        foreach ($data['order'] as $id) {
            Product::where('id', $id)->update(['sort_order' => $pos++]);
        }
        return response()->json(['ok' => true]);
    }

    /** Persist the Homepage-featured drag order into home_order. */
    public function reorderHome(Request $request)
    {
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);
        $pos = 1;
        foreach ($data['order'] as $id) {
            Product::where('id', $id)->where('for_home', true)->update(['home_order' => $pos++]);
        }
        return response()->json(['ok' => true]);
    }

    public function create()
    {
        return view('admin.products.form', ['product' => new Product(['status' => 'draft', 'currency' => 'USD'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data = $this->handleImages($request, $data);
        $data['status'] = 'draft'; // always created unpublished; publish from the product view

        $product = Product::create($data);

        return redirect()->route('admin.products.show', $product)
            ->with('status', 'Product created as a draft — add plans, features, gallery, etc., then publish.');
    }

    /** Product overview: General + Stats & media, plus a tab/card per section to manage. */
    public function show(Product $product)
    {
        $product->loadCount(['plans', 'features', 'galleryGroups', 'demos', 'tech', 'suitableFor', 'docs', 'faqs', 'files']);

        return view('admin.products.show', compact('product'));
    }

    /** Edit only General + Stats & media (sections are managed on their own pages). */
    public function edit(Product $product)
    {
        return view('admin.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);
        $data = $this->handleImages($request, $data, $product);

        $product->update($data);
        $this->saveSeo($request, $product);

        return redirect()->route('admin.products.show', $product)->with('status', 'Product updated.');
    }

    /** Publish / unpublish toggle (from the product view). */
    public function togglePublish(Product $product)
    {
        $product->update(['status' => $product->status === 'published' ? 'draft' : 'published']);

        return back()->with('status', $product->status === 'published' ? 'Product is now live.' : 'Product unpublished.');
    }

    /** Duplicate a product with all its content relations (a fresh draft to tweak). */
    public function clone(Product $product)
    {
        $clone = \Illuminate\Support\Facades\DB::transaction(function () use ($product) {
            $copy = $product->replicate([
                'slug', 'status', 'is_featured', 'rating', 'reviews_count', 'sales_count',
            ]);
            $copy->name = $product->name.' (Copy)';
            $copy->slug = $this->uniqueSlug($product->slug.'-copy');
            $copy->status = 'draft';
            $copy->is_featured = false;
            $copy->rating = 0;
            $copy->reviews_count = 0;
            $copy->sales_count = 0;
            $copy->save();

            // Simple hasMany relations copied verbatim.
            foreach (['plans', 'features', 'tech', 'suitableFor', 'docs', 'faqs', 'demos'] as $rel) {
                foreach ($product->{$rel} as $row) {
                    $copy->{$rel}()->create($row->replicate()->toArray());
                }
            }

            // Gallery groups + their nested images.
            foreach ($product->galleryGroups as $group) {
                $newGroup = $copy->galleryGroups()->create($group->replicate()->toArray());
                foreach ($group->images as $img) {
                    $newGroup->images()->create($img->replicate()->toArray());
                }
            }

            // SEO (morphOne). Only the settings that describe how we publish, never the text that
            // names the product: a copy that keeps the original's title, canonical and SKU will
            // publish under the wrong name and tell Google it is the same page as the original.
            // Left blank, the site falls back to "{name} — {tagline}", which is at least correct.
            if ($product->seo) {
                $copy->seo()->create($product->seo->replicate([
                    'seoable_id', 'seoable_type',
                    'seo_title', 'meta_description', 'focus_keyword', 'meta_keywords', 'canonical_url',
                    'og_title', 'og_description', 'og_image',
                    'twitter_title', 'twitter_description', 'twitter_image',
                    'sku',
                ])->toArray());
            }

            return $copy;
        });

        return redirect()->route('admin.products.edit', $clone)->with('status', "Product cloned as \"{$clone->name}\" (draft). Write its SEO title and description before publishing — they are left blank on purpose. Reviews, questions and source files were not copied.");
    }

    /** Ensure the cloned slug is unique (append -2, -3, … if needed). */
    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Product deleted.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product)],
            'tagline' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'badge' => ['nullable', 'in:best_seller,new,free'],
            'version' => ['nullable', 'string', 'max:50'],
            'is_featured' => ['boolean'],
            'for_home' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'size:3'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'reviews_count' => ['nullable', 'integer', 'min:0'],
            'sales_count' => ['nullable', 'integer', 'min:0'],
            'thumbnail_alt' => ['nullable', 'string', 'max:255'],
            'hero_alt' => ['nullable', 'string', 'max:255'],
            'overview' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'image', 'max:4096', \App\Support\ImageSpecs::rule('product')],
            'hero_image' => ['nullable', 'image', 'max:4096', \App\Support\ImageSpecs::rule('product')],
        ], [
            'thumbnail.dimensions' => \App\Support\ImageSpecs::message('product', 'thumbnail'),
            'hero_image.dimensions' => \App\Support\ImageSpecs::message('product', 'hero image'),
        ]);

        $data['slug'] = Str::slug(($data['slug'] ?? '') ?: $data['name']);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['badge'] = ($data['badge'] ?? '') ?: null;

        return $data;
    }

    /**
     * Save the product's search/social fields.
     *
     * Blank is stored as null rather than an empty string, because the website tells the two
     * apart: null falls back to "{name} — {tagline}" and the hero image, an empty string would
     * publish an empty title. Only runs when the form actually carried the section, so an older
     * form or a partial post cannot wipe what is there.
     */
    private function saveSeo(Request $request, Product $product): void
    {
        if (! $request->has('seo_title')) {
            return;
        }

        $data = $request->validate([
            'seo_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'focus_keyword' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'string', 'max:255'],
            // Rule::in, not the string form — these values contain the comma that separates rules.
            'robots' => ['nullable', Rule::in(['index,follow', 'noindex,follow'])],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:320'],
            'og_image' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'operating_system' => ['nullable', 'string', 'max:255'],
            'application_category' => ['nullable', 'string', 'max:255'],
            'software_version' => ['nullable', 'string', 'max:255'],
        ]);

        $product->seo()->updateOrCreate([], array_map(
            fn ($v) => is_string($v) && trim($v) === '' ? null : $v,
            $data,
        ));
    }

    /** Store uploaded thumbnail/hero on the public disk; keep existing path otherwise. */
    private function handleImages(Request $request, array $data, ?Product $product = null): array
    {
        foreach (['thumbnail', 'hero_image'] as $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $data[$field] = $file->storeAs('products', $file->getClientOriginalName(), 'public');
            } else {
                unset($data[$field]); // don't overwrite existing
            }
        }

        return $data;
    }
}
