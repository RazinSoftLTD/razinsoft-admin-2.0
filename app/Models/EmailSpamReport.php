<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A "this is spam" complaint. Always suppresses the address — mailing on is what gets a domain blocked. */
class EmailSpamReport extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array'];

    public function log(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }
}
