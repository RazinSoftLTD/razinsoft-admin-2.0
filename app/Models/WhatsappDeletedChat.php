<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A record that someone deliberately wiped a chat from this panel.
 *
 * The phone still holds the conversation and hands it back on every history sync, so a plain
 * delete undoes itself the moment the number reconnects. This remembers the address and the
 * moment it was wiped: anything the gateway replays from BEFORE that moment is ignored, while a
 * genuinely new message afterwards starts a fresh chat, exactly as if the contact were new.
 */
class WhatsappDeletedChat extends Model
{
    protected $guarded = [];

    protected $casts = ['deleted_at' => 'datetime'];

    /** Remember that this chat was wiped, so history cannot bring it back. */
    public static function remember(WhatsappChat $chat, ?int $userId = null): void
    {
        static::create([
            'account_id' => $chat->account_id,
            'wa_id' => $chat->wa_id,
            'phone' => $chat->phone,
            'deleted_at' => now(),
            'deleted_by' => $userId,
        ]);
    }

    /**
     * Whether an incoming message is part of a conversation someone already wiped.
     *
     * Only messages older than the wipe are blocked. The contact writing again is not a
     * resurrection — it is a new conversation, and it is allowed through.
     */
    public static function blocks(?int $accountId, string $waId, ?string $phone, ?Carbon $sentAt): bool
    {
        $digits = $phone ? preg_replace('/\D/', '', $phone) : null;

        return static::where('account_id', $accountId)
            ->where(function ($q) use ($waId, $digits) {
                $q->where('wa_id', $waId);
                if ($digits && strlen($digits) >= 8) {
                    $q->orWhere('phone', $digits);
                }
            })
            ->where('deleted_at', '>=', $sentAt ?: now())
            ->exists();
    }
}
