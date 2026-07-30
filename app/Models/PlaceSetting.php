<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Single-row settings for Google Places lookups. */
class PlaceSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'api_key' => 'encrypted',
        'auto_crawl_websites' => 'boolean',
    ];

    public static function current(): self
    {
        return static::first() ?? static::create(['max_pages' => 3, 'auto_crawl_websites' => true]);
    }

    public function isConfigured(): bool
    {
        return filled($this->api_key);
    }
}
