<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;

/** Public API — products that have installation plans, with their features + plan matrix. */
class InstallationPlanController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->whereHas('installationPlans')
            ->with(['installationFeatures', 'installationPlans.features:id'])
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'slug', 'thumbnail', 'currency']);

        return response()->json([
            'products' => $products->map(function (Product $p) {
                $features = $p->installationFeatures->map(fn ($f) => ['id' => $f->id, 'label' => $f->label])->values();

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'thumbnail' => ProductResource::media($p->thumbnail),
                    'currency' => $p->currency ?: 'USD',
                    'features' => $features,
                    'plans' => $p->installationPlans->map(fn ($plan) => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'tagline' => $plan->tagline,
                        'price' => (float) $plan->price,
                        'sale_price' => $plan->sale_price !== null ? (float) $plan->sale_price : null,
                        'note' => $plan->note,
                        'is_popular' => (bool) $plan->is_popular,
                        // IDs of the features this plan includes → drives the checkmarks.
                        'feature_ids' => $plan->features->pluck('id')->values(),
                    ])->values(),
                ];
            })->values(),
        ]);
    }
}
