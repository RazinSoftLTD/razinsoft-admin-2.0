<?php

namespace App\Jobs;

use App\Models\EnvatoSyncRun;
use App\Services\Envato\SyncRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs one CodeCanyon sync off the request cycle.
 *
 * A full watchlist refresh is one API call per author plus one per item, so it
 * comfortably outlives an HTTP request once there are more than a handful of
 * products to walk.
 *
 * Only the run id travels: the row records the outcome, and SyncRunner already
 * swallows failures onto it, so a retry here would double-count rather than help.
 */
class RunCodeCanyonSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $runId) {}

    public function handle(SyncRunner $runner): void
    {
        if ($run = EnvatoSyncRun::find($this->runId)) {
            $runner->execute($run);
        }
    }

    /** The queue gave up on us (timeout, worker restart) — say so on the row. */
    public function failed(\Throwable $e): void
    {
        EnvatoSyncRun::where('id', $this->runId)->whereIn('status', ['queued', 'running'])->update([
            'status' => 'failed',
            'error' => mb_substr($e->getMessage(), 0, 2000),
            'finished_at' => now(),
        ]);
    }
}
