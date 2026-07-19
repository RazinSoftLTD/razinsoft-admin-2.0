<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappQuickReply extends Model
{
    protected $guarded = [];

    /** The WhatsApp number this reply is scoped to (null = shared across all numbers). */
    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class, 'account_id');
    }
}
