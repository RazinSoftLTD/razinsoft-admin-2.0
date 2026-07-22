<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Lean list: only the card data + the first plan's price (no heavy relations).
        $q = Product::query()->published()->with('firstPlan')->withMin('plans', 'price');

        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('tagline', 'like', "%{$search}%"));
        }

        if (($cat = $request->query('category')) && $cat !== 'All Categories') {
            $q->where('category', $cat);
        }

        // Homepage picks: only products the admin flagged for_home. Fall back to the normal
        // list when none are flagged yet, so the homepage is never empty.
        $homeMode = $request->boolean('for_home') && Product::published()->where('for_home', true)->exists();
        if ($homeMode) {
            $q->where('for_home', true);
        }

        match ($request->query('sort')) {
            'sellers' => $q->orderByDesc('sales_count'),
            'rated' => $q->orderByDesc('rating'),
            'price' => $q->orderBy('plans_min_price'),
            'free' => $q->whereHas('plans', fn ($p) => $p->where('price', 0)),
            // Homepage uses its own drag order (home_order); the catalogue uses sort_order.
            default => $homeMode
                ? $q->orderBy('home_order')->orderByDesc('is_featured')
                : $q->orderBy('sort_order')->orderByDesc('is_featured'),
        };

        $perPage = min((int) $request->query('per_page', 12), 48);

        return ProductResource::collection($q->paginate($perPage)->withQueryString());
    }

    public function show(string $slug)
    {
        // Cache the (heavy) detail payload; invalidated by Product::forgetCache() on any admin edit.
        $payload = Cache::remember(Product::cacheKey($slug), now()->addMinutes(2), function () use ($slug) {
            $product = Product::published()
                ->with([
                    'plans', 'features', 'tech', 'suitableFor', 'docs', 'faqs', 'demos',
                    'galleryGroups.images', 'latestFile', 'seo',
                    'reviews' => fn ($r) => $r->where('is_approved', true)->latest(),
                    'questions' => fn ($q) => $q->public()->latest(),
                    'questions.answers' => fn ($a) => $a->where('is_public', true),
                    'questions.answers.user:id,name',
                ])
                ->where('slug', $slug)
                ->firstOrFail();

            // Fully arrayify (json round-trip) so no Collection/Resource objects land in the cache.
            return json_decode(json_encode(['data' => new ProductResource($product)]), true);
        });

        return response()->json($payload);
    }

    /** Visitor submits a question about a product (requires auth). */
    public function storeQuestion(Request $request, string $slug)
    {
        $product = Product::published()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $product->questions()->create([
            'user_id' => $request->user()->id,
            'name' => $request->user()->name,
            'question' => $data['question'],
            'is_public' => true,
        ]);

        return response()->json(['message' => 'Your question has been posted.'], 201);
    }

    /** Any logged-in user (asker, other customers, admin) can answer a question. */
    public function storeAnswer(Request $request, string $slug, \App\Models\ProductQuestion $question)
    {
        $product = Product::published()->where('slug', $slug)->firstOrFail();
        abort_unless($question->product_id === $product->id && $question->is_public, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $question->answers()->create([
            'user_id' => $request->user()->id,
            'name' => $request->user()->name,
            'body' => $data['body'],
            'is_admin' => $request->user()->isAdmin(),
            'is_public' => true,
        ]);

        return response()->json(['message' => 'Your answer has been posted.'], 201);
    }

    /** A verified purchaser leaves (or updates) their review — affects the product rating directly. */
    public function storeReview(Request $request, string $slug)
    {
        $product = Product::published()->where('slug', $slug)->firstOrFail();
        $user = $request->user();

        abort_unless($user->hasPurchased($product->id), 403, 'Only customers who purchased this product can review it.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        // One review per user per product — submitting again updates it.
        $product->reviews()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'author_name' => $user->name,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'is_approved' => true,
            ],
        );

        $product->syncReviewAggregates();

        return response()->json(['message' => 'Thanks for your review!'], 201);
    }

    public function categories()
    {
        return response()->json(
            Product::published()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')
        );
    }
}
