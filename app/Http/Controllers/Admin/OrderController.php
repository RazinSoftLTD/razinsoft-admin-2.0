<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\FulfillmentService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('user')->latest()->paginate(15);

        return view('admin.orders.index', compact('orders'));
    }

    public function create()
    {
        $customers = User::orderBy('name')->get(['id', 'name', 'email']);
        $products = Product::with('plans:id,product_id,name,price')->orderBy('name')->get(['id', 'name', 'price']);

        return view('admin.orders.create', compact('customers', 'products'));
    }

    public function store(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.plan_id' => ['nullable', 'integer'],
            'items.*.qty' => ['nullable', 'integer', 'min:1'],
            'coupon' => ['nullable', 'string', 'max:50'],
            'mark_paid' => ['boolean'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $items = collect($data['items'])->map(fn ($i) => [
            'product_id' => (int) $i['product_id'],
            'plan_id' => ! empty($i['plan_id']) ? (int) $i['plan_id'] : null,
            'qty' => max(1, (int) ($i['qty'] ?? 1)),
        ])->all();

        $order = $orders->createFromCheckout($user, [
            'items' => $items,
            'coupon' => $data['coupon'] ?? null,
            'gateway' => 'manual',
            'billing' => ['first_name' => $user->name, 'email' => $user->email],
        ]);

        if ($request->boolean('mark_paid')) {
            $order = $orders->markPaid($order, ['payment_id' => 'manual_'.uniqid(), 'payload' => ['gateway' => 'manual', 'by' => $request->user()->email]]);
        }

        return redirect()->route('admin.orders.show', $order)->with('status', "Order {$order->order_number} created.");
    }

    public function show(Order $order)
    {
        $order->load('user', 'items.license', 'items.product', 'invoice', 'payments');

        return view('admin.orders.show', compact('order'));
    }

    /** Stream the order's invoice PDF (self-heals: generates it if missing). */
    public function downloadInvoice(Order $order, FulfillmentService $fulfillment)
    {
        $invoice = $order->invoice;
        if (! $invoice || ! $invoice->pdf_path || ! Storage::disk('local')->exists($invoice->pdf_path)) {
            $invoice = $fulfillment->generateInvoice($order->load('items', 'user'));
        }

        abort_unless($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path), 404, 'Invoice file not available.');

        return Storage::disk('local')->download($invoice->pdf_path, "{$invoice->invoice_number}.pdf");
    }

    /** Stream a license certificate for one of the order's items. */
    public function downloadLicense(Order $order, License $license)
    {
        abort_unless($license->orderItem && $license->orderItem->order_id === $order->id, 404);
        abort_unless($license->file_path && Storage::disk('local')->exists($license->file_path), 404, 'License file not available yet — it is issued once the order is paid.');

        $ext = pathinfo($license->file_path, PATHINFO_EXTENSION) ?: 'pdf';

        return Storage::disk('local')->download($license->file_path, "{$license->license_key}.{$ext}");
    }
}
