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
