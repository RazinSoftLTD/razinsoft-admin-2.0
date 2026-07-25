<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One phone number belonging to a Lead or a client (User). The normalized `e164` is what
 * lead ↔ client matching joins on, so formatting differences never break the link.
 */
class ContactNumber extends Model
{
    public const LABELS = [
        'mobile' => 'Mobile',
        'office' => 'Office',
        'whatsapp' => 'WhatsApp',
        'other' => 'Other',
    ];

    protected $fillable = ['label', 'dial_code', 'number', 'e164', 'is_primary', 'is_whatsapp', 'position'];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_whatsapp' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Keep the matching key in step with whatever was typed.
        static::saving(function (self $row) {
            $row->e164 = self::toE164($row->number, $row->dial_code, $row->contactable?->country ?? null);
        });
    }

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    /** '+880 1877-987557' → '+8801877987557'; null when it isn't a valid number. */
    public static function toE164(?string $number, ?string $dialCode = null, ?string $country = null): ?string
    {
        $parts = Phone::normalize($number, $country, $dialCode);
        if ($parts) {
            return $parts['dial'].$parts['number'];
        }

        // Lots of imported rows hold a full international number with the leading "+" missing
        // ("971527613113"). With no country hint libphonenumber can't place those, so retry
        // once as "+<digits>" — it still has to validate, and both sides of a lead ↔ client
        // match run through this same rule, so the key stays consistent either way.
        $digits = preg_replace('/\D/', '', (string) $number);
        if (blank($dialCode) && blank($country) && strlen($digits) >= 10 && ! str_starts_with(trim((string) $number), '+')) {
            $parts = Phone::normalize('+'.$digits);

            return $parts ? $parts['dial'].$parts['number'] : null;
        }

        return null;
    }

    /** Pretty form for display: '+880 1877987557'. */
    public function display(): string
    {
        return trim(($this->dial_code ?: '').' '.$this->number);
    }

    /** Digits only — what wa.me links need. */
    public function whatsappNumber(): string
    {
        return preg_replace('/\D/', '', $this->e164 ?: (($this->dial_code ?: '').$this->number));
    }
}
