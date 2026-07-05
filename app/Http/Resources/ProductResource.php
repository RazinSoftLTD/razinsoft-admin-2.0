<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Lean payload the product card needs (no direct price — show the first plan's price).
        $base = [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'category' => $this->category,
            'badge' => $this->badge,
            'version' => $this->version,
            'currency' => $this->currency,
            'rating' => (float) $this->rating,
            'reviews_count' => $this->reviews_count,
            'sales_count' => $this->sales_count,
            'thumbnail' => self::media($this->thumbnail),
            'thumbnail_alt' => $this->thumbnail_alt ?: $this->name,
            'from_plan' => $this->fromPlan(),
            'from_price' => $this->fromPlan()['price'] ?? null,
        ];

        if (! $this->wantsDetail()) {
            return $base; // ---- product list: lean ----
        }

        // ---- product detail (show): everything ----
        return array_merge($base, [
            'is_featured' => (bool) $this->is_featured,
            'hero_image' => self::media($this->hero_image),
            'hero_alt' => $this->hero_alt ?: $this->name,
            'overview' => $this->overview,
            'demo' => [
                'live' => $this->live_demo_url,
                'admin' => $this->admin_demo_url,
                'customer' => $this->customer_demo_url,
                'android' => $this->android_apk_url,
                'ios' => $this->ios_ipa_url,
            ],
            // Dynamic, unlimited demo & download links (admin-managed) → "Try It Live" cards.
            'demos' => $this->whenLoaded('demos', fn () => $this->demos->map(fn ($d) => [
                'type' => $d->type,
                'icon' => $d->icon ? self::media($d->icon) : null, // uploaded icon (null → website uses the type preset)
                'title' => $d->title,
                'subtitle' => $d->subtitle,
                'badge' => $d->badge,
                'url' => $d->url,
            ])->values()),
            'plans' => PlanResource::collection($this->whenLoaded('plans')),
            'features' => $this->whenLoaded('features', fn () => $this->features->map(fn ($f) => [
                'title' => $f->title, 'subtitle' => $f->subtitle, 'description' => $f->description, 'icon' => $f->icon, 'color' => $f->color,
            ])),
            'tech_stack' => $this->whenLoaded('tech', fn () => $this->tech->map(fn ($t) => ['name' => $t->name, 'color' => $t->color])),
            'suitable_for' => $this->whenLoaded('suitableFor', fn () => $this->suitableFor->pluck('label')),
            'docs' => $this->whenLoaded('docs', fn () => $this->docs->map(fn ($d) => ['title' => $d->title, 'type' => $d->type, 'url' => $d->url])),
            'faqs' => $this->whenLoaded('faqs', fn () => $this->faqs->map(fn ($q) => ['question' => $q->question, 'answer' => $q->answer])),
            'questions' => $this->whenLoaded('questions', fn () => $this->questions->map(fn ($q) => [
                'id' => $q->id,
                'user' => $q->name ?: ($q->user?->name ?? 'Customer'),
                'question' => $q->question,
                'asked_at' => $q->created_at?->diffForHumans(),
                'answers' => $q->answers->map(fn ($a) => [
                    'user' => $a->name ?: ($a->user?->name ?? 'User'),
                    'body' => $a->body,
                    'is_admin' => (bool) $a->is_admin,
                    'when' => $a->created_at?->diffForHumans(),
                ])->values(),
            ])),
            'gallery' => $this->whenLoaded('galleryGroups', fn () => $this->galleryGroups->map(fn ($g) => [
                'name' => $g->name,
                'images' => $g->images->map(fn ($i) => ['image' => self::media($i->image), 'caption' => $i->caption, 'alt' => $i->alt ?: $g->name]),
            ])),
            'reviews' => $this->whenLoaded('reviews', fn () => $this->reviews->map(fn ($r) => [
                'id' => $r->id,
                'author' => $r->author_name ?: 'Customer',
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'when' => $r->created_at?->diffForHumans(),
            ])),
            'latest_file' => $this->whenLoaded('latestFile', fn () => $this->latestFile ? [
                'version' => $this->latestFile->version, 'size' => $this->latestFile->size,
            ] : null),
            'seo' => $this->whenLoaded('seo', fn () => $this->seoPayload()),
        ]);
    }

    /** First plan (by sort order) → the card's "from" price. Works for both list and detail. */
    private function fromPlan(): ?array
    {
        $plan = $this->relationLoaded('firstPlan')
            ? $this->firstPlan
            : ($this->relationLoaded('plans') ? $this->plans->first() : null);

        return $plan ? ['id' => $plan->id, 'name' => $plan->name, 'price' => (float) $plan->price] : null;
    }

    private function wantsDetail(): bool
    {
        return $this->relationLoaded('plans');
    }

    private function seoPayload(): array
    {
        $s = $this->seo;
        $title = $s?->seo_title ?: "{$this->name} — {$this->tagline}";
        $desc = $s?->meta_description ?: Str::limit(strip_tags((string) $this->overview), 155);

        return [
            'title' => $title,
            'description' => $desc,
            'focus_keyword' => $s?->focus_keyword,
            'canonical' => $s?->canonical_url ?: "/products/{$this->slug}",
            'robots' => $s?->robots ?: 'index,follow',
            'og_title' => $s?->og_title ?: $title,
            'og_description' => $s?->og_description ?: $desc,
            'og_image' => self::media($s?->og_image ?: $this->hero_image),
            'twitter_card' => $s?->twitter_card ?: 'summary_large_image',
            'twitter_image' => self::media($s?->twitter_image ?: $this->hero_image),
            'brand' => $s?->brand ?: 'RazinSoft',
            'sku' => $s?->sku,
            'operating_system' => $s?->operating_system,
            'application_category' => $s?->application_category,
            'software_version' => $s?->software_version ?: $this->version,
            'price_valid_until' => $s?->price_valid_until?->toDateString(),
        ];
    }

    public static function media(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        // Files keep their original name on disk; encode each segment so names with
        // spaces / special characters still load over HTTP (slashes preserved).
        $encoded = collect(explode('/', ltrim($path, '/')))
            ->map(fn ($seg) => rawurlencode($seg))
            ->implode('/');

        return rtrim((string) config('app.url'), '/').'/storage/'.$encoded;
    }
}
