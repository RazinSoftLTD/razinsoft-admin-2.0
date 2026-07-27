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
        // Chronological — history-synced messages arrive out of insertion order, so sort by real time.
        return $this->hasMany(WhatsappMessage::class, 'chat_id')->orderBy('sent_at')->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WhatsappNote::class, 'chat_id')->latest();
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappLabel::class, 'whatsapp_chat_label', 'chat_id', 'label_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class, 'account_id');
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

    /** Public URL of the uploaded avatar, or null. */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path) : null;
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

    /** IANA timezone for the contact's country (best-effort), so we can show their local time. */
    public function timezone(): ?string
    {
        $country = $this->country();
        if (! $country) {
            return null;
        }
        $iso = $country['code'];
        // Big multi-timezone countries: pick a sensible primary zone.
        $primary = [
            'US' => 'America/New_York', 'CA' => 'America/Toronto', 'RU' => 'Europe/Moscow',
            'AU' => 'Australia/Sydney', 'BR' => 'America/Sao_Paulo', 'MX' => 'America/Mexico_City',
            'ID' => 'Asia/Jakarta', 'CN' => 'Asia/Shanghai', 'KZ' => 'Asia/Almaty',
        ];
        if (isset($primary[$iso])) {
            return $primary[$iso];
        }
        $zones = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $iso);

        return $zones[0] ?? null;
    }

    /** Turn an ISO alpha-2 code into its flag emoji (regional-indicator letters). */
    private function flagEmoji(string $iso): string
    {
        $iso = strtoupper($iso);

        return mb_convert_encoding('&#'.(0x1F1E6 + ord($iso[0]) - 65).';', 'UTF-8', 'HTML-ENTITIES')
            .mb_convert_encoding('&#'.(0x1F1E6 + ord($iso[1]) - 65).';', 'UTF-8', 'HTML-ENTITIES');
    }

    /** When the customer last wrote to us — the clock Meta's 24-hour rule runs on. */
    public function lastInboundAt(): ?\Illuminate\Support\Carbon
    {
        return $this->messages()->where('direction', 'in')->max('sent_at')
            ? \Illuminate\Support\Carbon::parse($this->messages()->where('direction', 'in')->max('sent_at'))
            : null;
    }

    /**
     * Whether a plain message would be refused right now.
     *
     * Only Cloud API numbers have this rule: outside 24 hours from the customer's last message,
     * Meta accepts an approved template and nothing else. A paired phone has no such limit, so it
     * is never in the closed state.
     */
    public function needsTemplate(): bool
    {
        if (! $this->account?->isCloudApi()) {
            return false;
        }

        $last = $this->lastInboundAt();

        return $last === null || $last->lt(now()->subDay());
    }
}
