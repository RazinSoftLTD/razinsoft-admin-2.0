<?php

namespace App\Services\Envato;

use App\Jobs\RunCodeCanyonSync;
use App\Models\EnvatoAuthor;
use App\Models\EnvatoSetting;
use App\Models\EnvatoSyncRun;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Everything around a sync: queueing it, recording what happened, and knowing
 * whether today's snapshot has been taken yet.
 *
 * EnvatoSync does the talking to Envato; this decides when that should happen
 * and leaves a trail afterwards. Worth separating because the trail is the whole
 * point — a day whose snapshot was never captured cannot be recovered later, so
 * a silent failure is expensive in a way most failed jobs are not.
 */
class SyncRunner
{
    public function __construct(private EnvatoSync $sync) {}

    /**
     * Put a sync on the queue and hand back the run row to watch.
     *
     * Returns null when there is nothing to do — no token, or a run is already
     * in flight. Firing a second sync at the same watchlist only burns API quota.
     */
    public function queue(string $trigger, ?EnvatoAuthor $author = null, ?int $userId = null): ?EnvatoSyncRun
    {
        if (! EnvatoSetting::current()->isConfigured()) {
            return null;
        }

        if ($existing = EnvatoSyncRun::active()->latest('id')->first()) {
            if (! $existing->looksStalled()) {
                return $existing;
            }
            // Nothing picked it up. Let it go rather than block the watchlist forever.
            $existing->update(['status' => 'failed', 'error' => 'Abandoned — no queue worker picked it up.', 'finished_at' => now()]);
        }

        $run = EnvatoSyncRun::create([
            'trigger' => $trigger,
            'status' => 'queued',
            'envato_author_id' => $author?->id,
            'triggered_by' => $userId,
        ]);

        RunCodeCanyonSync::dispatch($run->id);

        return $run;
    }

    /** Perform the run this row stands for. Never throws — failures land on the row. */
    public function execute(EnvatoSyncRun $run): EnvatoSyncRun
    {
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        // Snapshots already on the books for today, so we can report how many this
        // run actually added rather than how many exist.
        $before = $this->snapshotsToday();

        try {
            if ($run->envato_author_id && ($author = $run->author)) {
                $products = $this->sync->author($author);
                $authors = 1;
            } else {
                [$authors, $products] = $this->sync->all();
            }

            $run->update([
                'status' => 'success',
                'authors_synced' => $authors,
                'products_synced' => $products,
                'snapshots_written' => max(0, $this->snapshotsToday() - $before),
                'finished_at' => now(),
            ]);

            EnvatoSetting::current()->update(['last_synced_at' => now(), 'last_error' => null]);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'snapshots_written' => max(0, $this->snapshotsToday() - $before),
                'finished_at' => now(),
            ]);

            EnvatoSetting::current()->update(['last_error' => $e->getMessage()]);
        }

        return $run->refresh();
    }

    /** Whether today's reading has been taken. This is what the trend depends on. */
    public function capturedToday(): bool
    {
        return $this->snapshotsToday() > 0;
    }

    /**
     * Which days in a range have no snapshot at all.
     *
     * @return array<int, string>
     */
    public function missingDays(\Illuminate\Support\Carbon $from, \Illuminate\Support\Carbon $to): array
    {
        // whereDate rather than a string range — see the note in SalesCompare::daily().
        $have = DB::table('envato_snapshots')
            ->whereDate('captured_on', '>=', $from->toDateString())
            ->whereDate('captured_on', '<=', $to->toDateString())
            ->distinct()->pluck('captured_on')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->flip();

        $missing = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            if (! $have->has($day->toDateString())) {
                $missing[] = $day->toDateString();
            }
        }

        return $missing;
    }

    private function snapshotsToday(): int
    {
        return DB::table('envato_snapshots')->whereDate('captured_on', today())->count();
    }
}
