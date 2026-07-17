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
        return $this->name ?: $this->profile_name ?: $this->wa_id;
    }
}
