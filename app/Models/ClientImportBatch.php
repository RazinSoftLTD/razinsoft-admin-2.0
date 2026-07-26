<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One client-import run — lets the admin undo the last import. */
class ClientImportBatch extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['batch_key', 'imported_by', 'count', 'undone_at', 'dismissed_at', 'created_at'];

    protected $casts = ['undone_at' => 'datetime', 'dismissed_at' => 'datetime', 'created_at' => 'datetime'];

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * The most recent import still worth offering an undo for: not already undone, not closed by
     * an admin, and inside the seven-day window.
     */
    public static function undoable(): ?self
    {
        return static::whereNull('undone_at')
            ->whereNull('dismissed_at')
            ->where('count', '>', 0)
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->first();
    }
}
