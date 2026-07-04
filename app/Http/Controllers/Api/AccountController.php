<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class AccountController extends Controller
{
    /** Dashboard stats + recent orders + owned licenses. */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $orders = $user->orders()->with('items', 'invoice')->latest()->get();
        $paid = $orders->filter->isPaid();
        $licenses = License::where('user_id', $user->id)->with('product:id,name,slug')->latest()->get();

        return response()->json([
            'user' => ['name' => $user->name, 'email' => $user->email],
            'stats' => [
                'total_orders' => $orders->count(),
                'completed_orders' => $orders->where('status', 'completed')->count(),
                'total_spent' => round((float) $paid->sum('total'), 2),
                'active_licenses' => $licenses->where('status', 'active')->count(),
                'products_owned' => $licenses->pluck('product_id')->unique()->count(),
            ],
            'recent_orders' => $orders->take(5)->map(fn ($o) => $this->orderSummary($o))->values(),
            'licenses' => $licenses->map(fn ($l) => $this->licensePayload($l))->values(),
        ]);
    }

    /** Authenticated user's orders. */
    public function orders(Request $request)
    {
        $orders = $request->user()->orders()->with('items', 'invoice')->latest()->paginate(15);

        return response()->json([
            'data' => collect($orders->items())->map(fn ($o) => $this->orderSummary($o))->values(),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
        ]);
    }

    /** The logged-in client's CRM invoices with current due + public pay link. */
    public function invoices(Request $request)
    {
        $invoices = \App\Models\ClientInvoice::where('client_id', $request->user()->id)
            ->withCount('items')->latest('id')->get();

        return response()->json([
            'data' => $invoices->map(fn ($inv) => [
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => $inv->invoice_date?->toDateString(),
                'due_date' => $inv->due_date?->toDateString(),
                'currency' => $inv->currency,
                'total' => (float) $inv->total,
                'amount_paid' => (float) $inv->amount_paid,
                'amount_due' => $inv->amountDue(),
                'status' => $inv->status,
                'status_label' => \App\Models\ClientInvoice::STATUSES[$inv->status] ?? $inv->status,
                'items_count' => $inv->items_count,
                'pay_url' => route('pay.invoice.show', $inv->public_token),
            ])->values(),
        ]);
    }

    /** Order detail (by order_number) — owner only. */
    public function order(Request $request, string $orderNumber)
    {
        $order = $request->user()->orders()
            ->where('order_number', $orderNumber)
            ->with(['items.license', 'items.product:id,name,slug', 'invoice'])
            ->firstOrFail();

        // Self-heal: older orders may predate auto-invoicing — issue one now (marked per paid state).
        if (! $order->invoice) {
            app(\App\Services\FulfillmentService::class)->generateInvoice($order->load('items', 'user'));
            $order->load('invoice');
        }

        return response()->json(['data' => $this->orderDetail($order)]);
    }

    /** Stream the invoice PDF — owner only. */
    public function downloadInvoice(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->order->user_id === $request->user()->id, 403);
        abort_unless($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path), 404, 'Invoice file not available.');

        return Storage::disk('local')->download($invoice->pdf_path, "{$invoice->invoice_number}.pdf");
    }

    /** Stream the license certificate — owner only, and only once the order is paid. */
    public function downloadLicense(Request $request, License $license)
    {
        abort_unless($license->user_id === $request->user()->id, 403);
        abort_unless(optional($license->orderItem?->order)->isPaid(), 403, 'License is available once your payment is completed.');
        abort_unless($license->file_path && Storage::disk('local')->exists($license->file_path), 404, 'License file not available.');

        $ext = pathinfo($license->file_path, PATHINFO_EXTENSION) ?: 'pdf';

        return Storage::disk('local')->download($license->file_path, "{$license->license_key}.{$ext}");
    }

    /**
     * Gated source-code download. Reached via a temporary signed URL (30 min) AND auth:sanctum.
     * Serves the product's latest source zip only if the user owns a paid/completed order for it.
     */
    public function downloadSource(Request $request, Product $product)
    {
        abort_unless($this->ownsProduct($request->user()->id, $product->id), 403, 'You do not own a license for this product.');

        $file = $product->latestFile()->first() ?? $product->files()->first();
        abort_unless($file && $file->file_path && Storage::disk('local')->exists($file->file_path), 404, 'Source code is not available yet.');

        $name = $product->slug.'-'.$file->version.'.zip';

        return Storage::disk('local')->download($file->file_path, $name);
    }

    // ---- helpers ----

    private function ownsProduct(int $userId, int $productId): bool
    {
        return Order::where('user_id', $userId)
            ->whereIn('status', ['paid', 'processing', 'completed'])
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->exists();
    }

    private function sourceUrl(int $userId, int $productId): ?string
    {
        if (! $this->ownsProduct($userId, $productId)) {
            return null;
        }

        return URL::temporarySignedRoute('account.source.download', now()->addMinutes(30), ['product' => $productId]);
    }

    private function orderSummary(Order $o): array
    {
        return [
            'order_number' => $o->order_number,
            'status' => $o->status,
            'total' => (float) $o->total,
            'currency' => $o->currency,
            'item_count' => $o->items->count(),
            'products' => $o->items->pluck('product_name')->values(),
            'paid_at' => $o->paid_at?->toIso8601String(),
            'created_at' => $o->created_at?->toIso8601String(),
            'invoice_url' => $o->relationLoaded('invoice') && $o->invoice ? route('account.invoice.download', $o->invoice->id) : null,
        ];
    }

    private function orderDetail(Order $o): array
    {
        // The buyer's own reviews for the products in this order (to show "your rating" inline).
        $myReviews = \App\Models\Review::where('user_id', $o->user_id)
            ->whereIn('product_id', $o->items->pluck('product_id')->filter()->unique())
            ->get()
            ->keyBy('product_id');

        return [
            'order_number' => $o->order_number,
            'status' => $o->status,
            'subtotal' => (float) $o->subtotal,
            'discount' => (float) $o->discount,
            'total' => (float) $o->total,
            'currency' => $o->currency,
            'coupon_code' => $o->coupon_code,
            'payment_gateway' => $o->payment_gateway,
            'billing' => $o->billing,
            'paid_at' => $o->paid_at?->toIso8601String(),
            'created_at' => $o->created_at?->toIso8601String(),
            'invoice' => $o->invoice ? [
                'invoice_number' => $o->invoice->invoice_number,
                'download_url' => route('account.invoice.download', $o->invoice->id),
            ] : null,
            'items' => $o->items->map(function ($i) use ($o, $myReviews) {
                $paid = $o->isPaid();
                $review = $myReviews->get($i->product_id);

                return [
                    'product_name' => $i->product_name,
                    'product_slug' => $i->product?->slug,
                    'plan_name' => $i->plan_name,
                    'unit_price' => (float) $i->unit_price,
                    'quantity' => $i->quantity,
                    'line_total' => (float) $i->line_total,
                    // License key + downloads are unlocked only after the order is paid.
                    'license' => $i->license ? [
                        'license_key' => $paid ? $i->license->license_key : null,
                        'status' => $i->license->status,
                        'download_url' => $paid ? route('account.license.download', $i->license->id) : null,
                    ] : null,
                    'source_download_url' => $paid ? $this->sourceUrl($o->user_id, $i->product_id) : null,
                    // Reviews can only be left from here (paid order), product-wise.
                    'can_review' => $paid && $i->product,
                    'my_review' => $review ? [
                        'rating' => (int) $review->rating,
                        'comment' => $review->comment,
                        'is_approved' => (bool) $review->is_approved,
                    ] : null,
                ];
            })->values(),
        ];
    }

    private function licensePayload(License $l): array
    {
        return [
            'id' => $l->id,
            'license_key' => $l->license_key,
            'plan_name' => $l->plan_name,
            'status' => $l->status,
            'issued_at' => $l->issued_at?->toIso8601String(),
            'product' => $l->product ? ['name' => $l->product->name, 'slug' => $l->product->slug] : null,
            'license_download_url' => route('account.license.download', $l->id),
            'source_download_url' => $this->sourceUrl($l->user_id, $l->product_id),
        ];
    }
}
