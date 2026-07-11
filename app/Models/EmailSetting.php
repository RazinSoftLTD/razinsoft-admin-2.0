<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class EmailSetting extends Model
{
    protected $fillable = [
        'mailer', 'host', 'port', 'username', 'password',
        'encryption', 'from_address', 'from_name', 'is_enabled',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'is_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'mailer' => 'smtp',
            'port' => 587,
            'encryption' => 'tls',
            'is_enabled' => false,
        ]);
    }

    /** Push these settings into the live mail config so Mail:: uses them. */
    public function apply(): void
    {
        if (! $this->is_enabled || ! $this->host) {
            return;
        }

        Config::set('mail.default', $this->mailer ?: 'smtp');
        Config::set('mail.mailers.smtp.host', $this->host);
        Config::set('mail.mailers.smtp.port', (int) $this->port);
        Config::set('mail.mailers.smtp.username', $this->username);
        Config::set('mail.mailers.smtp.password', $this->password);
        Config::set('mail.mailers.smtp.encryption', $this->encryption ?: null);

        if ($this->from_address) {
            Config::set('mail.from.address', $this->from_address);
            Config::set('mail.from.name', $this->from_name ?: config('app.name'));
        }
    }
}
