<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One tracking-pixel hit. Several per message is normal — mail clients re-fetch images. */
class EmailOpen extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['opened_at' => 'datetime'];

    public function log(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }
}
