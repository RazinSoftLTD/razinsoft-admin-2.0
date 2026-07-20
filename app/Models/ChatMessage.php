<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    /** How long after posting a message may still be edited or deleted (minutes). */
    public const MUTATE_WINDOW_MINUTES = 60;

    protected $fillable = ['conversation_id', 'user_id', 'reply_to_id', 'body', 'checklist', 'edited_at', 'attachment', 'attachment_name', 'reactions'];

    protected $casts = [
        'checklist' => 'array','edited_at' => 'datetime', 'reactions' => 'array'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** The message this one is a reply to (WhatsApp-style quote), if any. */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * Compact quote data for the message being replied to — author + text/attachment
     * snippet — or null when this isn't a reply (or the quoted message was deleted).
     */
    public function quoted(): ?array
    {
        $q = $this->replyTo;
        if (! $q) {
            return null;
        }

        return [
            'id' => $q->id,
            'author' => $q->author->name ?? '—',
            'preview' => $q->preview,
            'is_image' => $q->is_image,
        ];
    }

    /** Raw reaction map ({userId: emoji}) — always an array, never null. */
    public function reactionMap(): array
    {
        return is_array($this->reactions) ? $this->reactions : [];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
}
