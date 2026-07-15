<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Single-row branding/config for invoices (logo, brand name). */
class InvoiceSetting extends Model
{
    protected $fillable = ['logo', 'brand_name'];

    /** The one settings row, created on demand. */
    public static function current(): self
    {
        return static::first() ?? static::create(['brand_name' => 'RazinSoft']);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return str_starts_with($this->logo, 'http') ? $this->logo : asset('storage/'.$this->logo);
    }
}
