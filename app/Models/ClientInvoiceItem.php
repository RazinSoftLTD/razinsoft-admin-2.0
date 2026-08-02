<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInvoiceItem extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'amount' => 'decimal:2',
        'taxes' => 'array', // [{name, rate}, …] applied to this line
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }

    /**
     * Sub-description as safe INLINE html — bold/italic kept, list items turned into
     * "• " bullet lines and block tags into <br>. DomPDF can't render nested block
     * lists inside a table cell, so we flatten them here (used by both show & PDF).
     */
    public function formattedSubDescription(): string
    {
        return \App\Support\InvoiceRichText::format((string) $this->sub_description);
    }
}
