<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A careers opening. Only `published` rows appear on the public website
 * (and are therefore visible to LinkedIn's crawler) — drafts stay internal,
 * giving staff a verify-before-live step.
 */
class JobOpening extends Model
{
    public const TYPES = ['Full-time', 'Part-time', 'Contract', 'Internship'];

    protected $fillable = [
        'title', 'slug', 'department', 'type', 'location',
        'description', 'apply_url', 'status', 'published_at', 'created_by',
    ];

    protected $casts = ['published_at' => 'datetime'];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** A unique slug from the title (ignoring the given id on update). */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'role';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
