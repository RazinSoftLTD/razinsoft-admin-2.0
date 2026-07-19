<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One connected WhatsApp number. Each account has its own gateway session and inbox. */
class WhatsappAccount extends Model
{
    use SoftDeletes;

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

    /** Permanently wipe this account: its chats, messages, notes, labels and gateway session. */
    public function wipe(): void
    {
        $chatIds = WhatsappChat::where('account_id', $this->id)->pluck('id');
        WhatsappMessage::whereIn('chat_id', $chatIds)->delete();
        \DB::table('whatsapp_notes')->whereIn('chat_id', $chatIds)->delete();
        \DB::table('whatsapp_chat_label')->whereIn('chat_id', $chatIds)->delete();
        WhatsappChat::where('account_id', $this->id)->delete();
        try {
            \App\Services\WhatsappService::for($this)->disconnect();
        } catch (\Throwable) {
        }
        $this->forceDelete();
    }

    /** Force-delete numbers that have sat in the bin longer than the retention window. */
    public static function purgeExpiredBin(int $days = 30): void
    {
        static::onlyTrashed()->where('deleted_at', '<', now()->subDays($days))->get()->each->wipe();
    }
}
