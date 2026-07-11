<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_reply_at' => 'datetime',
        'customer_seen_at' => 'datetime',
        'unread_by_admin' => 'boolean',
        'unread_by_customer' => 'boolean',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'pending' => 'Pending',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    public const CATEGORIES = [
        'technical_support' => 'Technical Support',
        'billing' => 'Billing & Payment',
        'product_download' => 'Product Download',
        'activation_key' => 'Activation Key',
        'other' => 'Other',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TicketGroup::class, 'group_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'type_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->oldest();
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst((string) $this->priority);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** Next sequential ticket number, e.g. TICKET-001. */
    public static function nextNumber(): string
    {
        $n = (int) (self::max('id') ?? 0) + 1;

        return 'TICKET-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }
}
