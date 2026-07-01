<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;

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
}
