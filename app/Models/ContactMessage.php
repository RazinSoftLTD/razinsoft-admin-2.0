<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    /** Workflow statuses (value => label). */
    public const STATUSES = [
        'new' => 'New',
        'in_progress' => 'In Progress',
        'complete' => 'Complete',
        'ignore' => 'Ignore',
        'cancel' => 'Cancel',
    ];

    /** Tailwind classes for each status badge. */
    public const STATUS_STYLES = [
        'new' => 'bg-blue-50 text-blue-700',
        'in_progress' => 'bg-amber-50 text-amber-700',
        'complete' => 'bg-emerald-50 text-emerald-700',
        'ignore' => 'bg-gray-100 text-gray-500',
        'cancel' => 'bg-red-50 text-red-700',
    ];

    protected $guarded = [];

    protected $casts = ['is_read' => 'boolean'];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? 'New';
    }

    public function statusStyle(): string
    {
        return self::STATUS_STYLES[$this->status] ?? self::STATUS_STYLES['new'];
    }
}
