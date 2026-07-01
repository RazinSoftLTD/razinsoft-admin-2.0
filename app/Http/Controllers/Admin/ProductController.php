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
        $products = Product::with('firstPlan')->withMin('plans', 'price')->latest('updated_at')->paginate(15);

        return view('admin.products.index', compact('products'));
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

        return redirect()->route('admin.products.show', $product)->with('status', 'Product updated.');
    }

    /** Publish / unpublish toggle (from the product view). */
    public function togglePublish(Product $product)
    {
        $product->update(['status' => $product->status === 'published' ? 'draft' : 'published']);

        return back()->with('status', $product->status === 'published' ? 'Product is now live.' : 'Product unpublished.');
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
            'sort_order' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'size:3'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'reviews_count' => ['nullable', 'integer', 'min:0'],
            'sales_count' => ['nullable', 'integer', 'min:0'],
            'thumbnail_alt' => ['nullable', 'string', 'max:255'],
            'hero_alt' => ['nullable', 'string', 'max:255'],
            'overview' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'image', 'max:4096'],
            'hero_image' => ['nullable', 'image', 'max:4096'],
        ]);

        $data['slug'] = Str::slug(($data['slug'] ?? '') ?: $data['name']);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['badge'] = ($data['badge'] ?? '') ?: null;

        return $data;
    }

    /** Store uploaded thumbnail/hero on the public disk; keep existing path otherwise. */
    private function handleImages(Request $request, array $data, ?Product $product = null): array
    {
        foreach (['thumbnail', 'hero_image'] as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->store('products', 'public');
            } else {
                unset($data[$field]); // don't overwrite existing
            }
        }

        return $data;
    }
}
