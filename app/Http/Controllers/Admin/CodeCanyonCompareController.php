<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EnvatoAuthor;
use App\Models\EnvatoProduct;
use App\Models\EnvatoProject;
use App\Models\EnvatoSetting;
use App\Models\EnvatoSyncRun;
use App\Services\Envato\SalesCompare;
use App\Services\Envato\SyncRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The two comparison screens over the CodeCanyon watchlist.
 *
 * Authors: who is selling how much, day by day, and which of their items is
 * carrying it. Projects: a named line-up of competing items across authors,
 * which is the shape the question takes when deciding what to build next.
 *
 * Both read the daily snapshots the sync already records; nothing here talks to
 * Envato.
 */
class CodeCanyonCompareController extends Controller
{
    /** Windows offered on both screens. */
    private const RANGES = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];

    public function authors(Request $request, SalesCompare $compare, SyncRunner $runner): View
    {
        [$from, $to, $days] = $this->window($request);

        $authors = EnvatoAuthor::with(['products' => fn ($q) => $q->orderByDesc('number_of_sales')])->get();

        $rows = $authors->map(function (EnvatoAuthor $author) use ($compare, $from, $to) {
            $perProduct = $compare->perProduct($author->products, $from, $to);
            $sold = array_sum($perProduct);
            $today = $compare->today($author->products);

            // Which single item carried the period. Answering "who sold more" is
            // only half the question; "on the back of what" is the other half.
            $topId = array_key_first($perProduct);
            $top = $topId ? $author->products->firstWhere('id', $topId) : null;

            return [
                'author' => $author,
                'sold' => $sold,
                'today' => array_sum(array_column($today, 'sold')),
                // Partial when no product has yesterday's close to measure against.
                'today_partial' => $today && ! collect($today)->contains(fn ($t) => $t['since'] === 'yesterday'),
                // Which items moved today, best first — the answer to "sold what?".
                'today_products' => collect($today)
                    ->filter(fn ($t) => $t['sold'] > 0)
                    ->sortByDesc('sold')
                    ->map(fn ($t, $id) => ['product' => $author->products->firstWhere('id', $id), 'sold' => $t['sold']])
                    ->filter(fn ($r) => (bool) $r['product'])
                    ->values(),
                'products' => $author->products->count(),
                // Two different truths, deliberately kept apart: the portfolio sum
                // covers the items we track, while Envato's profile figure counts
                // everything the author ever sold — including retired items and
                // their other marketplaces. They will not agree, and shouldn't.
                'lifetime' => (int) $author->products->sum('number_of_sales'),
                'profile_sales' => (int) $author->total_sales,
                'revenue' => $author->products->sum(fn ($p) => $p->estimatedRevenue()),
                'top' => $top,
                'top_sold' => $topId ? $perProduct[$topId] : 0,
                'per_product' => $perProduct,
                'daily' => $compare->dailyTotals($author->products, $from, $to),
            ];
        })->sortByDesc('sold')->values();

        return view('admin.codecanyon.compare-authors', [
            'rows' => $rows,
            'ranges' => self::RANGES,
            'days' => $days,
            'from' => $from,
            'to' => $to,
            'hasHistory' => $compare->hasHistory(),
            'dates' => $this->dateAxis($rows->pluck('daily')->all()),
            'sync' => $this->syncState($runner, $from, $to),
            'runs' => EnvatoSyncRun::with('author', 'triggeredBy')->latest('id')->limit(8)->get(),
            'canManage' => $request->user()->allows('codecanyon', 'manage'),
        ]);
    }

    public function projects(Request $request, SalesCompare $compare): View
    {
        [$from, $to, $days] = $this->window($request);

        $projects = EnvatoProject::withCount('products')->latest('id')->get();
        $current = $request->filled('project')
            ? $projects->firstWhere('id', (int) $request->query('project'))
            : $projects->first();

        $rows = collect();

        if ($current) {
            $current->load('products.author', 'products.niche');
            $perProduct = $compare->perProduct($current->products, $from, $to);

            $rows = $current->products->map(fn (EnvatoProduct $p) => [
                'product' => $p,
                'sold' => $perProduct[$p->id] ?? 0,
                'is_ours' => $current->own_product_id === $p->id,
                'daily' => $compare->dailyTotals([$p->id], $from, $to),
            ])->sortByDesc('sold')->values();
        }

        return view('admin.codecanyon.compare-projects', [
            'projects' => $projects,
            'current' => $current,
            'rows' => $rows,
            'ranges' => self::RANGES,
            'days' => $days,
            'hasHistory' => $compare->hasHistory(),
            'dates' => $this->dateAxis($rows->pluck('daily')->all()),
            // Only watchlist items can join a project; there is no sales history
            // for anything the sync has never seen.
            'available' => EnvatoProduct::with('author')->orderBy('name')->get(),
        ]);
    }

    /* ----------------------------------------------------------------- sync */

    /** Refresh the whole watchlist, or one author when `author` is given. */
    public function sync(Request $request, SyncRunner $runner)
    {
        $author = $request->filled('author')
            ? EnvatoAuthor::find((int) $request->input('author'))
            : null;

        $run = $runner->queue($author ? 'author' : 'manual', $author, $request->user()->id);

        if (! $run) {
            return back()->withErrors(['sync' => 'Add your Envato personal token under Settings → CodeCanyon Config first.']);
        }

        return back()->with('status', $run->isActive()
            ? ($author ? "Refreshing {$author->username}…" : 'Sync started — the page updates when it finishes.')
            : "A sync is already {$run->status}.");
    }

    /**
     * Sync state as JSON, for the page to poll while a run is in flight.
     *
     * Polling rather than pushing: a sync takes a minute at most and this saves
     * standing up a broadcast channel for one screen.
     */
    public function syncStatus(Request $request, SyncRunner $runner): JsonResponse
    {
        [$from, $to] = $this->window($request);

        $state = $this->syncState($runner, $from, $to);

        return response()->json([
            'active' => $state['active'],
            'stalled' => $state['stalled'],
            'status' => $state['last']?->status,
            'message' => $state['message'],
            'last_synced_human' => $state['last_synced']?->diffForHumans(),
        ]);
    }

    /**
     * Everything the page needs to say about syncing.
     *
     * @return array<string, mixed>
     */
    private function syncState(SyncRunner $runner, Carbon $from, Carbon $to): array
    {
        $settings = EnvatoSetting::current();
        $last = EnvatoSyncRun::latest('id')->first();
        $missing = $runner->missingDays($from, $to);

        $message = match (true) {
            ! $settings->isConfigured() => 'No Envato token — connect the API to start syncing.',
            (bool) $last?->looksStalled() => 'Queued, but nothing has picked it up. Is the queue worker running?',
            (bool) $last?->isActive() => 'Sync in progress…',
            $last?->status === 'failed' => 'Last sync failed: '.$last->error,
            ! $runner->capturedToday() => "Today's snapshot has not been taken yet.",
            default => "Today's snapshot is recorded.",
        };

        return [
            'configured' => $settings->isConfigured(),
            'auto' => (bool) $settings->auto_sync,
            'last_synced' => $settings->last_synced_at,
            'captured_today' => $runner->capturedToday(),
            'last' => $last,
            'active' => (bool) $last?->isActive() && ! $last->looksStalled(),
            'stalled' => (bool) $last?->looksStalled(),
            'missing' => $missing,
            'covered' => $from->diffInDays($to) + 1 - count($missing),
            'total_days' => $from->diffInDays($to) + 1,
            'message' => $message,
        ];
    }

    /* ---------------------------------------------------------- projects CRUD */

    public function storeProject(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $project = EnvatoProject::create($data + ['created_by' => $request->user()->id]);

        return redirect()
            ->route('admin.codecanyon.compare-projects', ['project' => $project->id])
            ->with('status', "Project \"{$project->name}\" created. Add the products to compare.");
    }

    public function updateProject(Request $request, EnvatoProject $project)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'own_product_id' => ['nullable', 'integer', 'exists:envato_products,id'],
            'products' => ['nullable', 'array'],
            'products.*' => ['integer', 'exists:envato_products,id'],
        ]);

        $project->update([
            'name' => $data['name'],
            'notes' => $data['notes'] ?? null,
            'own_product_id' => $data['own_product_id'] ?: null,
        ]);

        $project->products()->sync($data['products'] ?? []);

        return back()->with('status', 'Project updated.');
    }

    public function destroyProject(EnvatoProject $project)
    {
        $name = $project->name;
        $project->delete();

        return redirect()
            ->route('admin.codecanyon.compare-projects')
            ->with('status', "Project \"{$name}\" deleted.");
    }

    /* ------------------------------------------------------------------ util */

    /**
     * The chosen window.
     *
     * @return array{0: Carbon, 1: Carbon, 2: int}
     */
    private function window(Request $request): array
    {
        $days = (int) $request->query('days', 30);
        if (! array_key_exists($days, self::RANGES)) {
            $days = 30;
        }

        return [today()->copy()->subDays($days - 1), today(), $days];
    }

    /**
     * Every date any series has a figure for, in order.
     *
     * Built from the data rather than the range so a gap in the sync shows as a
     * missing column instead of a run of invented zeroes.
     *
     * @param  array<int, array<string, int>>  $series
     * @return array<int, string>
     */
    private function dateAxis(array $series): array
    {
        $dates = [];
        foreach ($series as $row) {
            foreach (array_keys($row) as $date) {
                $dates[$date] = true;
            }
        }
        ksort($dates);

        return array_keys($dates);
    }
}
