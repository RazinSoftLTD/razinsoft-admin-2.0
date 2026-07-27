<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappAccount;
use App\Models\WhatsappSetting;

/**
 * Picks the transport for one number.
 *
 * The choice belongs to the account, not the installation: a scanned WhatsApp Web number and a
 * Meta Cloud API number can now be connected at the same time, and reconfiguring one leaves the
 * others alone. It used to be a single setting, so switching driver silently took every other
 * number offline.
 */
class WhatsappManager
{
    public function provider(?WhatsappSetting $settings = null, string $sessionKey = 'default', ?WhatsappAccount $account = null): WhatsappProvider
    {
        $settings ??= WhatsappSetting::current();

        if ($account?->isCloudApi()) {
            return new CloudApiProvider($account);
        }

        return new BaileysProvider($settings, $sessionKey);
    }
}
