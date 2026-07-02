<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ArticleController extends Controller
{
    /** Published articles for the Insights list (newest first). Supports ?search= for site search. */
    public function index(Request $request)
    {
        $q = Article::published()
            ->with('category', 'author')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$search}%")
                ->orWhere('excerpt', 'like', "%{$search}%")
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$search}%")));
        }

        if ($perPage = (int) $request->query('per_page')) {
            $q->take(min($perPage, 24));
        }

        return ArticleResource::collection($q->get());
    }

    /** Single article (full body) + related posts in the same category. */
    public function show(string $slug)
    {
        $payload = Cache::remember(Article::cacheKey($slug), now()->addMinutes(5), function () use ($slug) {
            $article = Article::published()
                ->with([
                    'category',
                    'author',
                    // Only surface products that are still published — an unpublished/removed
                    // product must not linger on a blog post that features it.
                    'products' => fn ($q) => $q->published()->with('firstPlan'),
                ])
                ->where('slug', $slug)
                ->firstOrFail();

            $related = Article::published()
                ->with('category', 'author')
                ->where('category_id', $article->category_id)
                ->whereKeyNot($article->id)
                ->orderByDesc('published_at')
                ->take(3)
                ->get();

            // Fully arrayify (json round-trip) so no Collection/Resource objects land in the cache.
            return json_decode(json_encode([
                'data' => (new ArticleResource($article))->detail(),
                'related' => ArticleResource::collection($related),
            ]), true);
        });

        return response()->json($payload);
    }
}
