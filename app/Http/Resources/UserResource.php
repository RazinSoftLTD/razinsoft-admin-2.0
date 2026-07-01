<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'initials' => collect(explode(' ', trim((string) $this->name)))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''),
            'created_at' => $this->created_at,
        ];
    }
}
