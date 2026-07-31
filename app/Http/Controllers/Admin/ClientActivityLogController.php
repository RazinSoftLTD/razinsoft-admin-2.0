<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        $base = ClientActivityLog::query()->whereNull('error_code'); // errors have their own report
        $this->applyDates($base, $request);

        // Error-page views in the same date window (the stat card links to the report).
        $errBase = ClientActivityLog::query()->whereNotNull('error_code');
        $this->applyDates($errBase, $request);
        $totalErrors = $errBase->count();

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
            'growth' => $growth = $this->growth($request),
            'visitorTrend' => $this->visitorTrend($growth),
            'totalErrors' => $totalErrors,
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
        abort_unless($request->user()->allows('activity', $type), 403); // activity.blogs / activity.products
        $cfg = self::CONTENT[$type];

        $base = ClientActivityLog::query()->whereNull('error_code')->where('path', 'like', $cfg['prefix'].'%');
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

    /** Error-page views report (404 & friends): which URLs fail, how often, for whom. */
    public function errors(Request $request)
    {
        $base = ClientActivityLog::query()->whereNotNull('error_code');
        $this->applyDates($base, $request);

        $totalErrors = (clone $base)->count();
        $affectedVisitors = (int) (clone $base)->selectRaw('COUNT(DISTINCT '.self::VISITOR_KEY.') as c')->value('c');

        // Breakdown by status code (404 vs 500 …).
        $byCode = (clone $base)->selectRaw('error_code, COUNT(*) as hits')
            ->groupBy('error_code')->orderByDesc('hits')->get();

        // Which URLs error the most.
        $pages = (clone $base)
            ->selectRaw('error_code, path, COUNT(*) as hits, COUNT(DISTINCT '.self::VISITOR_KEY.') as visitors, MAX(created_at) as last_seen, MAX(referrer) as sample_referrer')
            ->groupBy('error_code', 'path')->orderByDesc('hits')
            ->paginate(20)->withQueryString();

        // Recent individual error hits (who hit what).
        $recent = ClientActivityLog::query()->whereNotNull('error_code')->with('client:id,name,email,photo');
        $this->applyDates($recent, $request);
        $recent = $recent->latest('id')->limit(20)->get();

        return view('admin.client-activity.errors', [
            'totalErrors' => $totalErrors,
            'affectedVisitors' => $affectedVisitors,
            'byCode' => $byCode,
            'pages' => $pages,
            'recent' => $recent,
        ]);
    }

    /**
     * Every signed-in client in the window, one row each — what the "Logged-in Clients" stat counts.
     * The main Visitors table mixes them in with anonymous IPs, so this is the list you reach for
     * when the question is "who has actually logged in?". Each row opens that client's full history.
     */
    public function clients(Request $request)
    {
        $base = ClientActivityLog::query()->whereNull('error_code')->whereNotNull('client_id');
        $this->applyDates($base, $request);

        $clients = (clone $base)
            ->selectRaw('client_id, COUNT(*) as visits, MAX(id) as last_id, MAX(created_at) as last_seen, MIN(created_at) as first_seen')
            ->groupBy('client_id')->orderByDesc('last_seen')
            ->paginate(20)->withQueryString();

        // The latest row per client carries the page/country to show alongside their totals.
        $lastRows = ClientActivityLog::whereIn('id', $clients->pluck('last_id'))->get()->keyBy('id');

        // Who is signed in *right now*: clients log in through the API, so a live Sanctum token is
        // the session. Without this the list only says who visited, not who is still in.
        $ids = $clients->pluck('client_id');
        $activeSessions = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)->whereIn('tokenable_id', $ids)
            ->selectRaw('tokenable_id, COUNT(*) as sessions, MAX(last_used_at) as last_used')
            ->groupBy('tokenable_id')->get()->keyBy('tokenable_id');

        return view('admin.client-activity.clients', [
            'clients' => $clients,
            'lastRows' => $lastRows,
            'activeSessions' => $activeSessions,
            'canSignOut' => (bool) $request->user()?->isSuperAdmin(),
            'clientUsers' => User::withTrashed()
                ->whereIn('id', $ids)
                ->get(['id', 'name', 'email', 'photo'])->keyBy('id'),
            'totalClients' => (int) (clone $base)->distinct()->count('client_id'),
            'totalLogins' => (clone $base)->count(),
        ]);
    }

    /**
     * Sign a client out of the website everywhere, from the Logged-in Clients list.
     *
     * Clients hold Sanctum tokens (the Nuxt site logs in through the API), so revoking those is what
     * actually ends the session; the sessions row is cleared too in case they also have a cookie
     * session. Super admins only — this kicks a paying customer out mid-visit.
     */
    public function logoutClient(Request $request, User $client)
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only a super admin can sign a client out.');

        $tokens = $client->tokens()->count();
        $client->tokens()->delete();

        // Session rows are only touched when the table is the session store (it is: SESSION_DRIVER=database).
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $client->id)->delete();
        }

        return back()->with('status', $tokens
            ? "{$client->name} has been signed out ({$tokens} session(s) ended)."
            : "{$client->name} had no active session — nothing to sign out.");
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

    /**
     * How the client base moved: how many joined and how many were removed, per day, month or year.
     *
     * Grouped by DATE() in SQL and rolled up in PHP rather than with DATE_FORMAT/strftime, because
     * those differ between MySQL and SQLite and this has to work on both. Client rows number in the
     * hundreds, so the rollup costs nothing.
     */
    private function growth(Request $request): array
    {
        $view = in_array($request->query('growth'), ['daily', 'monthly', 'yearly'], true)
            ? $request->query('growth') : 'daily';

        $year = (int) ($request->query('gyear') ?: now()->year);
        $month = (int) ($request->query('gmonth') ?: now()->month);

        $dateCounts = function (string $column) {
            return User::clients()->withTrashed()
                ->whereNotNull($column)
                ->selectRaw("DATE({$column}) as d, COUNT(*) as c")
                ->groupBy('d')->pluck('c', 'd');
        };

        $joined = $dateCounts('created_at');
        $left = $dateCounts('deleted_at');

        // Everything before the window, so the running total starts from the real figure.
        $openingFor = function (Carbon $start) use ($joined, $left) {
            $before = fn ($set) => collect($set)->filter(fn ($c, $d) => Carbon::parse($d)->lt($start))->sum();

            return $before($joined) - $before($left);
        };

        $buckets = [];

        if ($view === 'daily') {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $running = $openingFor($start);
            foreach (range(1, $start->daysInMonth) as $day) {
                $date = $start->copy()->day($day);
                $key = $date->toDateString();
                $in = (int) ($joined[$key] ?? 0);
                $out = (int) ($left[$key] ?? 0);
                $running += $in - $out;
                $buckets[] = ['label' => $date->format('j'), 'full' => $date->format('D, d M Y'),
                    'in' => $in, 'out' => $out, 'net' => $in - $out, 'total' => $running];
            }
        } elseif ($view === 'monthly') {
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $running = $openingFor($start);
            foreach (range(1, 12) as $m) {
                $in = (int) collect($joined)->filter(fn ($c, $d) => Carbon::parse($d)->year === $year && Carbon::parse($d)->month === $m)->sum();
                $out = (int) collect($left)->filter(fn ($c, $d) => Carbon::parse($d)->year === $year && Carbon::parse($d)->month === $m)->sum();
                $running += $in - $out;
                $buckets[] = ['label' => Carbon::create($year, $m, 1)->format('M'), 'full' => Carbon::create($year, $m, 1)->format('F Y'),
                    'in' => $in, 'out' => $out, 'net' => $in - $out, 'total' => $running];
            }
        } else {
            $years = collect($joined)->keys()->merge(collect($left)->keys())
                ->map(fn ($d) => (int) Carbon::parse($d)->year)->unique()->sort()->values();
            $running = 0;
            foreach ($years as $y) {
                $in = (int) collect($joined)->filter(fn ($c, $d) => Carbon::parse($d)->year === $y)->sum();
                $out = (int) collect($left)->filter(fn ($c, $d) => Carbon::parse($d)->year === $y)->sum();
                $running += $in - $out;
                $buckets[] = ['label' => (string) $y, 'full' => (string) $y,
                    'in' => $in, 'out' => $out, 'net' => $in - $out, 'total' => $running];
            }
        }

        return [
            'view' => $view,
            'year' => $year,
            'month' => $month,
            'buckets' => $buckets,
            'joined' => array_sum(array_column($buckets, 'in')),
            'left' => array_sum(array_column($buckets, 'out')),
            'net' => array_sum(array_column($buckets, 'net')),
            'closing' => $buckets ? end($buckets)['total'] : 0,
            'years' => User::clients()->withTrashed()->selectRaw('DATE(created_at) as d')
                ->whereNotNull('created_at')->pluck('d')
                ->map(fn ($d) => (int) Carbon::parse($d)->year)->unique()->sortDesc()->values(),
        ];
    }

    /**
     * Unique visitors per bucket, split into first-time and returning, with the previous period
     * alongside for comparison.
     *
     * Distinct counts cannot be added up: the same person visiting on three days is three daily
     * figures but one monthly one. So this pulls the distinct (day, visitor) pairs once and counts
     * uniques per bucket in PHP, which is the only way to get a truthful monthly or yearly number.
     *
     * "New" means their first visit ever landed in that bucket — judged against every visit on
     * record, not just the window on screen, so someone who first came last year is returning even
     * when this month is all you are looking at.
     */
    private function visitorTrend(array $growth): array
    {
        $pairs = ClientActivityLog::query()
            ->whereNull('error_code')
            ->selectRaw('DATE(created_at) as d, '.self::VISITOR_KEY.' as v')
            ->groupBy('d', 'v')
            ->get();

        $firstSeen = [];
        foreach ($pairs as $row) {
            $firstSeen[$row->v] = min($firstSeen[$row->v] ?? $row->d, $row->d);
        }

        $bucketOf = function (Carbon $date, int $year, ?int $month) use ($growth) {
            return match ($growth['view']) {
                'daily' => ($date->year === $year && $date->month === $month) ? $date->toDateString() : null,
                'monthly' => $date->year === $year ? $date->month : null,
                default => $date->year,
            };
        };

        // Same shape one period back, for the comparison line.
        $previous = match ($growth['view']) {
            'daily' => Carbon::create($growth['year'], $growth['month'], 1)->subMonth(),
            'monthly' => Carbon::create($growth['year'], 1, 1)->subYear(),
            default => null,
        };

        $seen = [];
        $seenPrev = [];

        foreach ($pairs as $row) {
            $date = Carbon::parse($row->d);

            if (($key = $bucketOf($date, $growth['year'], $growth['month'])) !== null) {
                $isNew = ($firstSeen[$row->v] ?? null) === $row->d;
                $seen[$key][$row->v] = ($seen[$key][$row->v] ?? false) || $isNew;
            }

            if ($previous && $bucketOf($date, $previous->year, $previous->month) !== null) {
                $seenPrev[$row->v] = true;
            }
        }

        $make = function ($key, string $label, string $full) use ($seen) {
            $set = $seen[$key] ?? [];
            $new = count(array_filter($set));

            return ['label' => $label, 'full' => $full, 'count' => count($set),
                'new' => $new, 'returning' => count($set) - $new];
        };

        $buckets = [];

        if ($growth['view'] === 'daily') {
            $start = Carbon::create($growth['year'], $growth['month'], 1);
            foreach (range(1, $start->daysInMonth) as $day) {
                $date = $start->copy()->day($day);
                $b = $make($date->toDateString(), $date->format('j'), $date->format('D, d M Y'));
                $b['isToday'] = $date->isToday();
                $b['isWeekend'] = $date->isWeekend();
                $buckets[] = $b;
            }
        } elseif ($growth['view'] === 'monthly') {
            foreach (range(1, 12) as $m) {
                $d = Carbon::create($growth['year'], $m, 1);
                $buckets[] = $make($m, $d->format('M'), $d->format('F Y')) + ['isToday' => $d->isSameMonth(now()), 'isWeekend' => false];
            }
        } else {
            foreach (collect(array_keys($seen))->sort() as $y) {
                $buckets[] = $make($y, (string) $y, (string) $y) + ['isToday' => $y === now()->year, 'isWeekend' => false];
            }
        }

        $total = count(array_reduce($seen, fn ($carry, $set) => $carry + $set, []));
        $prevTotal = count($seenPrev);
        $active = collect($buckets)->filter(fn ($b) => $b['count'] > 0);

        return [
            'buckets' => $buckets,
            'peak' => max(1, (int) (collect($buckets)->max('count') ?: 1)),
            'total' => $total,
            'new' => count(array_filter(array_reduce($seen, fn ($carry, $set) => $carry + $set, []))),
            // Averaged over the buckets that saw anyone: a month-to-date view should not be dragged
            // down by days that have not happened yet.
            'average' => $active->count() ? round($active->avg('count'), 1) : 0,
            'busiest' => $active->sortByDesc('count')->first(),
            'previousTotal' => $prevTotal,
            'previousLabel' => $previous ? ($growth['view'] === 'daily' ? $previous->format('F Y') : (string) $previous->year) : null,
            'change' => $prevTotal > 0 ? round(($total - $prevTotal) / $prevTotal * 100) : null,
        ];
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
