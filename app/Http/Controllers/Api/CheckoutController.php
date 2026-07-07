<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function store(Request $request, OrderService $orders, PaymentService $payments)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.slug' => ['required_without:items.*.product_id', 'string'],
            'items.*.product_id' => ['sometimes', 'integer'],
            'items.*.plan_id' => ['nullable', 'integer'],
            'items.*.qty' => ['nullable', 'integer', 'min:1'],
            'items.*.license_type' => ['nullable', 'in:regular,extended'],
            'coupon' => ['nullable', 'string', 'max:50'],
            'gateway' => ['required', 'in:stripe,paypal'],
            'billing' => ['nullable', 'array'],
            'billing.email' => ['nullable', 'email'],
        ]);

        $order = $orders->createFromCheckout($request->user(), $data);
        $payment = $payments->initiate($order, $data['gateway']);

        return response()->json(array_merge([
            'order_number' => $order->order_number,
            'total' => (float) $order->total,
            'currency' => $order->currency,
        ], $payment), 201);
    }

    /** Re-issue payment for an existing pending order (cancel → re-pay flow). */
    public function repay(Request $request, PaymentService $payments, string $orderNumber)
    {
        $order = $request->user()->orders()->where('order_number', $orderNumber)->firstOrFail();

        if ($order->isPaid()) {
            return response()->json(['status' => 'paid', 'message' => 'This order is already paid.'], 409);
        }

        $payment = $payments->initiate($order, $order->payment_gateway ?: 'stripe');

        return response()->json(array_merge([
            'order_number' => $order->order_number,
            'total' => (float) $order->total,
            'currency' => $order->currency,
        ], $payment));
    }

    /**
     * Confirm a returned payment and fulfil the order (fallback to the webhook). Called by the
     * success page: Stripe verifies the session, PayPal captures the approved order.
     */
    public function confirm(Request $request, OrderService $orders, PaymentService $payments, string $orderNumber)
    {
        $order = $request->user()->orders()->where('order_number', $orderNumber)->firstOrFail();

        if (! $order->isPaid()) {
            if ($order->payment_gateway === 'paypal') {
                // PayPal returns with ?token=<paypal-order-id>; capture it to take the money & fulfil.
                if ($payments->paypalCapture($order, $request->query('token'))) {
                    $orders->markPaid($order, ['payment_id' => $request->query('token'), 'payload' => ['gateway' => 'paypal']]);
                }
            } elseif ($payments->stripeSessionPaid($order, $request->query('session_id'))) {
                $orders->markPaid($order, ['payment_id' => $request->query('session_id'), 'payload' => ['gateway' => 'stripe']]);
            }
        }

        return response()->json([
            'order_number' => $order->order_number,
            'status' => $order->fresh()->status,
            'paid' => $order->fresh()->isPaid(),
        ]);
    }
}
