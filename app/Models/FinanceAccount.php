<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A wallet (Payoneer, Wise, Stripe, Cash…) or a bank account — same shape, different `type`. */
class FinanceAccount extends Model
{
    use SoftDeletes;

    public const TYPE_WALLET = 'wallet';
    public const TYPE_BANK = 'bank';
    public const TYPES = [self::TYPE_WALLET => 'Wallet', self::TYPE_BANK => 'Bank Account'];

    public const STATUSES = ['active' => 'Active', 'inactive' => 'Inactive'];

    /** Suggestions only — the field stays free text. */
    public const WALLET_PROVIDERS = ['Payoneer', 'Wise', 'Stripe', 'Mercury', 'PayPal', 'Cash', 'Other'];
    public const BANK_PROVIDERS = ['DBBL', 'City Bank', 'BRAC Bank', 'EBL', 'HSBC', 'Islami Bank', 'Other'];

    protected $fillable = [
        'type', 'name', 'provider', 'currency', 'account_number',
        'opening_balance', 'current_balance', 'status', 'notes', 'sort_order',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'account_id');
    }

    public function scopeWallets($q)
    {
        return $q->where('type', self::TYPE_WALLET);
    }

    public function scopeBanks($q)
    {
        return $q->where('type', self::TYPE_BANK);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Recalculate the balance from the opening balance plus every live transaction.
     * Cheaper to store than to compute on every page, and self-healing after any edit.
     */
    public function recalculateBalance(): void
    {
        $in = (float) $this->transactions()->where('direction', 'in')->sum('amount');
        $out = (float) $this->transactions()->where('direction', 'out')->sum('amount');

        $this->forceFill(['current_balance' => (float) $this->opening_balance + $in - $out])->saveQuietly();
    }

    public function symbol(): string
    {
        return Currency::symbolMap()[$this->currency] ?? '';
    }
}
