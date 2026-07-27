<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * One SMTP account. Several can exist; the default is used unless a caller names another, and the
 * rest act as fallbacks in priority order when the default is failing.
 */
class EmailConfig extends Model
{
    /**
     * Host/port/encryption presets, so an admin picks their provider instead of looking these up.
     * `custom` is the escape hatch — every field stays editable for all of them.
     */
    public const PROVIDERS = [
        'gmail' => ['label' => 'Gmail / Google Workspace', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Needs an App Password — a normal account password is refused.'],
        'ses' => ['label' => 'Amazon SES', 'host' => 'email-smtp.us-east-1.amazonaws.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use SMTP credentials from SES, not your AWS access keys. Match the region in the host.'],
        'mailgun' => ['label' => 'Mailgun', 'host' => 'smtp.mailgun.org', 'port' => 587, 'encryption' => 'tls', 'note' => 'Username is the full postmaster@your-domain address.'],
        'sendgrid' => ['label' => 'SendGrid', 'host' => 'smtp.sendgrid.net', 'port' => 587, 'encryption' => 'tls', 'note' => 'Username is literally "apikey"; the password is the API key.'],
        'postmark' => ['label' => 'Postmark', 'host' => 'smtp.postmarkapp.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Username and password are both the Server API token.'],
        'brevo' => ['label' => 'Brevo (Sendinblue)', 'host' => 'smtp-relay.brevo.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Password is the SMTP key, not your login password.'],
        'microsoft' => ['label' => 'Microsoft 365 / Outlook', 'host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'SMTP AUTH must be enabled on the mailbox.'],
        'zoho' => ['label' => 'Zoho Mail', 'host' => 'smtp.zoho.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use an app-specific password when 2FA is on.'],
        'custom' => ['label' => 'Custom SMTP', 'host' => '', 'port' => 587, 'encryption' => 'tls', 'note' => 'Any SMTP server — fill in the details your host gave you.'],
    ];

    public const ENCRYPTIONS = ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'];

    public const HEALTH = ['unknown' => 'Not tested', 'ok' => 'Healthy', 'failing' => 'Failing'];

    protected $guarded = [];

    protected $casts = [
        'password' => 'encrypted',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function logs()
    {
        return $this->hasMany(EmailLog::class);
    }

    public function providerLabel(): string
    {
        return self::PROVIDERS[$this->provider]['label'] ?? 'Custom SMTP';
    }

    /** Active accounts, best first — the default, then by priority. */
    public function scopeUsable(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderByDesc('is_default')->orderBy('priority')->orderBy('id');
    }

    /** The account a send should use when the caller didn't name one. */
    public static function pick(?int $id = null): ?self
    {
        if ($id && $config = static::whereKey($id)->where('is_active', true)->first()) {
            return $config;
        }

        return static::usable()->first();
    }

    /** Make this the default, clearing the flag on the others. */
    public function makeDefault(): void
    {
        static::where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true, 'is_active' => true])->save();
    }

    /**
     * Point Laravel's mailer at this account for the current request/job.
     *
     * Written as its own transport name rather than overwriting `smtp`, so two accounts used in
     * the same worker process cannot leak settings into each other.
     */
    public function applyToMailer(): string
    {
        $name = 'db-'.$this->id;

        Config::set("mail.mailers.{$name}", array_filter([
            'transport' => 'smtp',
            'host' => $this->host,
            'port' => (int) $this->port,
            'encryption' => $this->encryption ?: null,
            'username' => $this->username ?: null,
            'password' => $this->password ?: null,
            'timeout' => 30,
            // Handed to the SMTP server as the envelope sender, so bounces land where we ask.
            'local_domain' => parse_url(config('app.url'), PHP_URL_HOST),
        ], fn ($v) => $v !== null));

        Config::set('mail.from.address', $this->from_email);
        Config::set('mail.from.name', $this->from_name ?: config('app.name'));

        return $name;
    }

    /**
     * Make this the mailer Laravel reaches for when nothing names one.
     *
     * applyToMailer() only registers the account; without this, plain `Mail::` calls would still
     * go to whatever MAIL_MAILER says in .env — which on this installation is `log`, meaning the
     * message is written to a file and quietly never sent.
     */
    public function makeDefaultMailer(): void
    {
        Config::set('mail.default', $this->applyToMailer());
    }

    public function markHealthy(): void
    {
        $this->forceFill(['health' => 'ok', 'last_checked_at' => now(), 'last_error' => null])->save();
    }

    public function markFailing(string $error): void
    {
        $this->forceFill([
            'health' => 'failing',
            'last_checked_at' => now(),
            'last_error' => mb_substr($error, 0, 2000),
        ])->save();
    }

    /** Sent in the last hour / today, for the throttle and the health card. */
    public function sentInLastHour(): int
    {
        return $this->logs()->where('sent_at', '>=', now()->subHour())->count();
    }

    public function sentToday(): int
    {
        return $this->logs()->whereDate('sent_at', today())->count();
    }

    /** Whether this account still has room inside the provider's limits. */
    public function withinLimits(): bool
    {
        if ($this->hourly_limit && $this->sentInLastHour() >= $this->hourly_limit) {
            return false;
        }

        return ! ($this->daily_limit && $this->sentToday() >= $this->daily_limit);
    }
}
