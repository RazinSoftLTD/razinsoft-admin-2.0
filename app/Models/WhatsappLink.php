<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A shareable link that opens a WhatsApp chat, and counts who followed it. */
class WhatsappLink extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'is_site_button' => 'boolean'];

    /** The reserved code for the website's floating button — readable, and never reassigned. */
    public const SITE_BUTTON_CODE = 'website';

    /**
     * The one link the website's floating button uses.
     *
     * Always exists: it is created on first look rather than toggled onto some other link, so the
     * site has a permanent address to point at and editing this row is the only thing that changes
     * what the button does. It cannot be deleted or retired — see the controller.
     */
    public static function siteButton(): self
    {
        return static::firstOrCreate(
            ['code' => self::SITE_BUTTON_CODE],
            [
                'label' => 'Website floating button',
                'number' => '+8801937203743',
                'message' => "Hello RazinSoft, I would like to know more about your services.",
                'is_site_button' => true,
                'is_active' => true,
            ],
        );
    }

    public function isSiteButton(): bool
    {
        return $this->code === self::SITE_BUTTON_CODE;
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(WhatsappLinkClick::class, 'link_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Short, unambiguous codes: no O/0 or I/l, since these get read aloud and retyped. */
    public static function newCode(): string
    {
        do {
            $code = Str::lower(Str::random(7));
            $code = str_replace(['o', '0', 'i', 'l', '1'], ['a', 'b', 'c', 'd', 'e'], $code);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** The link you hand out. */
    public function shortUrl(): string
    {
        return url('/wa/'.$this->code);
    }

    /** Where it lands: WhatsApp itself. */
    public function whatsappUrl(): string
    {
        $number = preg_replace('/\D/', '', $this->number);
        $url = 'https://wa.me/'.$number;

        return filled($this->message) ? $url.'?text='.rawurlencode($this->message) : $url;
    }
}
