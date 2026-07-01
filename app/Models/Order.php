<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Order extends Model
{
    protected $guarded = [];
    protected $casts = [
        'billing' => 'array', 'paid_at' => 'datetime',
        'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2',
    ];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function invoice(): HasOne { return $this->hasOne(Invoice::class); }
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
    public function isPaid(): bool { return in_array($this->status, ['paid', 'processing', 'completed']); }
}
