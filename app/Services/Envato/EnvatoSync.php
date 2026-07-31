<?php

namespace App\Services\Envato;

use App\Models\EnvatoAuthor;
use App\Models\EnvatoProduct;
use App\Models\EnvatoSetting;
use Illuminate\Support\Carbon;

/** Pulls watched authors + products from the official API and records a daily snapshot. */
class EnvatoSync
{
    private EnvatoClient $api;

    public function __construct(EnvatoClient $api)
    {
        // A sync reads past the cache by definition: its job is to record what the
        // numbers are *now*. Serving it a cached reading meant a sale could sit
        // invisible for up to the cache TTL even after a manual "Sync now".
        $this->api = $api->withoutCache();
    }

    /** Refresh every watched author and their portfolio. Returns [authors, products]. */
    public function all(): array
    {
        $authors = EnvatoAuthor::all();
        $products = 0;
        foreach ($authors as $author) {
            $products += $this->author($author);
        }

        // Products tracked on their own (no watched author) still need refreshing.
        foreach (EnvatoProduct::whereNull('envato_author_id')->get() as $product) {
            $this->product($product->item_id, null, $product->envato_niche_id);
            $products++;
        }

        EnvatoSetting::current()->update(['last_synced_at' => now(), 'last_error' => null]);

        return [$authors->count(), $products];
    }

    /** Refresh one author's profile and every CodeCanyon item they sell. */
    public function author(EnvatoAuthor $author): int
    {
        if ($profile = $this->api->author($author->username)) {
            $author->update([
                'country' => $profile['country'] ?? null,
                'image' => $profile['image'] ?? null,
                'total_sales' => (int) ($profile['sales'] ?? 0),
                'followers' => (int) ($profile['followers'] ?? 0),
                'badges' => $this->api->authorBadges($author->username),
                'items_count' => $this->api->authorItemCounts($author->username)['codecanyon'] ?? null,
                'synced_at' => now(),
            ]);
        }

        $count = 0;
        foreach ($this->api->authorItems($author->username) as $item) {
            $this->store($item, $author->id, null);
            $count++;
        }

        return $count;
    }

    /** Track (or refresh) a single item by its Envato id. */
    public function product(int $itemId, ?int $authorId = null, ?int $nicheId = null): ?EnvatoProduct
    {
        $item = $this->api->item($itemId);

        return $item ? $this->store($item, $authorId, $nicheId) : null;
    }

    /** Persist the API payload and append today's snapshot. */
    private function store(array $item, ?int $authorId, ?int $nicheId): EnvatoProduct
    {
        $rating = $item['rating'] ?? null;
        // more_like_this responses nest it as {rating, count}; the catalog returns flat fields.
        $ratingValue = is_array($rating) ? ($rating['rating'] ?? null) : $rating;
        $ratingCount = is_array($rating) ? ($rating['count'] ?? 0) : ($item['rating_count'] ?? 0);

        $product = EnvatoProduct::updateOrCreate(
            ['item_id' => (int) $item['id']],
            array_filter([
                'envato_author_id' => $authorId,
                'envato_niche_id' => $nicheId,
                'name' => $item['name'] ?? 'Untitled',
                'author_username' => $item['author_username'] ?? null,
                'url' => $item['url'] ?? null,
                'site' => $item['site'] ?? null,
                'classification' => $item['classification'] ?? null,
                'thumbnail_url' => $item['thumbnail_url'] ?? null,
                'number_of_sales' => (int) ($item['number_of_sales'] ?? 0),
                'rating' => $ratingValue !== null ? round((float) $ratingValue, 2) : null,
                'rating_count' => (int) $ratingCount,
                'price_cents' => (int) ($item['price_cents'] ?? 0),
                'trending' => (bool) ($item['trending'] ?? false),
                'tags' => $item['tags'] ?? [],
                'published_at' => isset($item['published_at']) ? Carbon::parse($item['published_at']) : null,
                'item_updated_at' => isset($item['updated_at']) ? Carbon::parse($item['updated_at']) : null,
                'synced_at' => now(),
            ], fn ($v, $k) => $v !== null || in_array($k, ['envato_author_id', 'envato_niche_id'], true), ARRAY_FILTER_USE_BOTH)
        );

        // One row per day — the API gives no history, so this is what builds the trend.
        // Later runs the same day overwrite the row, which keeps the last reading
        // before midnight as the day's close.
        $snapshot = $product->snapshots()->firstOrNew(['captured_on' => today()]);

        // Written once, on the first reading of the day, and never again: it is the
        // line today's sales are measured from before the day has a close.
        $snapshot->opening_sales ??= $product->number_of_sales;

        $snapshot->fill([
            'number_of_sales' => $product->number_of_sales,
            'rating' => $product->rating,
            'rating_count' => $product->rating_count,
            'price_cents' => $product->price_cents,
        ])->save();

        return $product;
    }
}
