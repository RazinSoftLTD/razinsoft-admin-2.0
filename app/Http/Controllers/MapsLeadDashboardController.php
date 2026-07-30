<?php

namespace App\Http\Controllers;

use App\Exports\MapsLeadsExport;
use App\Models\MapsLead;
use App\Models\MapsImportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-rendered lead management dashboard.
 *
 * Deliberately plain Blade with no build step, so it drops into an existing
 * admin app without touching its asset pipeline.
 */
class MapsLeadDashboardController extends Controller
{
    private const FILTER_KEYS = [
        'country', 'city', 'category', 'status', 'run_id',
        'min_rating', 'min_reviews', 'has_phone', 'has_website', 'from', 'to',
    ];

    public function index(Request $request): View
    {
        $filters = $request->only(self::FILTER_KEYS);
        $search = $request->query('q');

        $leads = MapsLead::query()
            ->search($search)
            ->filter($filters)
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('maps-leads.index', [
            'leads' => $leads,
            'filters' => $filters,
            'search' => $search,
            'statuses' => MapsLead::STATUSES,
            'countries' => MapsLead::query()->whereNotNull('search_country')->distinct()->orderBy('search_country')->pluck('search_country'),
            'cities' => MapsLead::query()->whereNotNull('search_city')->distinct()->orderBy('search_city')->pluck('search_city'),
            // The business categories actually collected, so the filter offers
            // real choices instead of asking the operator to guess the wording.
            'categories' => MapsLead::query()
                ->whereNotNull('category')->where('category', '!=', '')
                ->distinct()->orderBy('category')->pluck('category'),
            'summary' => [
                'total' => MapsLead::count(),
                'with_phone' => MapsLead::whereNotNull('phone')->where('phone', '!=', '')->count(),
                'with_website' => MapsLead::whereNotNull('website')->where('website', '!=', '')->count(),
                'runs' => MapsImportRun::count(),
            ],
        ]);
    }

    public function runs(): View
    {
        return view('maps-leads.runs', [
            'runs' => MapsImportRun::query()->latest('id')->paginate(30),
        ]);
    }

    /**
     * Cheap poll target for the list's live indicator.
     *
     * Deliberately two aggregates and nothing else: the page asks every few
     * seconds while a collection is running, so it must stay far cheaper than
     * re-rendering the table. `latest` is what actually detects new arrivals -
     * the count alone would miss an insert that coincided with a deletion.
     *
     * The same filters as the list are applied, so a filtered view only reports
     * rows the operator would actually see.
     */
    public function live(Request $request): JsonResponse
    {
        $query = MapsLead::query()
            ->search($request->query('q'))
            ->filter($request->only(self::FILTER_KEYS));

        return response()->json([
            'total' => (clone $query)->count(),
            'latest' => (clone $query)->max('id'),
            'collecting' => MapsImportRun::where('last_seen_at', '>=', now()->subMinute())->exists(),
        ]);
    }

    public function update(Request $request, MapsLead $lead)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', MapsLead::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $lead->update($data);

        return back()->with('status', "Updated {$lead->name}.");
    }

    /** Same streamed CSV as the API, for the dashboard's export button. */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = (new MapsLeadsExport(
            search: $request->query('q'),
            filters: $request->only(self::FILTER_KEYS),
        ))->query();

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, MapsLeadsExport::headings());

            $query->chunkById(500, function ($chunk) use ($handle) {
                foreach ($chunk as $lead) {
                    fputcsv($handle, MapsLeadsExport::row($lead));
                }
                flush();
            });

            fclose($handle);
        }, 'maps-leads-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
