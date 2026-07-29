<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CartEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Activity → Cart: who put products in their cart on the website.
 *
 * The cart is browser-side, so these rows come from the add-to-cart beacon (POST /api/track/cart).
 * One row per person — a signed-in client, or an anonymous visitor keyed by IP — because the
 * question this answers is "who showed buying intent", not "how many adds happened".
 */
class CartActivityController extends Controller
{
    /** One shopper = a signed-in client, or (for anonymous visitors) an IP address. */
    private const SHOPPER_KEY = "COALESCE(CAST(client_id AS CHAR(20)), ip)";

    public function index(Request $request)
    {
        $base = CartEvent::query();
        $this->applyDates($base, $request);

        $shoppers = (clone $base)
            ->selectRaw(self::SHOPPER_KEY.' as skey, MAX(id) as last_id, MAX(client_id) as client_id, COUNT(*) as adds, SUM(qty) as items, COUNT(DISTINCT product_name) as products, MAX(created_at) as last_added, MIN(created_at) as first_added')
            ->groupBy('skey')->orderByDesc('last_added')
            ->paginate(20)->withQueryString();

        // The latest add per shopper — what they were last interested in.
        $lastRows = CartEvent::whereIn('id', $shoppers->pluck('last_id'))->get()->keyBy('id');

        $clientIds = $shoppers->pluck('client_id')->filter();

        return view('admin.cart-activity.index', [
            'shoppers' => $shoppers,
            'lastRows' => $lastRows,
            'clients' => User::withTrashed()->whereIn('id', $clientIds)->get(['id', 'name', 'email', 'photo'])->keyBy('id'),
            // Did they go on to order? That is what turns this list into a follow-up list.
            'ordered' => Order::whereIn('user_id', $clientIds)->distinct()->pluck('user_id')->flip(),
            'totalAdds' => (clone $base)->count(),
            'totalShoppers' => (int) (clone $base)->selectRaw('COUNT(DISTINCT '.self::SHOPPER_KEY.') as c')->value('c'),
            'topProducts' => (clone $base)->selectRaw('product_name, COUNT(*) as adds, COUNT(DISTINCT '.self::SHOPPER_KEY.') as shoppers')
                ->groupBy('product_name')->orderByDesc('adds')->limit(10)->get(),
        ]);
    }

    private function applyDates($q, Request $request): void
    {
        match ($request->query('date_range')) {
            'today' => $q->whereDate('created_at', today()),
            'week' => $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $q->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            default => null,
        };
        if ($from = $request->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }
    }
}
