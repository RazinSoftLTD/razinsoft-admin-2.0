<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientInvoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'partially_paid' => 'Partially Paid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
    ];

    public const PAYMENT_METHODS = ['Bank Transfer', 'Stripe', 'PayPal', 'Cash', 'Cheque', 'Other'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClientInvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('paid_at')->orderBy('id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallment::class)->orderBy('sort_order')->orderBy('id');
    }

    /** What the client is asked to pay right now: the requested amount (capped to due) or the full due. */
    public function payableAmount(): float
    {
        $due = $this->amountDue();
        if (! is_null($this->requested_amount)) {
            return round(min((float) $this->requested_amount, $due), 2);
        }

        return $due;
    }

    /** Re-sum payments into amount_paid and refresh the status. Call after any payment change. */
    public function recomputePaid(): void
    {
        $this->amount_paid = round((float) $this->payments()->sum('amount'), 2);
        $this->save();
        $this->syncStatus();
    }

    /** Amount still owed at this moment (total − payments recorded so far). */
    public function amountDue(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    /** Recompute status from payments + due date. Call after payments change (C5). */
    public function syncStatus(): void
    {
        if (in_array($this->status, ['draft'], true) && (float) $this->amount_paid === 0.0) {
            return; // leave drafts alone until sent
        }

        $due = $this->amountDue();
        if ($due <= 0) {
            $this->status = 'paid';
        } elseif ((float) $this->amount_paid > 0) {
            $this->status = 'partially_paid';
        } elseif ($this->due_date && $this->due_date->isPast()) {
            $this->status = 'overdue';
        } else {
            $this->status = $this->status === 'draft' ? 'draft' : 'sent';
        }

        $this->saveQuietly();
    }
}
