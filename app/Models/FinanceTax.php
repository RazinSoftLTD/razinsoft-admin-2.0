<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A VAT / tax / withholding amount to report and settle for a given month. */
class FinanceTax extends Model
{
    use SoftDeletes;

    public const KINDS = ['vat' => 'VAT', 'tax' => 'Tax', 'withholding' => 'Withholding Tax'];
    public const STATUSES = ['pending' => 'Pending', 'paid' => 'Paid'];

    protected $fillable = [
        'kind', 'title', 'amount', 'currency', 'period', 'due_date',
        'status', 'reference', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period' => 'date',
        'due_date' => 'date',
    ];

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date && $this->due_date->isPast();
    }

    public function symbol(): string
    {
        return Currency::symbolMap()[$this->currency] ?? '';
    }
}
