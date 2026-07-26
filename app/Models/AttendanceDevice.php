<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A biometric reader (ZKTeco etc.) that pushes or is polled for punch logs. */
class AttendanceDevice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $d) {
            $d->api_token ??= Str::random(48);       // used by the on-site bridge to post logs
        });
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'device_id');
    }
}
