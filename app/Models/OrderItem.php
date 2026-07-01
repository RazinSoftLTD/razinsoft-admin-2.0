<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
class OrderItem extends Model
{
    protected $guarded = [];
    protected $casts = ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function license(): HasOne { return $this->hasOne(License::class); }
}
