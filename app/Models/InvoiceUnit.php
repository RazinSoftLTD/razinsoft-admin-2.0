<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A quantity unit for invoice lines (Items, Hours, Pcs, …). */
class InvoiceUnit extends Model
{
    protected $fillable = ['name', 'is_default', 'sort_order'];

    protected $casts = ['is_default' => 'bool'];

    public static function options()
    {
        return static::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_default']);
    }

    public static function defaultName(): ?string
    {
        return static::where('is_default', true)->value('name') ?? static::orderBy('sort_order')->value('name');
    }
}
