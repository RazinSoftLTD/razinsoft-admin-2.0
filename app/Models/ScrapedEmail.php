<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One address found by a crawl. Unique per address across every run. */
class ScrapedEmail extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_role_address' => 'boolean',
        'imported_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Mailboxes that belong to a desk rather than a person — info@, sales@, no-reply@ and friends.
     * Flagged rather than dropped: they are the useful address for a small business, and useless
     * (or a bounce) for outreach meant to reach someone by name. Which one you want depends on the
     * campaign, so the choice is left to whoever sends it.
     */
    public const ROLE_PREFIXES = [
        'info', 'sales', 'support', 'contact', 'admin', 'office', 'hello', 'help', 'enquiry',
        'enquiries', 'inquiry', 'team', 'mail', 'noreply', 'no-reply', 'donotreply', 'do-not-reply',
        'postmaster', 'webmaster', 'marketing', 'billing', 'accounts', 'careers', 'jobs', 'hr',
    ];

    public static function isRoleAddress(string $email): bool
    {
        $local = mb_strtolower(explode('@', $email)[0] ?? '');

        return in_array($local, self::ROLE_PREFIXES, true);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(EmailScrapeRun::class, 'run_id');
    }

    public function importedClient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_client_id');
    }
}
