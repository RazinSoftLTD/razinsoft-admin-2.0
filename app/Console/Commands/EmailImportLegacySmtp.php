<?php

namespace App\Console\Commands;

use App\Models\EmailConfig;
use App\Models\EmailSetting;
use Illuminate\Console\Command;

/**
 * Moves the single SMTP account from the old Email Settings screen into the new module.
 *
 * The old screen kept one row; the new one keeps many, and nothing sends until at least one
 * exists. Without this an installation that was happily sending yesterday goes quiet the moment
 * the module lands — which is exactly what happened in production.
 *
 * Reads and writes through both models so the encrypted password is decrypted and re-encrypted
 * rather than copied as ciphertext.
 */
class EmailImportLegacySmtp extends Command
{
    protected $signature = 'email:import-legacy-smtp {--force : Overwrite an account already imported}';

    protected $description = 'Copy the old Email Settings SMTP account into Email Configuration';

    public function handle(): int
    {
        $old = EmailSetting::first();

        if (! $old || ! $old->host || ! $old->username) {
            $this->warn('No old SMTP settings to import.');

            return self::SUCCESS;
        }

        $existing = EmailConfig::where('host', $old->host)->where('username', $old->username)->first();

        if ($existing && ! $this->option('force')) {
            $this->info("Already imported as \"{$existing->name}\" — nothing to do.");

            return self::SUCCESS;
        }

        $config = $existing ?: new EmailConfig;

        $config->fill([
            'name' => $old->from_name ?: 'Primary SMTP',
            'provider' => $this->providerFor($old->host),
            'host' => $old->host,
            'port' => (int) ($old->port ?: 587),
            'encryption' => $old->encryption ?: 'tls',
            'username' => $old->username,
            'password' => $old->password,
            'from_email' => $old->from_address ?: $old->username,
            'from_name' => $old->from_name ?: config('app.name'),
            // The first account in has to be the default, or picking one still finds nothing.
            'is_default' => true,
            'is_active' => true,
            'priority' => 1,
        ])->save();

        // Only one account may be the default.
        EmailConfig::where('id', '!=', $config->id)->update(['is_default' => false]);

        $this->info("Imported \"{$config->name}\" — {$config->host}:{$config->port} as {$config->username}.");
        $this->line('Run a connection test from Email Settings → Configuration before relying on it.');

        return self::SUCCESS;
    }

    /** Match the host against the shipped presets so the form shows the right provider. */
    private function providerFor(string $host): string
    {
        foreach (EmailConfig::PROVIDERS as $key => $preset) {
            if ($preset['host'] !== '' && strcasecmp($preset['host'], $host) === 0) {
                return $key;
            }
        }

        return 'custom';
    }
}
