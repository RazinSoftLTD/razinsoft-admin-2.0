<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealActivity extends Model
{
    protected $fillable = ['deal_id', 'user_id', 'type', 'body'];

    public const TYPES = ['note' => 'Note', 'call' => 'Call', 'meeting' => 'Meeting', 'email' => 'Email', 'stage' => 'Stage change'];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
