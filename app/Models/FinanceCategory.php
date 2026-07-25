<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Expense / income categories used across the Finance module. */
class FinanceCategory extends Model
{
    public const KINDS = ['expense' => 'Expense', 'income' => 'Income'];

    /** Seeded on install; the admin can add more. */
    public const DEFAULT_EXPENSES = [
        'Salary', 'Office Rent', 'Internet', 'Electricity', 'Marketing', 'Facebook Ads',
        'Google Ads', 'Hosting', 'Domain', 'Software Subscription', 'Travel', 'Food',
        'Maintenance', 'Other',
    ];

    public const DEFAULT_INCOMES = ['Client Payment', 'Interest', 'Refund', 'Other Income'];

    protected $fillable = ['kind', 'name', 'sort_order'];

    public function scopeOfKind($q, string $kind)
    {
        return $q->where('kind', $kind)->orderBy('sort_order')->orderBy('name');
    }

    public function transactions()
    {
        return $this->hasMany(FinanceTransaction::class, 'category_id');
    }

    /** ['id' => 'Name'] for a dropdown. */
    public static function options(string $kind): array
    {
        return static::ofKind($kind)->pluck('name', 'id')->all();
    }
}
