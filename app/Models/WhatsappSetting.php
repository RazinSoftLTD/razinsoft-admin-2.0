<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsappSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'access_token' => 'encrypted',
        'app_secret' => 'encrypted',
        'gateway_secret' => 'encrypted',
        'ai_settings' => 'array',
        'is_connected' => 'boolean',
        'connected_at' => 'datetime',
        'interest_options' => 'array',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([], ['api_version' => 'v21.0', 'verify_token' => Str::random(24)]);
    }

    /**
     * Whether the shared QR gateway is set up.
     *
     * This row is now only about the gateway: the connection method and any Cloud API credentials
     * live on each number, so asking "is WhatsApp configured" globally no longer has an answer.
     */
    public function isConfigured(): bool
    {
        return filled($this->gateway_url);
    }
}
