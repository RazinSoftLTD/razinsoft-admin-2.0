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

    /** The link the website's floating button points at, if one is set. */
    public static function siteButton(): ?self
    {
        return static::where('is_site_button', true)->where('is_active', true)->first();
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
