<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainOrder extends Model
{
    protected $guarded = [];

    protected $casts = ['paid_at' => 'datetime', 'registered_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /** What the website may show. The RC ids and the raw error stay inside. */
    public function publicPayload(): array
    {
        return [
            'order_number' => $this->order_number,
            'domain' => $this->domain,
            'years' => (int) $this->years,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'status' => $this->status,
            'paid' => $this->isPaid(),
            'registered_at' => $this->registered_at?->toIso8601String(),
        ];
    }
}
