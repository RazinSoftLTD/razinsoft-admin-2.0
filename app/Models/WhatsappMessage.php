<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    protected $guarded = [];

    protected $casts = ['sent_at' => 'datetime'];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(WhatsappChat::class, 'chat_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function mediaUrl(): ?string
    {
        return $this->media_path ? asset('storage/'.$this->media_path) : null;
    }
}
