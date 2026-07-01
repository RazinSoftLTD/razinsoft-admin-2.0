<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /** Set true (via ->detail()) to include the full body. */
    public bool $withDetail = false;

    public function detail(bool $v = true): static
    {
        $this->withDetail = $v;

        return $this;
    }

    public function toArray(Request $request): array
    {
        // Field names mirror the storefront's Article shape so the UI needs no remapping.
        $base = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'image' => ProductResource::media($this->image),
            'image_alt' => $this->image_alt ?: $this->title,
            'category' => $this->category?->name,
            'author' => $this->author?->name,
            'author_photo' => ProductResource::media($this->author?->photo),
            'author_role' => $this->author?->role,
            'date' => $this->published_at?->format('F j, Y'),
            'readTime' => $this->read_time,
            'tags' => $this->tags ?? [],
            'featured' => (bool) $this->is_featured,
        ];

        if (! $this->withDetail) {
            return $base;
        }

        return array_merge($base, [
            'content' => $this->content ?? '',
            'quote' => $this->quote,
            'takeaways' => $this->takeaways,
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'tagline' => $p->tagline,
                'badge' => $p->badge,
                'image' => ProductResource::media($p->thumbnail),
                'price' => $p->relationLoaded('firstPlan') && $p->firstPlan ? (float) $p->firstPlan->price : null,
            ])->values()),
            'seo' => [
                'title' => $this->meta_title ?: $this->title,
                'description' => $this->meta_description ?: $this->excerpt,
                'keywords' => $this->meta_keywords,
            ],
        ]);
    }
}
