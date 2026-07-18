<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSetting;

/** Resolves the active WhatsApp provider from settings. Swap drivers without touching callers. */
class WhatsappManager
{
    public function provider(?WhatsappSetting $settings = null, string $sessionKey = 'default'): WhatsappProvider
    {
        $settings ??= WhatsappSetting::current();

        return match ($settings->driver) {
            'cloud_api' => new CloudApiProvider($settings),
            default => new BaileysProvider($settings, $sessionKey),
        };
    }
}
