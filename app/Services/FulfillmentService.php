<?php

namespace App\Services;

use App\Mail\OrderFulfilledMail;
use App\Models\ClientInvoice;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Order;
use App\Models\OrderItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FulfillmentService
{
    /**
     * Fulfil a paid order — fully idempotent. Safe to call repeatedly:
     * generates the Invoice PDF once, one License (key + downloadable file) per item once,
     * marks the order completed, and emails the customer.
     * Source-code download is "unlocked" by the License existing (gated in the account API).
     */
    public function fulfill(Order $order): void
    {
        $order->loadMissing(['items.product.latestFile', 'items.plan', 'user']);

        $this->generateInvoice($order);

        foreach ($order->items as $item) {
            // Installation plans are a service — no product license / download to issue.
            if ($item->installation_plan_id) {
                continue;
            }
            $this->generateLicense($order, $item);
        }

        if ($order->status !== 'completed') {
            $order->update(['status' => 'completed']);
        }

        $this->sendEmail($order);
    }

    /**
     * One invoice record per order (idempotent), but the PDF is always (re)rendered so it
     * reflects the current paid/unpaid state — issued at order time as UNPAID, refreshed to
     * PAID on fulfilment.
     */
    public function generateInvoice(Order $order): Invoice
    {
        // Invoice number = order number (RS-{yy}#####). Order numbers already come from the
        // serial shared with CRM invoices, so all invoice numbers read as one sequence.
        $invoice = $order->invoice()->first()
            ?? $order->invoice()->create(['invoice_number' => $order->order_number, 'issued_at' => now()]);

        // Render with the shared CRM invoice layout so both look identical.
        $pdf = Pdf::loadView('admin.invoices.pdf', [
            'invoice' => ClientInvoice::fromOrder($order, $invoice),
        ])->setPaper('a4');

        $path = "invoices/{$invoice->invoice_number}.pdf";
        Storage::disk('local')->put($path, $pdf->output());
        $invoice->update(['pdf_path' => $path]);

        return $invoice;
    }

    /** One license per order item (idempotent). */
    public function generateLicense(Order $order, OrderItem $item): License
    {
        if ($existing = $item->license()->first()) {
            return $existing;
        }

        $key = $this->uniqueLicenseKey();
        $perks = $item->relationLoaded('plan') && $item->plan ? ($item->plan->perks ?? []) : [];
        $perks = is_array($perks) ? $perks : [];

        $license = License::create([
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'user_id' => $order->user_id,
            'license_key' => $key,
            'plan_name' => $item->plan_name,
            'terms' => $this->buildTerms($item),
            'status' => 'active',
            'issued_at' => now(),
        ]);

        // Professional license certificate PDF.
        $pdf = Pdf::loadView('licenses.pdf', [
            'order' => $order->loadMissing('user'),
            'item' => $item,
            'license' => $license,
            'perks' => $perks,
        ])->setPaper('a4');

        $path = "licenses/{$key}.pdf";
        Storage::disk('local')->put($path, $pdf->output());
        $license->update(['file_path' => $path]);

        return $license;
    }

    private function uniqueLicenseKey(): string
    {
        do {
            $key = 'RZN-'.strtoupper(implode('-', str_split(Str::random(16), 4)));
        } while (License::where('license_key', $key)->exists());

        return $key;
    }

    /** Human-readable terms drawn from the bought plan (perks) — recorded on the license. */
    private function buildTerms(OrderItem $item): string
    {
        $perks = $item->relationLoaded('plan') && $item->plan ? ($item->plan->perks ?? []) : [];
        $perks = is_array($perks) ? $perks : [];

        $lines = array_merge(
            $item->plan_name ? ["Plan: {$item->plan_name}"] : [],
            $perks ? array_map(fn ($p) => "- {$p}", $perks) : ['- Standard license terms apply.'],
        );

        return implode("\n", $lines);
    }

    private function sendEmail(Order $order): void
    {
        try {
            Mail::to($order->user->email)->queue(new OrderFulfilledMail($order));
        } catch (\Throwable $e) {
            Log::warning('Order fulfilment email failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);
        }
    }
}
