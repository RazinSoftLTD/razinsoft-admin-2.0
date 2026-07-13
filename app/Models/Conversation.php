<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['type', 'name', 'photo', 'slug', 'description', 'created_by', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    /** Public URL for the channel avatar (null → callers show a default icon). */
    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo) {
            return null;
        }

        return str_starts_with($this->photo, 'http') ? $this->photo : asset('storage/'.$this->photo);
    }

    /** Whether a user manages this group (creator/managers) or is an admin. */
    public function isManagedBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        $member = $this->members->firstWhere('id', $user->id);

        return $member !== null && (bool) $member->pivot->is_manager;
    }

    /** A unique slug for a channel name (dev-team, dev-team-2, …). */
    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug($name) ?: 'channel';
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
            ->withPivot('is_manager', 'last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    /** What this conversation is called from a given viewer's perspective. */
    public function titleFor(User $viewer): string
    {
        if ($this->isGroup()) {
            return $this->name ?: 'Untitled group';
        }
        if ($this->isClient()) {
            return $this->clientMember()?->name ?? 'Client';
        }
        $other = $this->members->firstWhere('id', '!=', $viewer->id);

        return $other->name ?? 'Direct message';
    }

    /** The other person in a direct conversation (null for groups). */
    public function counterpart(User $viewer): ?User
    {
        return $this->isGroup() ? null : $this->members->firstWhere('id', '!=', $viewer->id);
    }

    public function isClient(): bool
    {
        return $this->type === 'client';
    }

    /** The customer this client conversation belongs to. */
    public function clientMember(): ?User
    {
        return $this->members->firstWhere('role', User::ROLE_CUSTOMER);
    }

    /** Unread messages for a viewer = others' messages newer than their last_read_at. */
    public function unreadCountFor(User $viewer): int
    {
        $member = $this->members->firstWhere('id', $viewer->id);
        if (! $member) {
            return 0;
        }
        $since = $member->pivot->last_read_at;

        return $this->messages()
            ->where('user_id', '!=', $viewer->id)
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->count();
    }
}
