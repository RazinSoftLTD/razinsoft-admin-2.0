<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One conversation with a website visitor. */
class SiteChat extends Model
{
    protected $guarded = [];

    protected $casts = ['last_message_at' => 'datetime', 'ai_handover_at' => 'datetime'];

    public const STATUSES = ['open' => 'Open', 'pending' => 'Pending', 'resolved' => 'Resolved'];

    public function messages(): HasMany
    {
        return $this->hasMany(SiteChatMessage::class, 'chat_id')->orderBy('id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** Something to call them by: their name, their email, or the visitor they are. */
    public function displayName(): string
    {
        return $this->name ?: ($this->email ?: 'Visitor #'.$this->id);
    }

    public function initials(): string
    {
        $name = trim((string) $this->displayName());

        return mb_strtoupper(mb_substr($name === '' ? 'V' : $name, 0, 1));
    }
}
