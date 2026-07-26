<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One month's payslip for an employee. */
class EmployeePayroll extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'paid' => 'Paid'];

    protected $guarded = [];

    protected $casts = [
        'period' => 'date:Y-m-d',
        'paid_on' => 'date:Y-m-d',
        'basic' => 'decimal:2',
        'allowance' => 'decimal:2',
        'bonus' => 'decimal:2',
        'deduction' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Net pay always follows the parts it is made of. */
    public function recalculate(): void
    {
        $this->net_pay = (float) $this->basic + (float) $this->allowance + (float) $this->bonus - (float) $this->deduction;
    }

    public function symbol(): string
    {
        return Currency::symbolMap()[$this->currency] ?? '';
    }
}
