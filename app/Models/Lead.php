<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    protected $guarded = [];

    /** Dropdown option sets — single source of truth for the form + validation. */
    public const SOURCES = ['Website', 'Facebook', 'LinkedIn', 'WhatsApp', 'Referral', 'Cold Call', 'Advertisement', 'Other'];

    public const INDUSTRIES = ['Technology', 'eCommerce', 'Education', 'Healthcare', 'Retail', 'Real Estate', 'Finance', 'Logistics', 'Other'];

    public const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'proposal' => 'Proposal Sent', 'negotiation' => 'Negotiation', 'won' => 'Won', 'lost' => 'Lost'];

    public const TEAMS = ['Sales', 'Support', 'Development', 'Marketing'];

    public const PRIORITIES = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
