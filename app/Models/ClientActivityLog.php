<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One website page visit by a logged-in client. */
class ClientActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['client_id', 'country', 'path', 'title', 'error_code', 'referrer', 'ip', 'user_agent', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
