<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'blurb' => $this->blurb,
            'price' => (float) $this->price,
            'is_popular' => (bool) $this->is_popular,
            'perks' => $this->perks ?? [],
        ];
    }
}
