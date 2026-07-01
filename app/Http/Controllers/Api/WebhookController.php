<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Stripe\Webhook;

class WebhookController extends Controller
{
    public function stripe(Request $request, OrderService $orders)
    {
        $secret = config('services.stripe.webhook_secret');
        $payload = $request->getContent();

        try {
            $event = $secret
                ? Webhook::constructEvent($payload, $request->header('Stripe-Signature', ''), $secret)
                : json_decode($payload); // dev without signing secret
        } catch (\Throwable $e) {
            return response()->json(['error' => 'invalid signature'], 400);
        }

        if (($event->type ?? null) === 'checkout.session.completed') {
            $session = $event->data->object ?? null;
            $orderNumber = $session->client_reference_id ?? ($session->metadata->order_number ?? null);
            if ($order = Order::where('order_number', $orderNumber)->first()) {
                $orders->markPaid($order, ['payment_id' => $session->payment_intent ?? null, 'payload' => ['gateway' => 'stripe']]);
            }
        }

        return response()->json(['received' => true]);
    }

    public function paypal(Request $request, OrderService $orders)
    {
        $event = $request->all();
        $type = $event['event_type'] ?? null;

        if (in_array($type, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'], true)) {
            $ref = data_get($event, 'resource.purchase_units.0.reference_id')
                ?? data_get($event, 'resource.supplementary_data.related_ids.order_id');
            if ($ref && ($order = Order::where('order_number', $ref)->first())) {
                $orders->markPaid($order, ['payment_id' => data_get($event, 'resource.id'), 'payload' => ['gateway' => 'paypal']]);
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * Local-only: simulate a successful gateway payment, then redirect to the frontend
     * success page (so it works inside the checkout iframe). Pass ?json=1 for a JSON
     * response instead (used by tests / curl).
     */
    public function devPay(Request $request, string $orderNumber, OrderService $orders)
    {
        abort_unless(app()->environment('local'), 404);

        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $orders->markPaid($order, ['payment_id' => 'dev_'.uniqid(), 'payload' => ['gateway' => 'dev']]);

        if ($request->boolean('json')) {
            return response()->json([
                'message' => 'Payment simulated. Order marked paid & fulfilled.',
                'order_number' => $order->order_number,
                'status' => $order->fresh()->status,
            ]);
        }

        $frontend = rtrim((string) config('services.frontend_url'), '/');

        return redirect()->away("{$frontend}/payment/success?order={$order->order_number}");
    }
}
