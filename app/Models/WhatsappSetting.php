<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'access_token' => 'encrypted',
        'app_secret' => 'encrypted',
        'gateway_secret' => 'encrypted',
        'is_connected' => 'boolean',
        'connected_at' => 'datetime',
        'interest_options' => 'array',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([], ['api_version' => 'v21.0', 'verify_token' => \Illuminate\Support\Str::random(24)]);
    }

    /** Ready to send/receive? Depends on the active driver. */
    public function isConfigured(): bool
    {
        return $this->driver === 'cloud_api'
            ? filled($this->phone_number_id) && filled($this->access_token)
            : filled($this->gateway_url);
    }

    public function isConnected(): bool
    {
        return $this->driver === 'cloud_api' ? (bool) $this->is_connected : $this->session_state === 'connected';
    }
}
