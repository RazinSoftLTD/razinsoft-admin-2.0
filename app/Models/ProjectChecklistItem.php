<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectChecklistItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'required' => 'boolean',
        'deadline' => 'date',
        'requested_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public const STATUSES = [
        'waiting' => 'Waiting',
        'received' => 'Received',
        'rejected' => 'Rejected',
        'approved' => 'Approved',
        'need_update' => 'Need Update',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
