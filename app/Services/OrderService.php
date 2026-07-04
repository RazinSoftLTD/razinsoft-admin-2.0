<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function createFromCheckout(User $user, array $data): Order
    {
        $lines = [];
        $subtotal = 0;

        foreach ($data['items'] as $row) {
            $product = Product::published()
                ->when(isset($row['slug']), fn ($q) => $q->where('slug', $row['slug']))
                ->when(isset($row['product_id']), fn ($q) => $q->whereKey($row['product_id']))
                ->first();

            if (! $product) {
                throw ValidationException::withMessages(['items' => ['A product in your cart is no longer available.']]);
            }

            $plan = isset($row['plan_id']) ? Plan::where('product_id', $product->id)->whereKey($row['plan_id'])->first() : null;
            // Price priority: chosen plan → extended license (ext_price) → regular base price.
            $unit = $plan
                ? (float) $plan->price
                : (($row['license_type'] ?? null) === 'extended' ? (float) $product->ext_price : (float) $product->price);
            $qty = max(1, (int) ($row['qty'] ?? 1));
            $lineTotal = round($unit * $qty, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'product_id' => $product->id,
                'plan_id' => $plan?->id,
                'product_name' => $product->name,
                'plan_name' => $plan?->name,
                'license_type' => $row['license_type'] ?? null,
                'unit_price' => $unit,
                'quantity' => $qty,
                'line_total' => $lineTotal,
            ];
        }

        if (empty($lines)) {
            throw ValidationException::withMessages(['items' => ['Your cart is empty.']]);
        }

        // Coupon
        $coupon = null;
        $discount = 0;
        if (! empty($data['coupon'])) {
            $coupon = Coupon::whereRaw('UPPER(code) = ?', [strtoupper(trim($data['coupon']))])->first();
            if ($coupon && $coupon->isValid()) {
                $discount = $coupon->discountFor($subtotal);
            } else {
                $coupon = null;
            }
        }

        $total = round($subtotal - $discount, 2);

        return DB::transaction(function () use ($user, $data, $lines, $subtotal, $discount, $total, $coupon) {
            $order = Order::create([
                'order_number' => $this->newOrderNumber(),
                'user_id' => $user->id,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'currency' => 'USD',
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'payment_gateway' => $data['gateway'] ?? null,
                'billing' => $data['billing'] ?? null,
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            $order->payments()->create([
                'gateway' => $data['gateway'] ?? 'manual',
                'status' => 'pending',
                'amount' => $total,
                'currency' => 'USD',
            ]);

            // Issue the invoice immediately (marked UNPAID) — refreshed to PAID on fulfilment.
            app(FulfillmentService::class)->generateInvoice($order->load('items', 'user'));

            return $order->load('items', 'invoice');
        });
    }

    /** Idempotent: transition an order to paid and fulfil it once. */
    public function markPaid(Order $order, ?array $gatewayMeta = null): Order
    {
        if ($order->isPaid()) {
            return $order;
        }

        DB::transaction(function () use ($order, $gatewayMeta) {
            $order->update(['status' => 'paid', 'paid_at' => now()]);
            $payment = $order->payments()->latest()->first();
            $payment?->update([
                'status' => 'succeeded',
                'gateway_payment_id' => $gatewayMeta['payment_id'] ?? $payment->gateway_payment_id,
                'payload' => $gatewayMeta['payload'] ?? $payment->payload,
            ]);
        });

        app(FulfillmentService::class)->fulfill($order->fresh('items'));

        return $order->fresh();
    }

    /**
     * RS-{2-digit year}{5-digit serial}, drawn from the serial shared with CRM invoices
     * (so orders and invoices form one continuous sequence). The order's invoice reuses this.
     */
    private function newOrderNumber(): string
    {
        return \App\Support\InvoiceSerial::next();
    }
}
