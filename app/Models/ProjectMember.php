<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    /** Per-project access level for a member. */
    public const ACCESS_LEVELS = [
        'view' => 'View only',
        'tasks' => 'Manage tasks',
        'manage' => 'Full manage',
    ];

    protected $guarded = [];

    public function accessLabel(): string
    {
        return self::ACCESS_LEVELS[$this->access_level] ?? 'Full manage';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
