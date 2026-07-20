<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Singleton config for the official Envato API (Settings → CodeCanyon Config). */
class EnvatoSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'personal_token' => 'encrypted',
        'is_connected' => 'boolean',
        'auto_sync' => 'boolean',
        'verified_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([]);
    }

    public function isConfigured(): bool
    {
        return filled($this->personal_token);
    }
}
