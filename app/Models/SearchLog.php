<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    protected $guarded = [];

    public const UPDATED_AT = null; // only track created_at

    protected $casts = ['results_count' => 'integer'];

    /** ISO alpha-2 country code → readable name (e.g. BD → Bangladesh). */
    public static function countryName(?string $code): string
    {
        if (! $code) {
            return 'Unknown';
        }
        if (extension_loaded('intl')) {
            return \Locale::getDisplayRegion('-'.strtoupper($code), 'en') ?: strtoupper($code);
        }

        return strtoupper($code);
    }

    public function getCountryNameAttribute(): string
    {
        return static::countryName($this->country_code);
    }
}
