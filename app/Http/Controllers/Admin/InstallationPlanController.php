<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstallationFeature;
use App\Models\InstallationPlan;
use App\Models\Product;
use Illuminate\Http\Request;

/** Products › Installation Plans — manage per-product installation packages + comparison matrix. */
class InstallationPlanController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()->orderBy('name')
            ->withCount(['installationPlans', 'installationFeatures'])
            ->get(['id', 'name', 'slug', 'thumbnail', 'installation_status']);

        // Manage the picked product (or the first one).
        $productId = (int) $request->query('product', $products->first()->id ?? 0);
        $product = $products->firstWhere('id', $productId) ? Product::find($productId) : null;

        $product?->load(['installationFeatures', 'installationPlans.features']);

        return view('admin.installation-plans.index', [
            'products' => $products,
            'product' => $product,
        ]);
    }

    // ---- features ----

    public function featureStore(Request $request, Product $product)
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:150']]);
        $product->installationFeatures()->create([
            'label' => $data['label'],
            'position' => (int) $product->installationFeatures()->max('position') + 1,
        ]);

        return back()->with('status', 'Feature added.');
    }

    public function featureUpdate(Request $request, Product $product, InstallationFeature $feature)
    {
        abort_if($feature->product_id !== $product->id, 404);
        $feature->update($request->validate(['label' => ['required', 'string', 'max:150']]));

        return back()->with('status', 'Feature updated.');
    }

    public function featureDestroy(Product $product, InstallationFeature $feature)
    {
        abort_if($feature->product_id !== $product->id, 404);
        \DB::table('installation_plan_feature')->where('feature_id', $feature->id)->delete();
        $feature->delete();

        return back()->with('status', 'Feature removed.');
    }

    // ---- plans ----

    public function planStore(Request $request, Product $product)
    {
        $data = $this->planData($request);
        $data['position'] = (int) $product->installationPlans()->max('position') + 1;
        $plan = $product->installationPlans()->create($data);
        $this->onePopular($product, $plan, $request->boolean('is_popular'));

        return back()->with('status', 'Plan added.');
    }

    public function planUpdate(Request $request, Product $product, InstallationPlan $plan)
    {
        abort_if($plan->product_id !== $product->id, 404);
        $plan->update($this->planData($request));
        $this->onePopular($product, $plan, $request->boolean('is_popular'));

        return back()->with('status', 'Plan updated.');
    }

    public function planDestroy(Product $product, InstallationPlan $plan)
    {
        abort_if($plan->product_id !== $product->id, 404);
        $plan->features()->detach();
        $plan->delete();

        return back()->with('status', 'Plan removed.');
    }

    /** Publish state for the product's whole plan block. */
    public function status(Request $request, Product $product)
    {
        $data = $request->validate([
            'installation_status' => ['required', \Illuminate\Validation\Rule::in(array_keys(InstallationPlan::STATUSES))],
        ]);
        $product->update($data);

        return back()->with('status', 'Installation plans are now '.strtolower(InstallationPlan::STATUSES[$data['installation_status']]).'.');
    }

    /** Add a product straight from this page, then build its plans here. */
    public function productStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'currency' => ['nullable', 'string', 'max:8'],
        ]);

        $slug = \Illuminate\Support\Str::slug($data['name']);
        $base = $slug;
        $i = 2;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        $product = Product::create([
            'name' => $data['name'],
            'slug' => $slug,
            'currency' => $data['currency'] ?? 'USD',
            'status' => 'draft',                    // the product itself stays a draft
            'installation_status' => InstallationPlan::STATUS_DRAFT,
        ]);

        return redirect()->route('admin.installation-plans', ['product' => $product->id])
            ->with('status', 'Product added — now add its plans.');
    }

    /** Preview a product's plans exactly as the website renders them (drafts included). */
    public function preview(Request $request, Product $product)
    {
        $product->load(['installationFeatures', 'installationPlans.features']);
        // "live" shows only what the public API returns; otherwise everything, flagged.
        $live = $request->boolean('live');
        // Live view mirrors the API: the whole block is hidden unless the product is published.
        $plans = ($live && $product->installation_status !== InstallationPlan::STATUS_PUBLISHED)
            ? collect()
            : $product->installationPlans;

        return view('admin.installation-plans.preview', [
            'product' => $product,
            'plans' => $plans->values(),
            'live' => $live,
        ]);
    }

    /** Toggle a single feature on/off for a plan (the comparison-matrix checkbox). */
    public function toggle(Request $request, Product $product, InstallationPlan $plan)
    {
        abort_if($plan->product_id !== $product->id, 404);
        $data = $request->validate([
            'feature_id' => ['required', 'exists:installation_features,id'],
            'included' => ['required', 'boolean'],
        ]);
        $request->boolean('included')
            ? $plan->features()->syncWithoutDetaching([$data['feature_id']])
            : $plan->features()->detach($data['feature_id']);

        return $request->wantsJson() ? response()->json(['ok' => true]) : back();
    }

    /** Copy the full feature list + plans + matrix from another product into this one. */
    public function copyFrom(Request $request, Product $product)
    {
        $data = $request->validate(['source_id' => ['required', 'different:'.$product->id, 'exists:products,id']]);
        $source = Product::with(['installationFeatures', 'installationPlans.features'])->findOrFail($data['source_id']);

        \DB::transaction(function () use ($product, $source) {
            // Clear whatever this product currently has.
            $product->installationPlans->each(fn ($p) => $p->features()->detach());
            $product->installationPlans()->delete();
            $product->installationFeatures()->delete();

            // Copy features, keeping a source-id → new-id map for the matrix.
            $map = [];
            foreach ($source->installationFeatures as $f) {
                $new = $product->installationFeatures()->create(['label' => $f->label, 'position' => $f->position]);
                $map[$f->id] = $new->id;
            }
            // Copy plans + re-link their features through the map.
            foreach ($source->installationPlans as $plan) {
                $newPlan = $product->installationPlans()->create([
                    'name' => $plan->name, 'tagline' => $plan->tagline, 'price' => $plan->price,
                    'sale_price' => $plan->sale_price, 'note' => $plan->note, 'is_popular' => $plan->is_popular, 'position' => $plan->position,
                ]);
                $newPlan->features()->sync(collect($plan->features)->pluck('id')->map(fn ($id) => $map[$id] ?? null)->filter()->all());
            }
        });

        return redirect()->route('admin.installation-plans', ['product' => $product->id])
            ->with('status', "Copied from {$source->name}. Now update prices & details as needed.");
    }

    // ---- internals ----

    private function planData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:150'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'note' => ['nullable', 'string', 'max:150'],
            'is_popular' => ['nullable', 'boolean'],
            'status' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(InstallationPlan::STATUSES))],
        ]);
        $data['is_popular'] = $request->boolean('is_popular');
        $data['status'] = $data['status'] ?? InstallationPlan::STATUS_DRAFT;

        return $data;
    }

    /** Only one plan per product may be flagged "Most Popular". */
    private function onePopular(Product $product, InstallationPlan $plan, bool $popular): void
    {
        if ($popular) {
            $product->installationPlans()->where('id', '!=', $plan->id)->update(['is_popular' => false]);
        }
    }
}
