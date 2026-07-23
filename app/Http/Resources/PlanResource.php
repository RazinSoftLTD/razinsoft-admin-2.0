<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = (float) $this->price;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'blurb' => $this->blurb,
            'price' => $price,
            'sale_price' => $this->resource->hasActiveOffer() ? $this->resource->discountedPrice($price) : null,
            'is_popular' => (bool) $this->is_popular,
            'perks' => $this->perks ?? [],
        ];
    }
}
