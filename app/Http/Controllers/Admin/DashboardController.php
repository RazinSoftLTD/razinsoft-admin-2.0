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
        // Employees (non-admin staff) get a personal self-service dashboard — their own info only.
        $me = auth()->user();
        if ($me && $me->isStaff() && ! $me->isAdmin()) {
            return $this->employeeDashboard($me);
        }

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

    /** Personal dashboard for an employee — only their own information. */
    private function employeeDashboard(User $me)
    {
        $me->loadMissing('designation', 'department', 'reportsTo');

        $assignedTickets = \App\Models\Ticket::where('assigned_to', $me->id)
            ->with('client')
            ->latest('last_reply_at')->latest('id')
            ->take(6)->get();

        $ticketStats = [
            'open' => \App\Models\Ticket::where('assigned_to', $me->id)->where('status', 'open')->count(),
            'pending' => \App\Models\Ticket::where('assigned_to', $me->id)->where('status', 'pending')->count(),
            'total' => \App\Models\Ticket::where('assigned_to', $me->id)->count(),
        ];

        // Upcoming birthdays across the team (next occurrence).
        $birthdays = User::assignable()->whereNotNull('date_of_birth')->with('designation')->get()
            ->map(function ($u) {
                $next = Carbon::parse($u->date_of_birth)->setYear((int) now()->year);
                if ($next->isPast()) {
                    $next->addYear();
                }
                $u->next_birthday = $next;

                return $u;
            })
            ->sortBy('next_birthday')->take(5)->values();

        return view('admin.dashboard-employee', compact('me', 'assignedTickets', 'ticketStats', 'birthdays'));
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
