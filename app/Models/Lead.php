<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    protected $guarded = [];

    /** Dropdown option sets — single source of truth for the form + validation. */
    public const SALUTATIONS = ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr'];

    public const SOURCES = ['WhatsApp', 'Website', 'Facebook', 'LinkedIn', 'Email', 'Others'];

    public const INDUSTRIES = ['Technology', 'eCommerce', 'Education', 'Healthcare', 'Retail', 'Real Estate', 'Finance', 'Logistics', 'Other'];

    /** A lead is just a quality signal — the full sales pipeline lives on Deals. */
    public const STATUSES = ['new' => 'New', 'qualified' => 'Qualified', 'unqualified' => 'Unqualified'];

    public const TEAMS = ['Sales', 'Support', 'Development', 'Marketing'];

    public const PRIORITIES = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];

    /**
     * Configurable option sets (managed in Settings → CRM Settings). Each falls
     * back to the const above if the settings table is empty.
     */
    public static function sourceOptions(): array
    {
        return LeadOption::labels('source') ?: self::SOURCES;
    }

    public static function departmentOptions(): array
    {
        return LeadOption::labels('department') ?: self::TEAMS;
    }

    /**
     * Product names: the RazinSoft Products module, plus any extra products added
     * in Settings → CRM Settings. Deduped and sorted.
     */
    public static function productOptions(): array
    {
        return Product::pluck('name')
            ->merge(LeadOption::labels('product'))
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected $casts = [
        'converted_at' => 'datetime',
        'next_follow_up_at' => 'date',
        'last_contacted_at' => 'datetime',
        'is_whatsapp' => 'boolean',
    ];

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** Assign an LD-{yy}#### code on creation (any path: form, import, etc.). */
    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            if (empty($lead->lead_code)) {
                $lead->lead_code = \App\Support\LeadSerial::next();
            }
        });
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_client_id');
    }

    /** Deals opened from this lead. */
    public function deals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function isConverted(): bool
    {
        return ! is_null($this->converted_client_id);
    }
}
