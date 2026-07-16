<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Activity → Client: marketing-style report of website visitors.
 * The list shows ONE row per visitor (their latest visit); the details page
 * holds their full history. Plus top-pages and top-countries reports.
 */
class ClientActivityLogController extends Controller
{
    /** One visitor = a logged-in client, or (for unknowns) an IP address. */
    private const VISITOR_KEY = "COALESCE(CAST(client_id AS CHAR(20)), ip)";

    public function index(Request $request)
    {
        $base = ClientActivityLog::query();
        $this->applyDates($base, $request);

        // ---- Headline stats ----
        $totalVisits = (clone $base)->count();
        $uniqueVisitors = (int) (clone $base)->selectRaw('COUNT(DISTINCT '.self::VISITOR_KEY.') as c')->value('c');
        $knownClients = (clone $base)->whereNotNull('client_id')->distinct()->count('client_id');
        $topCountry = (clone $base)->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as visits')->groupBy('country')->orderByDesc('visits')->first();

        // ---- Top pages (which screens/blogs/products get the most visits) ----
        $topPages = (clone $base)
            ->selectRaw('path, MAX(title) as title, COUNT(*) as visits, COUNT(DISTINCT '.self::VISITOR_KEY.') as visitors')
            ->groupBy('path')->orderByDesc('visits')->limit(10)->get();

        // ---- Top countries ----
        $topCountries = (clone $base)->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as visits, COUNT(DISTINCT '.self::VISITOR_KEY.') as visitors')
            ->groupBy('country')->orderByDesc('visitors')->limit(10)->get();

        // ---- Visitors, deduped: latest visit per visitor + their total count ----
        $visitors = (clone $base)
            ->selectRaw(self::VISITOR_KEY.' as vkey, MAX(id) as last_id, COUNT(*) as visits, MIN(created_at) as first_visit')
            ->groupBy('vkey')->orderByDesc('last_id')
            ->paginate(20)->withQueryString();
        $lastRows = ClientActivityLog::with('client:id,name,email,photo')
            ->whereIn('id', $visitors->pluck('last_id'))->get()->keyBy('id');

        return view('admin.client-activity.index', [
            'totalVisits' => $totalVisits,
            'uniqueVisitors' => $uniqueVisitors,
            'knownClients' => $knownClients,
            'topCountry' => $topCountry,
            'topPages' => $topPages,
            'topCountries' => $topCountries,
            'visitors' => $visitors,
            'lastRows' => $lastRows,
        ]);
    }

    /** Content sections reported on their own pages (Blogs / Products). */
    private const CONTENT = [
        'blogs' => ['label' => 'Blogs', 'prefix' => '/blog/', 'noun' => 'blog post', 'hint' => 'Which blog posts are most popular, who reads them, and from where.'],
        'products' => ['label' => 'Products', 'prefix' => '/products/', 'noun' => 'product', 'hint' => 'Which products get the most attention, who views them, and from where.'],
    ];

    /** Blogs / Products popularity report (views, unique visitors, clients, countries). */
    public function content(Request $request, string $type)
    {
        abort_unless(isset(self::CONTENT[$type]), 404);
        $cfg = self::CONTENT[$type];

        $base = ClientActivityLog::query()->where('path', 'like', $cfg['prefix'].'%');
        $this->applyDates($base, $request);

        // Headline stats for the section.
        $totalViews = (clone $base)->count();
        $uniqueVisitors = (int) (clone $base)->selectRaw('COUNT(DISTINCT '.self::VISITOR_KEY.') as c')->value('c');
        $knownClients = (clone $base)->whereNotNull('client_id')->distinct()->count('client_id');
        $topCountry = (clone $base)->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as views')->groupBy('country')->orderByDesc('views')->first();

        // Per-item popularity (views · unique visitors · logged-in clients).
        $items = (clone $base)
            ->selectRaw('path, MAX(title) as title, COUNT(*) as views, COUNT(DISTINCT '.self::VISITOR_KEY.') as visitors, COUNT(DISTINCT client_id) as clients')
            ->groupBy('path')->orderByDesc('views')
            ->paginate(15)->withQueryString();

        // Top country per listed item.
        $countryPerItem = (clone $base)->whereNotNull('country')
            ->whereIn('path', collect($items->items())->pluck('path'))
            ->selectRaw('path, country, COUNT(*) as views')
            ->groupBy('path', 'country')->orderByDesc('views')->get()
            ->groupBy('path')->map(fn ($rows) => $rows->first());

        // WHICH clients viewed each listed item (so the Clients count can expand to a list).
        $clientsPerItem = (clone $base)->whereNotNull('client_id')
            ->whereIn('path', collect($items->items())->pluck('path'))
            ->selectRaw('path, client_id, COUNT(*) as views, MAX(created_at) as last_visit')
            ->groupBy('path', 'client_id')->orderByDesc('views')->get()
            ->groupBy('path');
        $clientMap = \App\Models\User::withTrashed()
            ->whereIn('id', $clientsPerItem->flatten(1)->pluck('client_id')->unique())
            ->get(['id', 'name', 'email', 'photo'])->keyBy('id');

        // Country breakdown for the whole section.
        $topCountries = (clone $base)->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as views, COUNT(DISTINCT '.self::VISITOR_KEY.') as visitors')
            ->groupBy('country')->orderByDesc('visitors')->limit(10)->get();

        return view('admin.client-activity.content', [
            'type' => $type,
            'cfg' => $cfg,
            'totalViews' => $totalViews,
            'uniqueVisitors' => $uniqueVisitors,
            'knownClients' => $knownClients,
            'topCountry' => $topCountry,
            'items' => $items,
            'countryPerItem' => $countryPerItem,
            'clientsPerItem' => $clientsPerItem,
            'clientMap' => $clientMap,
            'topCountries' => $topCountries,
        ]);
    }

    /** Full history for one visitor — a client (by id) or an unknown visitor (by ip). */
    public function details(Request $request)
    {
        $clientId = $request->query('client');
        $ip = $request->query('ip');
        abort_unless($clientId || $ip, 404);

        $scope = fn () => ClientActivityLog::query()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when(! $clientId, fn ($q) => $q->whereNull('client_id')->where('ip', $ip));

        $client = $clientId ? User::withTrashed()->find($clientId) : null;
        abort_if($clientId && ! $client, 404);

        $total = $scope()->count();
        abort_if($total === 0, 404);

        return view('admin.client-activity.details', [
            'client' => $client,
            'ip' => $ip,
            'total' => $total,
            'firstSeen' => $scope()->min('created_at'),
            'lastSeen' => $scope()->max('created_at'),
            'country' => $scope()->whereNotNull('country')->latest('id')->value('country'),
            'topPages' => $scope()->selectRaw('path, MAX(title) as title, COUNT(*) as visits')
                ->groupBy('path')->orderByDesc('visits')->limit(10)->get(),
            'timeline' => $scope()->latest('id')->paginate(30)->withQueryString(),
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
