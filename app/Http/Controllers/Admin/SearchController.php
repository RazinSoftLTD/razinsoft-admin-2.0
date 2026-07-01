<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchLog;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $range = $request->query('range', '30');
        $from = match ($range) {
            'today' => now()->startOfDay(),
            '7' => now()->subDays(7),
            '30' => now()->subDays(30),
            default => null, // all time
        };

        $scoped = fn () => SearchLog::query()->when($from, fn ($q) => $q->where('created_at', '>=', $from));

        // Headline stats for the selected range.
        $totalSearches = $scoped()->count();
        $uniqueTerms = $scoped()->distinct('term')->count('term');
        $noResults = (clone $scoped())->where('results_count', 0)->count();

        // Aggregated per-term table (with optional filters).
        $terms = $scoped()
            ->when($request->query('q'), fn ($q, $s) => $q->where('term', 'like', '%'.strtolower(trim($s)).'%'))
            ->when($request->boolean('no_results'), fn ($q) => $q->where('results_count', 0))
            ->selectRaw('term, COUNT(*) as total, MAX(created_at) as last_at, SUM(results_count = 0) as no_result_count, MAX(results_count) as best_results')
            ->groupBy('term')
            ->orderByDesc('total')
            ->orderByDesc('last_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.searches.index', compact('terms', 'totalSearches', 'uniqueTerms', 'noResults', 'range'));
    }

    public function destroy(Request $request)
    {
        SearchLog::query()->delete();

        return back()->with('status', 'Search history cleared.');
    }
}
