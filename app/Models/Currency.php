<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = ['code', 'symbol', 'name', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'bool'];

    /** Request-cached code => symbol map for ALL currencies (used to render existing invoices). */
    protected static ?array $map = null;

    public static function symbolMap(): array
    {
        return static::$map ??= static::orderBy('sort_order')->orderBy('code')->pluck('symbol', 'code')->all();
    }

    /** Active currencies for the invoice form dropdown. */
    public static function options()
    {
        return static::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(['code', 'symbol', 'name']);
    }

    /** Forget the cached map (call after writes within the same request). */
    public static function flushMap(): void
    {
        static::$map = null;
    }
}
