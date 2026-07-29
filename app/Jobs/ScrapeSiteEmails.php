<?php

namespace App\Jobs;

use App\Models\EmailScrapeRun;
use App\Models\ScrapedEmail;
use App\Services\Email\SiteEmailScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs one crawl in the background.
 *
 * Queued rather than done in the request because a 25-page crawl is a minute of waiting on someone
 * else's server, and a browser tab should not be holding that open.
 */
class ScrapeSiteEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** One attempt: a re-run would re-crawl the whole site, and the failure is nearly always the
     *  target site being unreachable — worth showing rather than silently retrying. */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $runId) {}

    public function handle(SiteEmailScraper $scraper): void
    {
        $run = EmailScrapeRun::find($this->runId);

        if (! $run || $run->status !== 'pending') {
            return;
        }

        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $result = $scraper->crawl($run->url, $run->max_pages);
        } catch (\Throwable $e) {
            Log::warning('Email scrape failed for '.$run->url.': '.$e->getMessage());
            $run->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 500),
                'finished_at' => now(),
            ]);

            return;
        }

        $new = 0;

        foreach ($result['emails'] as $email => $meta) {
            $existing = ScrapedEmail::where('email', $email)->first();

            if ($existing) {
                // Seen before, on this site or another: keep the first sighting, note it is still live.
                $existing->update(['last_seen_at' => now()]);

                continue;
            }

            ScrapedEmail::create([
                'email' => $email,
                'domain' => $run->domain,
                'source_url' => mb_substr($meta['url'] ?? $run->url, 0, 1024),
                'name' => $meta['name'] ?? null,
                'is_role_address' => ScrapedEmail::isRoleAddress($email),
                'run_id' => $run->id,
                'last_seen_at' => now(),
            ]);
            $new++;
        }

        $run->update([
            'status' => 'done',
            'pages_crawled' => $result['pages'],
            'emails_found' => count($result['emails']),
            'emails_new' => $new,
            'finished_at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        EmailScrapeRun::where('id', $this->runId)->update([
            'status' => 'failed',
            'error' => mb_substr($e->getMessage(), 0, 500),
            'finished_at' => now(),
        ]);
    }
}
