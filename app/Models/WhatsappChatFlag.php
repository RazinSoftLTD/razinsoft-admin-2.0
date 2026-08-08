<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What one employee has marked on one chat: their pin, and their own "unread".
 *
 * Deliberately per-person. In a shared inbox, "I still need to answer this" is a note to
 * yourself, not an announcement to the team — and a colleague opening the chat should not
 * clear it for you.
 */
class WhatsappChatFlag extends Model
{
    protected $table = 'whatsapp_chat_flags';

    protected $guarded = [];

    protected $casts = ['pinned_at' => 'datetime', 'unread_at' => 'datetime'];

    /** Flip this person's pin on the chat; returns the new state. */
    public static function togglePin(int $chatId, int $userId): bool
    {
        $row = static::firstOrNew(['chat_id' => $chatId, 'user_id' => $userId]);
        $row->pinned_at = $row->pinned_at ? null : now();
        $row->save();

        return (bool) $row->pinned_at;
    }

    public static function markUnread(int $chatId, int $userId): void
    {
        $row = static::firstOrNew(['chat_id' => $chatId, 'user_id' => $userId]);
        $row->unread_at = now();
        $row->save();
    }

    /** Opening a chat clears only the reader's own unread mark. */
    public static function clearUnread(int $chatId, int $userId): void
    {
        static::where('chat_id', $chatId)->where('user_id', $userId)
            ->whereNotNull('unread_at')->update(['unread_at' => null]);
    }
}
