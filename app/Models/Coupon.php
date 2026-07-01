<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Coupon extends Model
{
    protected $guarded = [];
    protected $casts = ['value' => 'decimal:2', 'is_active' => 'boolean', 'expires_at' => 'datetime'];
    public function isValid(): bool
    {
        if (! $this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) return false;
        return true;
    }
    public function discountFor(float $subtotal): float
    {
        return $this->type === 'percent'
            ? round($subtotal * ((float) $this->value) / 100, 2)
            : min((float) $this->value, $subtotal);
    }
}
