<?php

namespace App\Models;

use App\Models\Concerns\HasPrivacy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientInvoice extends Model
{
    use HasPrivacy, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'pay_methods' => 'array',
        'is_private' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Every invoice must have a pay-link token — never rely on the caller to set it.
        static::creating(function (self $invoice) {
            $invoice->public_token = $invoice->public_token ?: \Illuminate\Support\Str::random(40);
        });
    }

    /** Gateways offered on the public pay link. Defaults to Stripe when nothing is set. */
    public function payMethods(): array
    {
        $methods = array_values(array_intersect((array) $this->pay_methods, ['stripe', 'paypal']));

        return $methods ?: ['stripe'];
    }

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

    /**
     * Invoices visible to $actor for a given action (view/edit): the normal
     * owned/added/all permission scope for module 'invoices', PLUS — for private
     * invoices — an override so the super admin, whoever made the invoice private,
     * and anyone explicitly granted access can always see it regardless of scope.
     * Non-private invoices still only show up if the normal scope allows it.
     */
    public function scopeVisibleTo($q, User $actor, string $action = 'view')
    {
        if ($actor->isAdmin()) {
            return $q;
        }

        $scope = $actor->permissionScope('invoices', $action);

        return $q->where(function ($outer) use ($actor, $scope) {
            $outer->where('made_private_by', $actor->id)
                ->orWhereHas('privacyGrants', fn ($g) => $g->where('user_id', $actor->id));

            if ($scope === 'none') {
                return;
            }

            $outer->orWhere(function ($w) use ($actor, $scope) {
                $w->where('is_private', false);
                if ($scope !== 'all') {
                    $w->where(function ($inner) use ($actor, $scope) {
                        if (in_array($scope, ['owned', 'both'], true)) {
                            $inner->orWhere('owner_id', $actor->id);
                        }
                        if (in_array($scope, ['added', 'both'], true)) {
                            $inner->orWhere('created_by', $actor->id);
                        }
                    });
                }
            });
        });
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

    /**
     * Notes as safe inline HTML — bold/italic/underline kept, list items become
     * "◉ " bullet lines and block tags become <br>. Mirrors the item sub-description
     * formatter so DomPDF (PDF) and the web view render identically.
     */
    public function formattedNotes(): string
    {
        $html = (string) $this->notes;
        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<li[^>]*>#i', '◉ ', $html);
        $html = preg_replace('#</(p|li|ul|ol|div|h[1-6])>#i', '<br>', $html);
        $html = preg_replace('#<(p|ul|ol|div|h[1-6])[^>]*>#i', '', $html);
        $html = strip_tags($html, '<b><strong><i><em><u><br>');
        $html = preg_replace('#(<br\s*/?>\s*){2,}#i', '<br>', $html);
        $html = preg_replace('#^(<br\s*/?>)+|(<br\s*/?>)+$#i', '', trim($html));

        return $html;
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
