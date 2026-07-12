<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $guarded = [];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'bool',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** The role's granted permissions as a clean {"module.action": "scope"} map. */
    public function permissionMap(): array
    {
        return \App\Support\Permissions::normalize($this->permissions);
    }

    /** Scope granted for a key (none if not granted). */
    public function grantedScope(string $key): string
    {
        return $this->permissionMap()[$key] ?? 'none';
    }

    public function hasPermission(string $key): bool
    {
        return $this->grantedScope($key) !== 'none';
    }
}
