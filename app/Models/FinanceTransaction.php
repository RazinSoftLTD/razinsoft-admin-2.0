<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One movement of money. Transfers and conversions are stored as two paired rows
 * (an `out` leg and an `in` leg) sharing a transfer_group, so every account's balance
 * is simply opening + ins − outs.
 */
class FinanceTransaction extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'income' => 'Income',
        'expense' => 'Expense',
        'transfer' => 'Transfer',
        'conversion' => 'Currency Conversion',
        'deposit' => 'Deposit',
        'withdrawal' => 'Withdrawal',
        'refund' => 'Refund',
        'adjustment' => 'Manual Adjustment',
    ];

    /** Which way each type moves money by default. Adjustments/refunds pick their own. */
    public const DIRECTION = [
        'income' => 'in',
        'expense' => 'out',
        'deposit' => 'in',
        'withdrawal' => 'out',
    ];

    /** Types that count as money earned / spent in the reports. */
    public const INCOME_TYPES = ['income', 'deposit'];
    public const EXPENSE_TYPES = ['expense', 'withdrawal'];

    protected $fillable = [
        'type', 'direction', 'account_id', 'counter_account_id', 'category_id',
        'amount', 'currency', 'converted_amount', 'exchange_rate', 'fee', 'bank_charge',
        'occurred_on', 'reference', 'notes', 'receipt',
        'source', 'client_invoice_id', 'invoice_payment_id', 'transfer_group', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'converted_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'fee' => 'decimal:2',
        'bank_charge' => 'decimal:2',
        'occurred_on' => 'date',
    ];

    protected static function booted(): void
    {
        // Any change to a transaction re-points the balances it touches.
        $sync = function (self $t) {
            foreach (array_filter([$t->account_id, $t->getOriginal('account_id')]) as $id) {
                FinanceAccount::find($id)?->recalculateBalance();
            }
        };
        static::saved($sync);
        static::deleted($sync);
        static::restored($sync);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function counterAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'counter_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeIncome($q)
    {
        return $q->whereIn('type', self::INCOME_TYPES);
    }

    public function scopeExpense($q)
    {
        return $q->whereIn('type', self::EXPENSE_TYPES);
    }

    /** Money in minus money out, for a query already scoped to a period. */
    public static function netFor($query): float
    {
        return (float) (clone $query)->where('direction', 'in')->sum('amount')
            - (float) (clone $query)->where('direction', 'out')->sum('amount');
    }

    public function symbol(): string
    {
        return Currency::symbolMap()[$this->currency] ?? '';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function isAutomatic(): bool
    {
        return $this->source === 'invoice';
    }
}
