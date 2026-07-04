<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RecurringInvoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'items' => 'array',
        'next_run_at' => 'date',
        'last_run_at' => 'date',
        'active' => 'boolean',
    ];

    public const INTERVALS = ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function advanceDate(\Carbon\Carbon $from): \Carbon\Carbon
    {
        return match ($this->interval) {
            'weekly' => $from->copy()->addWeek(),
            'quarterly' => $from->copy()->addMonthsNoOverflow(3),
            'yearly' => $from->copy()->addYearNoOverflow(),
            default => $from->copy()->addMonthNoOverflow(),
        };
    }

    /** Generate one ClientInvoice from this profile and roll next_run_at forward. */
    public function generate(): ClientInvoice
    {
        $client = $this->client;
        $subtotal = $discount = $tax = 0;
        $lines = [];
        foreach ($this->items as $i => $row) {
            $qty = (float) ($row['qty'] ?? 1);
            $price = (float) ($row['unit_price'] ?? 0);
            $gross = $qty * $price;
            $d = $gross * ((float) ($row['discount_percent'] ?? 0)) / 100;
            $net = $gross - $d;
            $subtotal += $gross;
            $discount += $d;
            $tax += $net * ((float) ($row['tax_percent'] ?? 0)) / 100;
            $lines[] = [
                'description' => $row['description'] ?? 'Item', 'sub_description' => $row['sub_description'] ?? null,
                'qty' => $qty, 'unit_price' => $price,
                'discount_percent' => (float) ($row['discount_percent'] ?? 0), 'tax_percent' => (float) ($row['tax_percent'] ?? 0),
                'amount' => round($net, 2), 'sort_order' => $i,
            ];
        }

        $invoice = ClientInvoice::create([
            'invoice_number' => ClientInvoice::nextNumber(),
            'public_token' => Str::random(40),
            'client_id' => $client?->id,
            'bill_to_name' => $client?->name ?? $this->title,
            'bill_to_company' => $client?->company,
            'bill_to_email' => $client?->email,
            'bill_to_phone' => $client?->phone,
            'bill_to_address' => $client ? collect([$client->address, $client->city, $client->state, $client->country, $client->zip])->filter()->join(', ') : null,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays($this->due_days)->toDateString(),
            'currency' => $this->currency,
            'status' => 'sent',
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'tax_total' => round($tax, 2),
            'total' => round($subtotal - $discount + $tax, 2),
            'notes' => $this->notes,
            'terms' => $this->terms,
            'payment_method' => $this->payment_method,
            'created_by' => $this->created_by,
        ]);
        $invoice->items()->createMany($lines);

        $this->update([
            'last_run_at' => now()->toDateString(),
            'next_run_at' => $this->advanceDate(now())->toDateString(),
            'generated_count' => $this->generated_count + 1,
        ]);

        return $invoice;
    }
}
