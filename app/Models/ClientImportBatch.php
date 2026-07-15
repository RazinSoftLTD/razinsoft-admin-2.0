<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One client-import run — lets the admin undo the last import. */
class ClientImportBatch extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['batch_key', 'imported_by', 'count', 'undone_at', 'created_at'];

    protected $casts = ['undone_at' => 'datetime', 'created_at' => 'datetime'];

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /** The most recent import that can still be undone (not undone, created within 7 days). */
    public static function undoable(): ?self
    {
        return static::whereNull('undone_at')
            ->where('count', '>', 0)
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->first();
    }
}
