<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One click on a rewritten link in a message. */
class EmailClick extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['clicked_at' => 'datetime'];

    public function log(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }
}
