<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file attached to a task. */
class ProjectTaskFile extends Model
{
    protected $guarded = [];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function sizeLabel(): string
    {
        $b = (int) $this->size;

        return $b >= 1048576 ? round($b / 1048576, 1).' MB' : ($b >= 1024 ? round($b / 1024).' KB' : $b.' B');
    }
}
