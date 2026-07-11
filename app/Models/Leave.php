<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    protected $guarded = [];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public const TYPES = ['casual' => 'Casual', 'sick' => 'Sick', 'annual' => 'Annual', 'unpaid' => 'Unpaid', 'other' => 'Other'];
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function typeLabel(): string { return self::TYPES[$this->leave_type] ?? ucfirst((string) $this->leave_type); }
    public function statusLabel(): string { return self::STATUSES[$this->status] ?? ucfirst((string) $this->status); }

    /** Inclusive day count. */
    public function days(): int { return $this->from_date && $this->to_date ? $this->from_date->diffInDays($this->to_date) + 1 : 0; }
}
