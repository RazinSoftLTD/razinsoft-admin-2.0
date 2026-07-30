<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMapsLeadRequest;
use App\Jobs\RecordMapsCollectionEvent;
use App\Models\MapsImportRun;
use App\Services\MapsLeadIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ingest endpoint used by the Chrome extension.
 *
 *   POST /api/v1/leads/store      one lead per call
 *   GET  /api/v1/leads/runs       import history
 *
 * Contract (the extension keys its counters off `duplicate`):
 *   201 {"status":"created","duplicate":false,"data":{...}}
 *   200 {"status":"duplicate","duplicate":true,"data":{...}}
 *   422 validation errors
 *   500 {"status":"error","message":"..."}   -> extension retries with backoff
 */
class MapsLeadIngestController extends Controller
{
    public function __construct(private readonly MapsLeadIngestService $service) {}

    public function store(StoreMapsLeadRequest $request): JsonResponse
    {
        $payload = $request->validated();

        try {
            $run = $this->service->touchRun($payload, $request->user()?->id);
            $result = $this->service->ingest($payload);
        } catch (Throwable $e) {
            // A 500 is a retryable signal to the extension, so log enough to
            // diagnose it and let the client come back.
            Log::error('MapsLead ingest failed', [
                'run_id' => $payload['run_id'],
                'place_key' => $payload['lead']['place_key'] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Could not store the lead. Please retry.',
            ], 500);
        }

        $lead = $result['lead'];
        $duplicate = $result['duplicate'];

        RecordMapsCollectionEvent::dispatch(
            runId: $run->run_id,
            event: $duplicate ? 'duplicate' : 'stored',
            leadId: $lead->id,
            placeKey: $lead->place_key,
            message: $lead->name,
            context: ['position' => $payload['position'] ?? null],
        );

        return response()->json([
            'status' => $duplicate ? 'duplicate' : 'created',
            'duplicate' => $duplicate,
            'data' => [
                'id' => $lead->id,
                'place_key' => $lead->place_key,
                'name' => $lead->name,
                'times_seen' => $lead->times_seen,
                'updated_at' => $lead->updated_at?->toIso8601String(),
            ],
        ], $duplicate ? 200 : 201);
    }

    /**
     * Import history, newest run first.
     */
    public function runs(): JsonResponse
    {
        $runs = MapsImportRun::query()
            ->latest('id')
            ->paginate(25, ['*'], 'page');

        return response()->json($runs);
    }
}
