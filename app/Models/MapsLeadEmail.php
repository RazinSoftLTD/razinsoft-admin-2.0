<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One address found on a collected lead's website. */
class MapsLeadEmail extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_generic' => 'boolean',
        'same_domain' => 'boolean',
    ];

    public function lead()
    {
        return $this->belongsTo(MapsLead::class, 'maps_lead_id');
    }

    /** Addresses safe to send unsolicited mail to: shared inboxes, not people. */
    public function scopeGeneric($query)
    {
        return $query->where('is_generic', true);
    }
}
