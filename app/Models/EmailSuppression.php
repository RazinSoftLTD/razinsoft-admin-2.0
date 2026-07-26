<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Addresses we must not mail again — hard bounces, spam complaints, unsubscribes.
 *
 * Checked before every send. Continuing to mail addresses that bounced or complained is the
 * fastest way to get a sending domain blocked, so this list overrides everything else.
 */
class EmailSuppression extends Model
{
    public const REASONS = [
        'bounce' => 'Hard bounce',
        'complaint' => 'Spam complaint',
        'unsubscribe' => 'Unsubscribed',
        'manual' => 'Added by an admin',
    ];

    protected $guarded = [];

    /** Add an address, keeping the first reason it was suppressed for. */
    public static function add(string $email, string $reason, ?string $note = null): self
    {
        return static::firstOrCreate(
            ['email' => mb_strtolower(trim($email))],
            ['reason' => $reason, 'note' => $note],
        );
    }

    public static function has(string $email): bool
    {
        return static::where('email', mb_strtolower(trim($email)))->exists();
    }

    /** Suppressed addresses out of the given list, lowercased — one query for a whole campaign. */
    public static function filter(array $emails): array
    {
        $lower = array_map(fn ($e) => mb_strtolower(trim($e)), $emails);

        return static::whereIn('email', $lower)->pluck('email')->all();
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
