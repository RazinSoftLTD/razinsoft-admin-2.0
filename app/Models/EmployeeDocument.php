<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file kept against an employee — contract, NID, certificate and so on. */
class EmployeeDocument extends Model
{
    public const CATEGORIES = [
        'contract' => 'Contract',
        'nid' => 'NID / Passport',
        'certificate' => 'Certificate',
        'cv' => 'CV',
        'other' => 'Other',
    ];

    protected $guarded = [];

    protected $casts = ['issued_on' => 'date', 'expires_on' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }

    public function sizeLabel(): string
    {
        $kb = $this->size / 1024;

        return $kb >= 1024 ? round($kb / 1024, 1).' MB' : max(1, round($kb)).' KB';
    }
}
