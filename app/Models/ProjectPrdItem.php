<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One submission against a PRD section — either an uploaded file, a note, or both. */
class ProjectPrdItem extends Model
{
    protected $guarded = [];

    protected $casts = ['approved_at' => 'datetime'];

    public const STATUSES = ['pending' => 'Pending review', 'approved' => 'Approved', 'rejected' => 'Changes requested'];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Who provided this — a panel user, or a client via the shared link. */
    public function submitterName(): string
    {
        return $this->uploader?->name ?? $this->submitted_by_name ?? 'Client';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isFile(): bool
    {
        return (bool) $this->path;
    }

    /** "1.4 MB" style size label. */
    public function sizeLabel(): string
    {
        $bytes = (int) $this->size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024).' KB';
        }

        return $bytes.' B';
    }
}
