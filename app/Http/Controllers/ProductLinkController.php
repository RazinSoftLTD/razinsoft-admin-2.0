<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * The public face of a product sales link.
 *
 * There is no catalogue any more. An operator creates a product in the panel, copies its link, and
 * sends it to whoever is buying. That token is the entire authorisation — the same arrangement as
 * an invoice pay-link, and it carries the same obligations:
 *
 *   - unpublished products 404, so a half-written draft cannot be reached by guessing
 *   - the buyer is never asked for a password; an account is created for them silently, and they
 *     set a password later through the normal reset flow if they want the dashboard
 *   - nothing here trusts a price from the request. The plan id is looked up against this product
 *     and the amount comes from the database.
 */
class ProductLinkController extends Controller
{
    /** Backend hit of the share URL — bounce to the page, which lives on the frontend. */
    public function show(string $token)
    {
        $product = Product::published()->where('public_token', $token)->firstOrFail();

        return redirect()->away(rtrim((string) config('services.frontend_url'), '/').'/p/'.$product->public_token);
    }

    /** What the frontend page renders. Deliberately narrow: no internal ids beyond what buying needs. */
    public function payload(string $token): JsonResponse
    {
        $product = Product::published()
            ->with(['plans' => fn ($q) => $q->orderBy('price')])
            ->where('public_token', $token)
            ->firstOrFail();

        return response()->json([
            'name' => $product->name,
            'tagline' => $product->tagline,
            'summary' => $product->tagline,
            'image' => ProductResource::media($product->thumbnail),
            'currency' => $product->currency ?: 'USD',
            'price' => (float) $product->price,
            'ext_price' => $product->ext_price !== null ? (float) $product->ext_price : null,
            'plans' => $product->plans->map(fn (Plan $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'blurb' => $p->blurb,
            ])->values(),
        ]);
    }

    /**
     * Buy it.
     *
     * Creates the customer if this is their first time, raises the order through the same service
     * the panel uses, and hands back a gateway URL for the browser to follow.
     */
    public function order(Request $request, string $token, OrderService $orders, PaymentService $payments): JsonResponse
    {
        $product = Product::published()->where('public_token', $token)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:120'],
            // Checked against this product, not taken on trust: a plan id from another product
            // would otherwise let someone buy at somebody else's price.
            'plan_id' => ['nullable', Rule::exists('plans', 'id')->where('product_id', $product->id)],
            'license_type' => ['nullable', Rule::in(['regular', 'extended'])],
            'gateway' => ['required', Rule::in(['stripe', 'paypal'])],
        ]);

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                // Unguessable. They never see it — the dashboard is reached by password reset.
                'password' => Hash::make(str()->random(40)),
                'role' => User::ROLE_CUSTOMER,
                'status' => 'active',
                'phone' => $data['phone'] ?? null,
                'company' => $data['company'] ?? null,
            ],
        );

        $order = $orders->createFromCheckout($user, [
            'items' => [[
                'product_id' => $product->id,
                'plan_id' => $data['plan_id'] ?? null,
                'license_type' => $data['license_type'] ?? 'regular',
                'qty' => 1,
            ]],
            'billing' => [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'company' => $data['company'] ?? null,
            ],
        ]);

        $payment = $payments->initiate($order, $data['gateway']);

        return response()->json([
            'order_number' => $order->order_number,
        ] + $payment);
    }
}
