<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry in an invoice's activity / audit trail. */
class InvoiceActivity extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Human label for who did it. */
    public function actorLabel(): string
    {
        if ($this->actor === 'client') {
            return $this->user?->name ? $this->user->name.' (client)' : 'Client';
        }
        if ($this->actor === 'system') {
            return 'System';
        }

        return $this->user?->name ? $this->user->name.' (employee)' : 'An employee';
    }
}
