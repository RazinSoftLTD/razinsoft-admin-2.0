<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A bill we owe — hosting, subscriptions, suppliers, employee reimbursements. */
class FinancePayable extends Model
{
    use SoftDeletes;

    public const STATUSES = ['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid'];

    protected $fillable = [
        'vendor', 'category_id', 'amount', 'amount_paid', 'currency',
        'bill_date', 'due_date', 'status', 'reference', 'notes', 'attachment', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'bill_date' => 'date',
        'due_date' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function due(): float
    {
        return max(0, (float) $this->amount - (float) $this->amount_paid);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date && $this->due_date->isPast();
    }

    /** Keep the status in step with what has been paid. */
    public function syncStatus(): void
    {
        $paid = (float) $this->amount_paid;
        $this->status = $paid <= 0 ? 'unpaid' : ($paid >= (float) $this->amount ? 'paid' : 'partial');
    }

    public function symbol(): string
    {
        return Currency::symbolMap()[$this->currency] ?? '';
    }
}
