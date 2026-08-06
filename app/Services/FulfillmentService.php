<?php

namespace App\Services;

use App\Models\ClientInvoice;
use App\Models\DomainOrder;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Email\EmailDispatcher;
use App\Services\Meta\ConversionsApi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
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
            // A domain line registers a name instead of issuing a licence. register() owns its
            // own failure state (action_needed) — a registrar problem must not stop the licences
            // and downloads the rest of the order still owes.
            if ($item->domain_order_id) {
                if ($domainOrder = DomainOrder::find($item->domain_order_id)) {
                    app(DomainOrderService::class)->register($domainOrder);
                }

                continue;
            }
            $this->generateLicense($order, $item);
        }

        if ($order->status !== 'completed') {
            $order->update(['status' => 'completed']);
        }

        $this->sendEmail($order);

        $this->reportPurchase($order);
    }

    /**
     * Tell Meta the order was paid.
     *
     * event_id is the order number, so if the browser pixel also fires Purchase on the thank-you
     * page with that same id, Meta counts one sale rather than two.
     */
    private function reportPurchase(Order $order): void
    {
        try {
            [$first, $last] = array_pad(explode(' ', trim((string) $order->user?->name), 2), 2, null);

            ConversionsApi::make()->send('Purchase', 'order-'.$order->order_number, [
                'value' => $order->total,
                'currency' => $order->currency ?: 'USD',
                'order_id' => $order->order_number,
                'num_items' => $order->items->count(),
                'contents' => $order->items->map(fn ($i) => [
                    'id' => (string) $i->product_id,
                    'quantity' => (int) ($i->quantity ?: 1),
                    'item_price' => round((float) $i->unit_price, 2),
                ])->values()->all(),
            ], [
                'email' => $order->user?->email,
                'phone' => $order->user?->phone,
                'first_name' => $first,
                'last_name' => $last,
                'id' => $order->user_id,
            ]);
        } catch (\Throwable $e) {
            // Tracking must never break fulfilment.
            report($e);
        }
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
            $order->loadMissing('items', 'invoice', 'user');

            $items = $order->items
                ->map(fn ($i) => '<li><strong>'.e($i->product_name).'</strong> — '.e($i->plan_name ?? 'License').'</li>')
                ->implode('');

            app(EmailDispatcher::class)->sendTemplate('order_confirmation', $order->user->email, [
                'customer_name' => $order->user->name,
                'order_number' => $order->order_number,
                'order_total' => '$'.number_format((float) $order->total, 2),
                'order_items' => $items,
                'order_url' => rtrim((string) config('services.frontend_url', config('app.frontend_url')), '/').'/dashboard',
            ], [
                'event' => 'order.confirmed',
                'module' => 'orders',
                'related' => $order,
                'user_id' => $order->user->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Order fulfilment email failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);
        }
    }
}
