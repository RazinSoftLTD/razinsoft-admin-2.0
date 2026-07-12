<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Meeting extends Model
{
    protected $fillable = [
        'name', 'email', 'client_id', 'phone', 'dial_code', 'company', 'notes', 'date', 'start_time', 'end_time',
        'status', 'seen_at', 'follow_up_date', 'assigned_to', 'meeting_link', 'admin_notes',
    ];

    protected $casts = [
        'date' => 'date',
        'follow_up_date' => 'date',
        'seen_at' => 'datetime',
    ];

    public const STATUSES = ['pending', 'waiting_for_client', 'confirmed', 'completed', 'cancelled'];

    public const STATUS_LABELS = [
        'pending' => 'Pending',
        'waiting_for_client' => 'Waiting for Client',
        'confirmed' => 'Confirm',
        'completed' => 'Complete',
        'cancelled' => 'Cancel',
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function scopeUnread($q)
    {
        return $q->whereNull('seen_at');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** "10:00 AM – 12:00 PM" */
    public function getSlotLabelAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('g:i A').' – '.Carbon::parse($this->end_time)->format('g:i A');
    }

    public function getStartsAtAttribute(): Carbon
    {
        return Carbon::parse($this->date->toDateString().' '.$this->start_time);
    }

    public function scopeUpcoming($q)
    {
        return $q->where('date', '>=', today())->whereIn('status', ['pending', 'confirmed']);
    }
}
