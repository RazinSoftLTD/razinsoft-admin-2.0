<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientInvoice extends Model
{
    use SoftDeletes;

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
        'cancelled' => 'Cancelled',
    ];

    public const PAYMENT_METHODS = ['Bank Transfer', 'Stripe', 'PayPal', 'Cash', 'Cheque', 'Other'];

    /** Currency symbol for this invoice, from the managed currencies list (falls back to the code). */
    public function currencySymbol(): string
    {
        return Currency::symbolMap()[$this->currency] ?? $this->currency;
    }

    /**
     * Next invoice number, drawn from the single serial shared with orders — same
     * RS-{yy}##### format as order numbers, so everything reads as one sequence.
     */
    public static function nextNumber(): string
    {
        return \App\Support\InvoiceSerial::next();
    }

    /** The likely next number for display on the create form — does NOT consume the serial. */
    public static function previewNumber(): string
    {
        return \App\Support\InvoiceSerial::peek();
    }

    /**
     * Build a transient (unsaved) CRM invoice from a web order + its Invoice row, so order
     * invoices render with the exact same PDF layout. Not persisted — for display/PDF only.
     */
    public static function fromOrder(Order $order, Invoice $invoice): self
    {
        $order->loadMissing('items', 'user');
        $billing = (array) ($order->billing ?? []);
        $paid = $order->isPaid();

        $ci = new self([
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->issued_at ?? $order->created_at,
            'due_date' => null,
            'currency' => $order->currency,
            'bill_to_name' => $billing['name'] ?? $order->user?->name,
            'bill_to_company' => $billing['company'] ?? null,
            'bill_to_email' => $billing['email'] ?? $order->user?->email,
            'bill_to_address' => collect([$billing['address'] ?? null, $billing['city'] ?? null, $billing['country'] ?? null])->filter()->implode(', ') ?: null,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount,
            'tax_total' => 0,
            'total' => (float) $order->total,
            'amount_paid' => $paid ? (float) $order->total : 0,
            'status' => $paid ? 'paid' : 'sent',
            'notes' => 'Thank you for your purchase. This invoice is generated for order '.$order->order_number.'.',
            'terms' => null,
        ]);

        $ci->setRelation('items', $order->items->map(fn ($i) => new ClientInvoiceItem([
            'description' => $i->product_name,
            'sub_description' => $i->plan_name,
            'qty' => (float) $i->quantity,
            'unit_price' => (float) $i->unit_price,
            'discount_percent' => 0,
            'tax_percent' => 0,
            'amount' => (float) $i->line_total,
        ])));
        $ci->setRelation('payments', collect());

        return $ci;
    }

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

    public function activities(): HasMany
    {
        return $this->hasMany(InvoiceActivity::class)->latest('id');
    }

    /**
     * Record an activity-log entry. $actor: employee|client|system.
     * Pass the acting user (defaults to the authenticated user for employee actions).
     */
    public function logActivity(string $action, string $description, string $actor = 'employee', ?User $user = null): void
    {
        $this->activities()->create([
            'action' => $action,
            'description' => $description,
            'actor' => $actor,
            'user_id' => $user?->id ?? ($actor === 'employee' ? auth()->id() : null),
            'created_at' => now(),
        ]);
    }

    /** Public pay page — served on the FRONTEND domain (website), not the admin. */
    public function payUrl(): string
    {
        return rtrim((string) config('services.frontend_url'), '/').'/invoice/pay/'.$this->public_token;
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
        if ($this->status === 'cancelled') {
            return; // a cancelled invoice stays cancelled
        }
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
