<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person following a WhatsApp link. */
class WhatsappLinkClick extends Model
{
    protected $guarded = [];

    protected $casts = ['clicked_at' => 'datetime'];

    public function link(): BelongsTo
    {
        return $this->belongsTo(WhatsappLink::class, 'link_id');
    }
}
