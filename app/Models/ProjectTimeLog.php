<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One block of time logged against a project — optionally tied to a task. */
class ProjectTimeLog extends Model
{
    protected $guarded = [];

    protected $casts = ['spent_on' => 'date', 'minutes' => 'integer'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "2h 15m" style label. */
    public static function humanMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h && $m ? "{$h}h {$m}m" : ($h ? "{$h}h" : "{$m}m");
    }

    public function duration(): string
    {
        return self::humanMinutes($this->minutes);
    }
}
