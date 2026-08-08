<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SiteChatMessage extends Model
{
    protected $guarded = [];

    protected $casts = ['ai_generated' => 'boolean', 'read_at' => 'datetime'];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(SiteChat::class, 'chat_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment ? Storage::disk('public')->url($this->attachment) : null;
    }
}
