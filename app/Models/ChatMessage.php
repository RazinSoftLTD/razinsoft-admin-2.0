<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    /** How long after posting a message may still be edited or deleted (minutes). */
    public const MUTATE_WINDOW_MINUTES = 60;

    protected $fillable = ['conversation_id', 'user_id', 'body', 'edited_at', 'attachment', 'attachment_name'];

    protected $casts = ['edited_at' => 'datetime'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** Within the edit/delete window (used to gate the author's edit & delete actions). */
    public function withinMutateWindow(): bool
    {
        return $this->created_at->gt(now()->subMinutes(self::MUTATE_WINDOW_MINUTES));
    }

    /** Public URL for an attached file (null if none). */
    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment ? asset('storage/'.$this->attachment) : null;
    }

    /** True when the attachment is an inline-previewable image. */
    public function getIsImageAttribute(): bool
    {
        return $this->attachment && preg_match('/\.(jpe?g|png|gif|webp|svg)$/i', $this->attachment) === 1;
    }

    /** Short plain-text preview for notifications (HTML stripped). */
    public function getPreviewAttribute(): string
    {
        $text = trim(html_entity_decode(strip_tags($this->body ?? '')));
        if ($text === '') {
            return $this->attachment ? '📎 '.($this->attachment_name ?: 'Attachment') : '';
        }

        return \Illuminate\Support\Str::limit($text, 60);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
