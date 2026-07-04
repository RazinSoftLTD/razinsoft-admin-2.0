<?php

namespace App\Mail;

use App\Models\ClientInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ClientInvoice $invoice) {}

    public function build()
    {
        $this->invoice->loadMissing('items');
        $payUrl = route('pay.invoice.show', $this->invoice->public_token);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.invoices.pdf', ['invoice' => $this->invoice->load('payments')]);

        return $this->subject("Invoice {$this->invoice->invoice_number} from RazinSoft")
            ->view('emails.invoice', ['invoice' => $this->invoice, 'payUrl' => $payUrl])
            ->attachData($pdf->output(), "{$this->invoice->invoice_number}.pdf", ['mime' => 'application/pdf']);
    }
}
