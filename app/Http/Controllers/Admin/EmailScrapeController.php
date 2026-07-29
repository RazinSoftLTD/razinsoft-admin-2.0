<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ScrapeSiteEmails;
use App\Models\EmailScrapeRun;
use App\Models\EmailSuppression;
use App\Models\ScrapedEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Email Manager → Scraping: collect published addresses from a site, review them, and move the
 * useful ones into the client book so a campaign can reach them.
 */
class EmailScrapeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeScraping($request);

        $emails = ScrapedEmail::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('email', 'like', '%'.$request->string('q').'%')
                ->orWhere('domain', 'like', '%'.$request->string('q').'%')
                ->orWhere('name', 'like', '%'.$request->string('q').'%')))
            ->when($request->query('domain'), fn ($q, $d) => $q->where('domain', $d))
            ->when($request->query('kind') === 'people', fn ($q) => $q->where('is_role_address', false))
            ->when($request->query('kind') === 'role', fn ($q) => $q->where('is_role_address', true))
            ->when($request->query('imported') === 'yes', fn ($q) => $q->whereNotNull('imported_client_id'))
            ->when($request->query('imported') === 'no', fn ($q) => $q->whereNull('imported_client_id'))
            ->latest('id')
            ->paginate(30)->withQueryString();

        return view('admin.email.scraping', [
            'runs' => EmailScrapeRun::with('creator:id,name')->latest('id')->limit(10)->get(),
            'emails' => $emails,
            'total' => ScrapedEmail::count(),
            'people' => ScrapedEmail::where('is_role_address', false)->count(),
            'imported' => ScrapedEmail::whereNotNull('imported_client_id')->count(),
            'domains' => ScrapedEmail::whereNotNull('domain')->distinct()->orderBy('domain')->pluck('domain'),
        ]);
    }

    /** Queue a crawl. */
    public function store(Request $request)
    {
        $this->authorizeScraping($request);

        $data = $request->validate([
            'url' => ['required', 'string', 'max:255'],
            'max_pages' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $url = preg_match('#^https?://#i', $data['url']) ? $data['url'] : 'https://'.ltrim($data['url'], '/');
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return back()->with('error', 'That does not look like a web address.');
        }

        $run = EmailScrapeRun::create([
            'url' => $url,
            'domain' => $host,
            'max_pages' => $data['max_pages'] ?? 25,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        ScrapeSiteEmails::dispatch($run->id);

        return back()->with('status', "Crawling {$host} — results appear here as they are found.");
    }

    /**
     * Move addresses into the client book so campaigns can reach them.
     *
     * The label is what makes them targetable afterwards: campaigns aim by client label, so a
     * scrape imported as "Prospects — August" is a mailing list without any new plumbing.
     */
    public function import(Request $request)
    {
        $this->authorizeScraping($request);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'label' => ['required', 'string', 'max:60'],
        ]);

        $rows = ScrapedEmail::whereIn('id', $data['ids'])->whereNull('imported_client_id')->get();

        // Never import an address someone has already asked us to stop mailing.
        $blocked = EmailSuppression::filter($rows->pluck('email')->all());

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (in_array(mb_strtolower($row->email), $blocked, true)) {
                $skipped++;

                continue;
            }

            $client = User::where('email', $row->email)->first();

            if (! $client) {
                $client = User::create([
                    'name' => $row->name ?: Str::before($row->email, '@'),
                    'email' => $row->email,
                    'role' => 'customer',
                    'password' => bcrypt(Str::random(32)),   // no login; they never signed up
                    'client_label' => $data['label'],
                    'company' => $row->domain,
                ]);
            }

            $row->update(['imported_client_id' => $client->id, 'imported_at' => now()]);
            $imported++;
        }

        $msg = "{$imported} address(es) added to clients under \"{$data['label']}\".";
        if ($skipped) {
            $msg .= " {$skipped} skipped — on the suppression list.";
        }

        return back()->with('status', $msg);
    }

    public function destroy(Request $request, ScrapedEmail $scrapedEmail)
    {
        $this->authorizeScraping($request);
        $scrapedEmail->delete();

        return back()->with('status', 'Address removed.');
    }

    /** CSV of everything matching the current filter. */
    public function export(Request $request): StreamedResponse
    {
        $this->authorizeScraping($request);

        $query = ScrapedEmail::query()
            ->when($request->query('domain'), fn ($q, $d) => $q->where('domain', $d))
            ->when($request->query('kind') === 'people', fn ($q) => $q->where('is_role_address', false))
            ->when($request->query('kind') === 'role', fn ($q) => $q->where('is_role_address', true))
            ->orderBy('id');

        $name = 'scraped-emails-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Email', 'Name', 'Domain', 'Type', 'Source page', 'Found']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->email, $r->name, $r->domain,
                        $r->is_role_address ? 'Role' : 'Person',
                        $r->source_url, optional($r->created_at)->format('Y-m-d H:i'),
                    ]);
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** Scraping rides on the send permission — collecting a list is only useful if you may mail it. */
    private function authorizeScraping(Request $request): void
    {
        abort_unless($request->user()->hasPermission('email.send'), 403);
    }
}
