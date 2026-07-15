<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A tax / charge type applied to invoice lines (Vat/Tax 5.5%, Paypal Charge 6%, …). */
class InvoiceTax extends Model
{
    protected $fillable = ['name', 'rate', 'is_default', 'sort_order'];

    protected $casts = ['rate' => 'float', 'is_default' => 'bool'];

    public static function options()
    {
        return static::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'rate', 'is_default']);
    }

    /** "Vat/Tax: 5.5%" label used in the picker. */
    public function getLabelAttribute(): string
    {
        return $this->name.': '.rtrim(rtrim(number_format($this->rate, 3, '.', ''), '0'), '.').'%';
    }
}
