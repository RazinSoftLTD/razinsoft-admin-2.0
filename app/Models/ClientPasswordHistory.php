<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded password an admin set/generated for a client account.
 * The password is encrypted at rest — decryptable by the app so a super admin
 * can review it, but never stored as readable plaintext in the database.
 */
class ClientPasswordHistory extends Model
{
    public const UPDATED_AT = null; // created-only log

    protected $fillable = ['user_id', 'password', 'set_by'];

    protected function casts(): array
    {
        return ['password' => 'encrypted'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
