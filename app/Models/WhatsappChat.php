<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappChat extends Model
{
    protected $guarded = [];

    protected $casts = ['last_message_at' => 'datetime'];

    public const STATUSES = ['open' => 'Open', 'pending' => 'Pending', 'resolved' => 'Resolved'];

    public const LEAD_QUALITIES = ['qualified' => 'Qualified', 'unqualified' => 'Unqualified'];

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'chat_id')->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WhatsappNote::class, 'chat_id')->latest();
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappLabel::class, 'whatsapp_chat_label', 'chat_id', 'label_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function isGroup(): bool
    {
        return $this->chat_type === 'group' || str_contains((string) $this->wa_id, '@g.us');
    }

    public function displayName(): string
    {
        return $this->name ?: $this->profile_name ?: ($this->isGroup() ? 'Group chat' : $this->phoneLabel());
    }

    /** Human label for the contact's address — strips the WhatsApp domain (@lid / @s.whatsapp.net). */
    public function phoneLabel(): string
    {
        if ($this->isGroup()) {
            return 'Group';
        }
        // Prefer the resolved real phone number when we have one (LID contacts hide it in wa_id).
        if ($this->phone) {
            return '+'.ltrim($this->phone, '+');
        }
        $id = preg_replace('/@.*/', '', (string) $this->wa_id);
        // A real MSISDN gets a leading +; a WhatsApp LID (privacy id) is shown as a plain id.
        return str_contains((string) $this->wa_id, '@lid') ? 'ID '.$id : '+'.$id;
    }

    /** Best-effort E.164 number (digits only) — the resolved phone, or the wa_id if it's a real number. */
    public function realNumber(): ?string
    {
        if ($this->isGroup()) {
            return null;
        }
        if ($this->phone) {
            return ltrim($this->phone, '+');
        }

        return str_contains((string) $this->wa_id, '@lid') ? null : preg_replace('/@.*/', '', (string) $this->wa_id);
    }

    /** Country of the phone number, resolved via libphonenumber. Returns ['name','code','flag'] or null. */
    public function country(): ?array
    {
        $number = $this->realNumber();
        if (! $number) {
            return null;
        }

        try {
            $util = \libphonenumber\PhoneNumberUtil::getInstance();
            $proto = $util->parse('+'.$number, null);
            $region = $util->getRegionCodeForNumber($proto); // ISO 3166-1 alpha-2, e.g. "BD"
            if (! $region || $region === 'ZZ') {
                return null;
            }
            $name = class_exists(\Locale::class)
                ? (\Locale::getDisplayRegion('-'.$region, 'en') ?: $region)
                : $region;

            return ['name' => $name, 'code' => $region, 'flag' => $this->flagEmoji($region)];
        } catch (\Throwable) {
            return null;
        }
    }

    /** Turn an ISO alpha-2 code into its flag emoji (regional-indicator letters). */
    private function flagEmoji(string $iso): string
    {
        $iso = strtoupper($iso);

        return mb_convert_encoding('&#'.(0x1F1E6 + ord($iso[0]) - 65).';', 'UTF-8', 'HTML-ENTITIES')
            .mb_convert_encoding('&#'.(0x1F1E6 + ord($iso[1]) - 65).';', 'UTF-8', 'HTML-ENTITIES');
    }
}
