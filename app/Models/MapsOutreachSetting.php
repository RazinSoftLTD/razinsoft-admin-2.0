<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Single-row settings for automatic outreach to collected Maps leads.
 *
 * Defaults are deliberately conservative: the master switch is off, and the
 * daily ceiling is low. Cold mail is the one place in this app where sending
 * more, faster, makes results worse - once a domain is flagged, the invoices and
 * password resets stop arriving too.
 */
class MapsOutreachSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
        'discover_emails' => 'boolean',
        'auto_send' => 'boolean',
        'allowed_countries' => 'array',
        'quota_date' => 'date',
        'last_sent_at' => 'datetime',
    ];

    /**
     * The settings row, created on first use.
     *
     * The defaults are declared here rather than relied on from the schema:
     * a freshly created model does not read column defaults back from the
     * database, which left daily_limit and min_gap_seconds null in memory and
     * silently deferred every send.
     */
    public static function current(): self
    {
        return static::first() ?? static::create([
            'is_enabled' => false,
            'discover_emails' => true,
            'auto_send' => false,
            'template_key' => 'maps_lead_outreach',
            'daily_limit' => 50,
            'min_gap_seconds' => 90,
            'quota_used' => 0,
        ]);
    }

    /** Whether email lookups should run at all. */
    public function discovers(): bool
    {
        return $this->is_enabled && $this->discover_emails;
    }

    /** Whether a found address should be mailed without a human pressing Send. */
    public function sendsAutomatically(): bool
    {
        return $this->is_enabled && $this->auto_send;
    }

    /**
     * How many messages are still allowed today. Resets on the first call of a
     * new day rather than needing a scheduled task.
     */
    public function remainingToday(): int
    {
        $today = Carbon::today();

        if (! $this->quota_date || ! $this->quota_date->isSameDay($today)) {
            $this->forceFill(['quota_date' => $today, 'quota_used' => 0])->save();
        }

        return max(0, $this->daily_limit - $this->quota_used);
    }

    /**
     * Whether enough time has passed since the last message. Keeps a burst of
     * newly collected leads from turning into a burst of mail.
     */
    public function gapElapsed(): bool
    {
        return ! $this->last_sent_at
            || $this->last_sent_at->diffInSeconds(now()) >= $this->min_gap_seconds;
    }

    /** Country filter, applied to the search country the lead was collected under. */
    public function allowsCountry(?string $country): bool
    {
        $allowed = array_filter((array) $this->allowed_countries);

        return $allowed === [] || in_array($country, $allowed, true);
    }

    /** Record one send against today's quota. */
    public function countSend(): void
    {
        $this->remainingToday(); // rolls the day over if needed
        $this->forceFill([
            'quota_used' => $this->quota_used + 1,
            'last_sent_at' => now(),
        ])->save();
    }
}
