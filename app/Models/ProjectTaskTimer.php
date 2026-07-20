<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A timer someone left running on a task. Stopping it becomes a ProjectTimeLog. */
class ProjectTaskTimer extends Model
{
    protected $guarded = [];

    protected $casts = ['started_at' => 'datetime'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRunning(): bool
    {
        return $this->started_at !== null;
    }

    /** Seconds banked from earlier runs plus the current run, if any. */
    public function elapsedSeconds(): int
    {
        return (int) $this->banked_seconds
            + ($this->started_at ? (int) $this->started_at->diffInSeconds(now()) : 0);
    }

    public function elapsedMinutes(): int
    {
        return intdiv($this->elapsedSeconds(), 60);
    }

    /** "01:24:18" for the live clock. */
    public function clock(): string
    {
        $s = $this->elapsedSeconds();

        return sprintf('%02d:%02d:%02d', intdiv($s, 3600), intdiv($s, 60) % 60, $s % 60);
    }
}
