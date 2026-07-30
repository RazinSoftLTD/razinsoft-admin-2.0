<?php

namespace App\Jobs;

use App\Models\MapsCollectionLog;
use App\Models\MapsImportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Post-processing for one ingest call: writes the collection log row and rolls
 * up the run counters.
 *
 * This is the queued half of the ingest path. Keeping it off the request means
 * the extension gets its created/duplicate answer in one round trip, while the
 * bookkeeping happens on a worker.
 */
class RecordMapsCollectionEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * @param  string  $event  stored|duplicate|rejected
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $runId,
        public string $event,
        public ?int $leadId = null,
        public ?string $placeKey = null,
        public ?string $message = null,
        public array $context = [],
    ) {
        $this->onQueue('maps-leads');
    }

    public function handle(): void
    {
        MapsCollectionLog::create([
            'run_id' => $this->runId,
            'level' => $this->event === 'rejected' ? 'warning' : 'info',
            'event' => $this->event,
            'message' => $this->message,
            'place_key' => $this->placeKey,
            'lead_id' => $this->leadId,
            'context' => $this->context ?: null,
        ]);

        $run = MapsImportRun::where('run_id', $this->runId)->first();
        if (! $run) {
            return;
        }

        $column = match ($this->event) {
            'stored' => 'created',
            'duplicate' => 'duplicates',
            default => 'rejected',
        };

        $run->increment('received');
        $run->increment($column);
        $run->forceFill(['last_seen_at' => now()])->save();
    }
}
