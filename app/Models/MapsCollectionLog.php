<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapsCollectionLog extends Model
{
    /** Set explicitly: this app already has an unrelated CRM `leads` table. */
    protected $table = 'maps_collection_logs';

    protected $fillable = ['run_id', 'level', 'event', 'message', 'place_key', 'lead_id', 'context'];

    protected $casts = ['context' => 'array'];

    /**
     * The foreign key is named explicitly. Eloquent would otherwise derive
     * `maps_lead_id` from the model name, but the column is `lead_id`.
     */
    public function lead()
    {
        return $this->belongsTo(MapsLead::class, 'lead_id');
    }
}
