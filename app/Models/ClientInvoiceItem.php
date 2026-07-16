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
        $html = (string) $this->sub_description;
        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<li[^>]*>#i', '◉ ', $html);              // list bullets (matches the invoice design)
        $html = preg_replace('#</(p|li|ul|ol|div|h[1-6])>#i', '<br>', $html); // block ends → break
        $html = preg_replace('#<(p|ul|ol|div|h[1-6])[^>]*>#i', '', $html);    // drop block openers
        $html = strip_tags($html, '<b><strong><i><em><u><br>');         // keep only inline formatting
        $html = preg_replace('#(<br\s*/?>\s*){2,}#i', '<br>', $html);    // collapse blank lines
        $html = preg_replace('#^(<br\s*/?>)+|(<br\s*/?>)+$#i', '', trim($html));

        return $html;
    }
}
