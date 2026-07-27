<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Single-row settings for Meta's Conversions API. */
class MetaCapiSetting extends Model
{
    /** The events we are able to send, and what each one means. */
    public const EVENTS = [
        'Purchase' => 'An order is paid',
        'Lead' => 'A contact form or meeting booking comes in',
        'CompleteRegistration' => 'A customer creates an account',
        'InitiateCheckout' => 'Checkout is started',
    ];

    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
        'access_token' => 'encrypted',
        'events' => 'array',
        'last_sent_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::first() ?? static::create([
            'api_version' => 'v21.0',
            'events' => array_keys(self::EVENTS),
        ]);
    }

    public function isConfigured(): bool
    {
        return filled($this->pixel_id) && filled($this->access_token);
    }

    /** Whether this event should be sent at all. */
    public function sends(string $event): bool
    {
        return $this->is_enabled
            && $this->isConfigured()
            && in_array($event, $this->events ?? array_keys(self::EVENTS), true);
    }
}
