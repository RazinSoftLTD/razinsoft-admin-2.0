<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappChat extends Model
{
    protected $guarded = [];

    protected $casts = ['last_message_at' => 'datetime'];

    public const STATUSES = ['open' => 'Open', 'pending' => 'Pending', 'resolved' => 'Resolved'];

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'chat_id')->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WhatsappNote::class, 'chat_id')->latest();
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappLabel::class, 'whatsapp_chat_label', 'chat_id', 'label_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function displayName(): string
    {
        return $this->name ?: $this->profile_name ?: $this->phoneLabel();
    }

    /** Human label for the contact's address — strips the WhatsApp domain (@lid / @s.whatsapp.net). */
    public function phoneLabel(): string
    {
        $id = preg_replace('/@.*/', '', (string) $this->wa_id);
        // A real MSISDN gets a leading +; a WhatsApp LID (privacy id) is shown as a plain id.
        return str_contains((string) $this->wa_id, '@lid') ? 'ID '.$id : '+'.$id;
    }
}
