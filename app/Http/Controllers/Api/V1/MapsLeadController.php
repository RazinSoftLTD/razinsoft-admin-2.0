<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\MapsLeadsExport;
use App\Http\Controllers\Controller;
use App\Models\MapsCollectionLog;
use App\Models\MapsLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MapsLead management API: search, filter, update, export.
 *
 *   GET    /api/v1/leads                 paginated + searchable + filterable
 *   GET    /api/v1/leads/{lead}
 *   PATCH  /api/v1/leads/{lead}          status / notes / assignment
 *   DELETE /api/v1/leads/{lead}          soft delete
 *   GET    /api/v1/leads/export/csv      streamed CSV of the current filter
 *   GET    /api/v1/leads/export/xlsx     Excel of the current filter
 *   GET    /api/v1/leads/logs            collection logs
 */
class MapsLeadController extends Controller
{
    /** Filter keys accepted on the index and export routes. */
    private const FILTER_KEYS = [
        'country', 'city', 'category', 'status', 'run_id',
        'min_rating', 'min_reviews', 'has_phone', 'has_website', 'from', 'to',
    ];

    public function index(Request $request): JsonResponse
    {
        $leads = MapsLead::query()
            ->search($request->query('q'))
            ->filter($request->only(self::FILTER_KEYS))
            ->orderByDesc($this->sortColumn($request))
            ->paginate(min((int) $request->query('per_page', 25), 200))
            ->withQueryString();

        return response()->json($leads);
    }

    public function show(MapsLead $lead): JsonResponse
    {
        return response()->json(['data' => $lead]);
    }

    /**
     * Only the operator-owned fields are writable here; collected data is
     * owned by the importer.
     */
    public function update(Request $request, MapsLead $lead): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', MapsLead::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $lead->fill(array_filter($data, fn ($v) => $v !== null))->save();

        return response()->json(['data' => $lead->fresh()]);
    }

    public function destroy(MapsLead $lead): JsonResponse
    {
        $lead->delete();

        return response()->json(['status' => 'deleted']);
    }

    /**
     * Streamed CSV so a 100k row export never exhausts memory.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = MapsLead::query()
            ->search($request->query('q'))
            ->filter($request->only(self::FILTER_KEYS))
            ->orderBy('id');

        $filename = 'leads-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'wb');

            // Excel needs the BOM to read UTF-8 correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, MapsLeadsExport::headings());

            $query->chunkById(500, function ($chunk) use ($handle) {
                foreach ($chunk as $lead) {
                    fputcsv($handle, MapsLeadsExport::row($lead));
                }
                flush();
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Excel export. Opt-in, because it needs a package the CSV route does not:
     *   composer require maatwebsite/excel
     *   mv app/Exports/MapsLeadsExcelExport.php.stub app/Exports/MapsLeadsExcelExport.php
     */
    public function exportXlsx(Request $request)
    {
        $wrapper = 'App\Exports\MapsLeadsExcelExport';

        if (! class_exists($wrapper) || ! class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Excel export is not enabled. Install maatwebsite/excel and rename '
                    .'app/Exports/MapsLeadsExcelExport.php.stub, or use /export/csv.',
            ], 501);
        }

        $definition = new MapsLeadsExport(
            search: $request->query('q'),
            filters: $request->only(self::FILTER_KEYS),
        );

        return \Maatwebsite\Excel\Facades\Excel::download(
            new $wrapper($definition),
            'leads-'.now()->format('Y-m-d-His').'.xlsx'
        );
    }

    /**
     * Collection logs, optionally scoped to one run.
     */
    public function logs(Request $request): JsonResponse
    {
        $logs = MapsCollectionLog::query()
            ->when($request->query('run_id'), fn ($q, $v) => $q->where('run_id', $v))
            ->when($request->query('level'), fn ($q, $v) => $q->where('level', $v))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 50), 200))
            ->withQueryString();

        return response()->json($logs);
    }

    /** Whitelist the sort column so the query string cannot inject one. */
    private function sortColumn(Request $request): string
    {
        $allowed = ['created_at', 'rating', 'review_count', 'name', 'times_seen'];
        $sort = (string) $request->query('sort', 'created_at');

        return in_array($sort, $allowed, true) ? $sort : 'created_at';
    }
}
