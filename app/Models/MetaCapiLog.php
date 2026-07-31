<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One event handed to Meta's Conversions API, and what came back. */
class MetaCapiLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'backfilled' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function scopeFailed($q)
    {
        return $q->where('status', 'failed');
    }
}
