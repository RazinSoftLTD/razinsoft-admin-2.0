<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DomainOrder;
use App\Services\DomainOrderService;
use App\Services\ResellerClub;
use Illuminate\Http\Request;

/** Public domain search for the website's Cloud & Domains page. */
class DomainController extends Controller
{
    public function search(Request $request, ResellerClub $rc)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'tlds' => ['nullable', 'array', 'max:20'],
            'tlds.*' => ['string', 'max:20'],
        ]);

        $label = $rc->normaliseLabel($data['name']);

        if (strlen($label) < 2) {
            return response()->json([
                'configured' => $rc->configured(),
                'label' => $label,
                'results' => [],
                'message' => 'Enter at least two characters.',
            ], 422);
        }

        // Without credentials the site still has a domain page — it just asks people to get in
        // touch instead of showing a search that cannot answer.
        if (! $rc->configured()) {
            return response()->json([
                'configured' => false,
                'label' => $label,
                'results' => [],
            ]);
        }

        // Only TLDs we actually offer: an arbitrary list from the browser would have us quoting
        // extensions nobody has priced.
        $offered = $rc->tlds();
        $tlds = isset($data['tlds'])
            ? array_values(array_intersect(array_map(fn ($t) => ltrim($t, '.'), $data['tlds']), $offered))
            : $offered;

        $results = $rc->search($label, $tlds ?: $offered);

        return response()->json([
            'configured' => true,
            'label' => $label,
            'currency' => config('services.resellerclub.currency', 'USD'),
            // Available first, then by price, so the answer to "can I have it" is at the top.
            'results' => collect($results)
                ->sortBy([
                    fn ($a, $b) => ($b['available'] <=> $a['available']),
                    fn ($a, $b) => ($a['price'] ?? INF) <=> ($b['price'] ?? INF),
                ])
                ->values(),
        ]);
    }

    /** Register clicked: take the registrant's details, make the order, hand back the pay URL. */
    public function order(Request $request, DomainOrderService $orders)
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+\.[a-z.]{2,20}$/i'],
            'registrant.name' => ['required', 'string', 'max:150'],
            'registrant.company' => ['nullable', 'string', 'max:150'],
            'registrant.email' => ['required', 'email', 'max:150'],
            'registrant.phone' => ['required', 'string', 'max:32'],
            'registrant.address' => ['required', 'string', 'max:250'],
            'registrant.city' => ['required', 'string', 'max:100'],
            'registrant.country' => ['required', 'string', 'size:2'],
            'registrant.zip' => ['required', 'string', 'max:20'],
        ]);

        try {
            $order = $orders->create($request->user(), strtolower($data['domain']), $data['registrant']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(array_merge(
            $order->publicPayload(),
            $orders->initiatePayment($order),
        ), 201);
    }

    /** Success-page return: verify the payment actually happened, then register the name. */
    public function confirmOrder(Request $request, DomainOrderService $orders, string $orderNumber)
    {
        $order = DomainOrder::where('order_number', $orderNumber)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json($orders->confirm($order, $request->input('session_id'))->publicPayload());
    }

    public function showOrder(Request $request, string $orderNumber)
    {
        $order = DomainOrder::where('order_number', $orderNumber)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json($order->publicPayload());
    }

    /** The signed-in user's domains, for the dashboard. */
    public function myDomains(Request $request)
    {
        return response()->json([
            'domains' => DomainOrder::where('user_id', $request->user()->id)
                ->whereNotNull('paid_at')
                ->latest()
                ->get()
                ->map(fn ($o) => $o->publicPayload()),
        ]);
    }

    /** Local stand-in for the gateway: marks the order paid and runs the registration. */
    public function devPay(DomainOrderService $orders, string $orderNumber)
    {
        abort_unless(app()->environment('local'), 404);

        $order = DomainOrder::where('order_number', $orderNumber)->firstOrFail();
        $order->update(['status' => 'paid', 'paid_at' => $order->paid_at ?? now()]);
        $orders->register($order->fresh());

        return redirect(rtrim((string) config('services.frontend_url'), '/')."/cloud/domains?order={$order->order_number}&dev=1");
    }
}
