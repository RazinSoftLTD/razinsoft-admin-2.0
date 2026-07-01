<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $paid = fn () => Order::whereIn('status', ['paid', 'processing', 'completed']);

        // ---- KPI cards (with month-over-month change) ----
        $revenue = (float) $paid()->sum('total');
        $kpis = [
            'revenue' => ['value' => $revenue, 'change' => $this->change($paid()->getQuery(), 'total')],
            'orders' => ['value' => Order::count(), 'change' => $this->change(Order::query(), null)],
            'customers' => ['value' => User::where('role', 'customer')->count(), 'change' => $this->change(User::where('role', 'customer'), null)],
            'products' => ['value' => Product::count(), 'change' => $this->change(Product::query(), null)],
        ];

        // ---- 12-month revenue + orders series ----
        $months = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $rev = $paid()->where('created_at', '>=', $months->first())->get(['total', 'created_at'])
            ->groupBy(fn ($o) => $o->created_at->format('Y-m'));
        $ord = Order::where('created_at', '>=', $months->first())->get(['id', 'created_at'])
            ->groupBy(fn ($o) => $o->created_at->format('Y-m'));

        $series = [
            'labels' => $months->map(fn (Carbon $m) => $m->format('M'))->all(),
            'revenue' => $months->map(fn (Carbon $m) => round((float) optional($rev->get($m->format('Y-m')))->sum('total'), 2))->all(),
            'orders' => $months->map(fn (Carbon $m) => (int) optional($ord->get($m->format('Y-m')))->count())->all(),
        ];

        // ---- Orders by status (donut) ----
        $statuses = Order::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        // ---- Top products by revenue ----
        $topProducts = OrderItem::selectRaw('product_name, SUM(line_total) as revenue, SUM(quantity) as qty')
            ->groupBy('product_name')->orderByDesc('revenue')->take(5)->get();

        // ---- Active stat boxes ----
        $active = [
            'today' => Order::whereDate('created_at', today())->count(),
            'week' => Order::where('created_at', '>=', now()->subWeek())->count(),
            'month' => Order::where('created_at', '>=', now()->startOfMonth())->count(),
            'pending' => Order::where('status', 'pending')->count(),
        ];

        $recentOrders = Order::with('user', 'items')->latest()->take(7)->get();

        return view('admin.dashboard', compact('kpis', 'series', 'statuses', 'topProducts', 'active', 'recentOrders'));
    }

    /** Percentage change of this month vs last month (count, or sum of $column). */
    private function change($query, ?string $column): float
    {
        $base = clone $query;
        $thisM = (float) (clone $base)->whereBetween('created_at', [now()->startOfMonth(), now()])->when($column, fn ($q) => $q->sum($column), fn ($q) => $q->count());
        $lastM = (float) (clone $base)->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->when($column, fn ($q) => $q->sum($column), fn ($q) => $q->count());

        if ($lastM <= 0) {
            return $thisM > 0 ? 100.0 : 0.0;
        }

        return round((($thisM - $lastM) / $lastM) * 100, 1);
    }
}
