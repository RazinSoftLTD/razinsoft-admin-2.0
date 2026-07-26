<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A working pattern assigned to an employee for a period — the shift roster. */
class EmployeeShift extends Model
{
    public const DAYS = ['0' => 'Sun', '1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat'];

    protected $guarded = [];

    protected $casts = ['effective_from' => 'date:Y-m-d', 'effective_to' => 'date:Y-m-d'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Still in force today. */
    public function isCurrent(): bool
    {
        return $this->effective_from->lte(today())
            && ($this->effective_to === null || $this->effective_to->gte(today()));
    }

    /** ['Fri', 'Sat'] from the stored csv. */
    public function weekOffLabels(): array
    {
        return collect(explode(',', (string) $this->week_offs))
            ->filter(fn ($d) => $d !== '')
            ->map(fn ($d) => self::DAYS[trim($d)] ?? $d)
            ->all();
    }
}
