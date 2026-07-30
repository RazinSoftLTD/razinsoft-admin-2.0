<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapsImportRun extends Model
{
    /** Set explicitly: this app already has an unrelated CRM `leads` table. */
    protected $table = 'maps_import_runs';

    protected $fillable = [
        'run_id', 'user_id', 'source', 'country', 'city', 'category', 'query',
        'received', 'created', 'duplicates', 'rejected', 'started_at', 'last_seen_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function leads()
    {
        return $this->hasMany(MapsLead::class, 'last_run_id', 'run_id');
    }

    public function logs()
    {
        return $this->hasMany(MapsCollectionLog::class, 'run_id', 'run_id');
    }
}
