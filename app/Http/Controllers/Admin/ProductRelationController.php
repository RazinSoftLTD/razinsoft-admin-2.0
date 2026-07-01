<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductRelationController extends Controller
{
    /** Manageable sections: page key => [title, relation(s) to eager-load]. */
    public const SECTIONS = [
        'plans' => ['title' => 'Plans', 'load' => 'plans'],
        'features' => ['title' => 'Features', 'load' => 'features'],
        'gallery' => ['title' => 'Gallery', 'load' => 'galleryGroups.images'],
        'demos' => ['title' => 'Demos & Downloads', 'load' => 'demos'],
        'tech' => ['title' => 'Tech Stack', 'load' => 'tech'],
        'suitable' => ['title' => 'Suitable For', 'load' => 'suitableFor'],
        'docs' => ['title' => 'Docs', 'load' => 'docs'],
        'faqs' => ['title' => 'FAQs', 'load' => 'faqs'],
        'files' => ['title' => 'Source Files', 'load' => 'files'],
    ];

    /** Dedicated management page for one section. */
    public function edit(Product $product, string $relation)
    {
        abort_unless(isset(self::SECTIONS[$relation]), 404);

        $product->load(self::SECTIONS[$relation]['load']);

        return view('admin.products.relation', [
            'product' => $product,
            'relation' => $relation,
            'sectionTitle' => self::SECTIONS[$relation]['title'],
        ]);
    }

    /** relation key => [Eloquent relation method, validation rules]. */
    private function config(): array
    {
        return [
            'plans' => ['rel' => 'plans', 'rules' => ['name' => 'required|string|max:255', 'blurb' => 'nullable|string', 'price' => 'required|numeric|min:0', 'perks' => 'nullable|string', 'is_popular' => 'boolean', 'sort_order' => 'nullable|integer']],
            'features' => ['rel' => 'features', 'rules' => ['title' => 'required|string|max:255', 'subtitle' => 'nullable|string|max:255', 'description' => 'nullable|string', 'icon' => 'nullable|string', 'color' => 'nullable|string', 'sort_order' => 'nullable|integer']],
            'tech' => ['rel' => 'tech', 'rules' => ['name' => 'required|string|max:255', 'color' => 'nullable|string|max:50', 'sort_order' => 'nullable|integer']],
            'suitable' => ['rel' => 'suitableFor', 'rules' => ['label' => 'required|string|max:255', 'sort_order' => 'nullable|integer']],
            'docs' => ['rel' => 'docs', 'rules' => ['title' => 'required|string|max:255', 'type' => 'nullable|string|max:50', 'url' => 'nullable|url', 'sort_order' => 'nullable|integer']],
            'demos' => ['rel' => 'demos', 'rules' => ['type' => 'nullable|string|max:50', 'title' => 'required|string|max:255', 'subtitle' => 'nullable|string|max:255', 'badge' => 'nullable|string|max:50', 'url' => 'required|url', 'sort_order' => 'nullable|integer']],
            'faqs' => ['rel' => 'faqs', 'rules' => ['question' => 'required|string|max:255', 'answer' => 'nullable|string', 'sort_order' => 'nullable|integer']],
            'gallery-groups' => ['rel' => 'galleryGroups', 'rules' => ['name' => 'required|string|max:255', 'sort_order' => 'nullable|integer']],
        ];
    }

    public function store(Request $request, Product $product, string $relation)
    {
        if ($relation === 'gallery-images') {
            return $this->storeGalleryImage($request, $product);
        }
        if ($relation === 'files') {
            return $this->storeFile($request, $product);
        }

        $cfg = $this->cfg($relation);
        $data = $this->normalize($relation, $request->validate($cfg['rules']), $request);
        $product->{$cfg['rel']}()->create($data);

        return back()->with('status', ucfirst($relation).' added.');
    }

    public function update(Request $request, Product $product, string $relation, int $id)
    {
        if ($relation === 'gallery-images') {
            $img = GalleryImage::whereHas('group', fn ($q) => $q->where('product_id', $product->id))->findOrFail($id);
            $data = $request->validate(['caption' => 'nullable|string|max:255', 'alt' => 'nullable|string|max:255', 'sort_order' => 'nullable|integer']);
            $img->update($data);

            return back()->with('status', 'Image updated.');
        }
        if ($relation === 'files') {
            $file = $product->files()->findOrFail($id);
            $data = $request->validate(['version' => 'required|string|max:50', 'size' => 'nullable|string|max:50', 'changelog' => 'nullable|string', 'is_latest' => 'boolean']);
            $data['is_latest'] = $request->boolean('is_latest');
            $file->update($data);
            if ($data['is_latest']) {
                // Demote the others (not this one) so exactly one file stays latest.
                $product->files()->whereKeyNot($file->id)->update(['is_latest' => false]);
            }

            return back()->with('status', 'File updated.');
        }

        $cfg = $this->cfg($relation);
        $model = $product->{$cfg['rel']}()->findOrFail($id);
        $data = $this->normalize($relation, $request->validate($cfg['rules']), $request);
        $model->update($data);

        return back()->with('status', ucfirst($relation).' updated.');
    }

    public function destroy(Product $product, string $relation, int $id)
    {
        if ($relation === 'gallery-images') {
            GalleryImage::whereHas('group', fn ($q) => $q->where('product_id', $product->id))->findOrFail($id)->delete();

            return back()->with('status', 'Image removed.');
        }

        $rel = $relation === 'files' ? 'files' : $this->cfg($relation)['rel'];
        $product->{$rel}()->findOrFail($id)->delete();

        return back()->with('status', 'Removed.');
    }

    // ---- helpers ----

    private function cfg(string $relation): array
    {
        abort_unless(isset($this->config()[$relation]), 404);

        return $this->config()[$relation];
    }

    private function normalize(string $relation, array $data, Request $request): array
    {
        if ($relation === 'plans') {
            $data['is_popular'] = $request->boolean('is_popular');
            $data['perks'] = collect(preg_split('/\r\n|\r|\n/', (string) $request->input('perks')))->map(fn ($l) => trim($l))->filter()->values()->all();
        }

        // Let the column default apply instead of inserting NULL when left blank.
        if (array_key_exists('sort_order', $data) && $data['sort_order'] === null) {
            unset($data['sort_order']);
        }

        return $data;
    }

    private function storeGalleryImage(Request $request, Product $product)
    {
        $data = $request->validate([
            'gallery_group_id' => ['required', 'integer', 'exists:gallery_groups,id'],
            'image' => ['required', 'image', 'max:4096'],
            'caption' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);
        $group = $product->galleryGroups()->findOrFail($data['gallery_group_id']);
        $image = $request->file('image');
        $group->images()->create([
            'image' => $image->storeAs('products/gallery', $image->getClientOriginalName(), 'public'),
            'caption' => $data['caption'] ?? null,
            'alt' => $data['alt'] ?? null,
        ]);

        return back()->with('status', 'Image uploaded.');
    }

    private function storeFile(Request $request, Product $product)
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:50'],
            'file' => ['required', 'file', 'max:512000'], // up to 500MB zip
            'changelog' => ['nullable', 'string'],
            'is_latest' => ['boolean'],
        ]);
        $isLatest = $request->boolean('is_latest');
        if ($isLatest) {
            $product->files()->update(['is_latest' => false]);
        }
        $path = $request->file('file')->store('sources', 'local');
        $product->files()->create([
            'version' => $data['version'],
            'file_path' => $path,
            'size' => $this->humanSize($request->file('file')->getSize()),
            'changelog' => $data['changelog'] ?? null,
            'is_latest' => $isLatest,
        ]);

        return back()->with('status', 'Source file uploaded.');
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;

        return round($bytes / (1024 ** $i), 1).' '.$units[$i];
    }
}
