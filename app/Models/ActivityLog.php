<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One recorded panel action (who did what request, when). */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'method', 'route_name', 'url', 'ip', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Human verb from the HTTP method. */
    public function verb(): string
    {
        return match ($this->method) {
            'POST' => 'Created / submitted',
            'PUT', 'PATCH' => 'Updated',
            'DELETE' => 'Deleted',
            default => 'Viewed',
        };
    }

    /** Friendly module name derived from the route (admin.leads.store → "Leads"). */
    public function module(): string
    {
        $name = (string) $this->route_name;
        $name = str_starts_with($name, 'admin.') ? substr($name, 6) : $name;
        $segment = str_replace(['-', '_'], ' ', explode('.', $name)[0] ?: 'dashboard');

        return ucwords($segment);
    }

    /** e.g. "Updated · Leads". */
    public function label(): string
    {
        return $this->verb().' · '.$this->module();
    }
}
