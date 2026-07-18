<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One connected WhatsApp number. Each account has its own gateway session and inbox. */
class WhatsappAccount extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_connected' => 'boolean',
        'connected_at' => 'datetime',
    ];

    /** Team members allowed to see/reply on this number. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'whatsapp_account_user', 'account_id', 'user_id');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(WhatsappChat::class, 'account_id');
    }

    public function isConnected(): bool
    {
        return $this->session_state === 'connected';
    }

    /** Accounts a given user may access (assigned to). */
    public function scopeAccessibleBy($query, User $user)
    {
        return $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
    }
}
