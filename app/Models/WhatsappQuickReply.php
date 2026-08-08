<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WhatsappQuickReply extends Model
{
    protected $guarded = [];

    /**
     * The numbers this reply shows on.
     *
     * Many, deliberately: the same sentence usually belongs on several numbers, and one text
     * edited in one place beats three copies drifting apart.
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappAccount::class, 'whatsapp_quick_reply_account', 'quick_reply_id', 'account_id');
    }

    /** The number it was first written for — kept so older data still reads. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class, 'account_id');
    }
}
