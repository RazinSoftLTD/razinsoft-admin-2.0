<?php

namespace App\Services\Email;

use App\Models\ClientInvoice;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Storage;

/**
 * Sends an invoice, or a reminder about one, through the email module.
 *
 * The PDF is rendered and stored before queueing rather than built inside the mailable. Two
 * reasons: the worker runs later, in another process, so the attachment has to exist on disk by
 * then; and it means the log keeps the exact document that went to the client, not a fresh render
 * of an invoice that may have been edited since.
 */
class InvoiceMailer
{
    public function __construct(private EmailDispatcher $dispatcher) {}

    public function send(ClientInvoice $invoice): ?EmailLog
    {
        return $this->queue($invoice, 'invoice_sent', 'invoice.sent');
    }

    public function remind(ClientInvoice $invoice): ?EmailLog
    {
        // A reminder is a deliberate repeat of a message the client already has, so the duplicate
        // guard has to be told to stand down.
        return $this->queue($invoice, 'invoice_reminder', 'invoice.reminder', dedupe: false);
    }

    private function queue(ClientInvoice $invoice, string $template, string $event, bool $dedupe = true): ?EmailLog
    {
        $invoice->loadMissing('items', 'client', 'payments');

        return $this->dispatcher->sendTemplate($template, (string) $invoice->bill_to_email, [
            'customer_name' => $invoice->bill_to_name ?: $invoice->client?->name,
            'invoice_number' => $invoice->invoice_number,
            'invoice_total' => $invoice->currencySymbol().number_format((float) $invoice->total, 2),
            'due_date' => $invoice->due_date?->format('j M Y') ?: 'receipt',
            'invoice_url' => $invoice->payUrl(),
        ], [
            'event' => $event,
            'module' => 'invoices',
            'related' => $invoice,
            'to_name' => $invoice->bill_to_name,
            'dedupe' => $dedupe,
            'attachments' => array_filter([$this->pdf($invoice)]),
        ]);
    }

    /**
     * Render the invoice to a stored PDF and describe it for the attachment row.
     *
     * A failure here must not stop the email: an invoice with a working pay link is worth more to
     * the client than no email at all.
     *
     * @return array{name: string, path: string, mime: string, size: int}|null
     */
    private function pdf(ClientInvoice $invoice): ?array
    {
        try {
            $bytes = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.invoices.pdf', ['invoice' => $invoice])->output();

            $path = "invoices/{$invoice->id}/{$invoice->invoice_number}-".now()->format('YmdHis').'.pdf';
            Storage::disk('public')->put($path, $bytes);

            return [
                'name' => "{$invoice->invoice_number}.pdf",
                'path' => $path,
                'mime' => 'application/pdf',
                'size' => strlen($bytes),
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
