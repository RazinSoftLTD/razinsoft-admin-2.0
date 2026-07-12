<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    protected $guarded = [];

    /** Dropdown option sets — single source of truth for the form + validation. */
    public const SALUTATIONS = ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr'];

    public const SOURCES = ['Website', 'Facebook', 'LinkedIn', 'WhatsApp', 'Referral', 'Cold Call', 'Advertisement', 'Other'];

    public const INDUSTRIES = ['Technology', 'eCommerce', 'Education', 'Healthcare', 'Retail', 'Real Estate', 'Finance', 'Logistics', 'Other'];

    /** A lead is just a quality signal — the full sales pipeline lives on Deals. */
    public const STATUSES = ['new' => 'New', 'qualified' => 'Qualified', 'unqualified' => 'Unqualified'];

    public const TEAMS = ['Sales', 'Support', 'Development', 'Marketing'];

    public const PRIORITIES = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];

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
