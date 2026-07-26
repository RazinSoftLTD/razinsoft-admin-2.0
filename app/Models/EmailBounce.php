<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A bounce reported by the provider. Hard bounces suppress the address. */
class EmailBounce extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array'];

    public function log(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }
}
