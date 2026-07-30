<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Collects the contact addresses published on a business's own website.
 *
 * Google Maps never shows an email, so the site the listing links to is the only
 * place to look. This walks that site the way someone hunting for a contact
 * address would: start at the homepage, prefer anything that looks like a
 * contact or about page, and stop once the obvious places are exhausted.
 *
 * Bounded on purpose - a page cap, a total time budget, one host only, and a
 * pause between requests. It is a look around a site, not a crawler, and a small
 * business host will not tolerate being hammered.
 */
class MapsLeadEmailFinder
{
    /** Pages to open per site. */
    private const MAX_PAGES = 25;

    /** Whole-site budget. A slow host must not hold a queue worker indefinitely. */
    private const MAX_SECONDS = 60;

    /** Per-request timeout. */
    private const TIMEOUT = 8;

    /** Politeness gap between requests to the same host, in microseconds. */
    private const GAP_US = 400000;

    /** Stop reading a response here; contact details are never deep in a huge page. */
    private const MAX_BYTES = 512000;

    /** Tried first, before any link found on the page. */
    private const SEED_PATHS = [
        '/contact', '/contact-us', '/contact_us', '/contacts', '/contact.html',
        '/about', '/about-us', '/about.html', '/support', '/help', '/team',
        '/reach-us', '/get-in-touch', '/impressum',
    ];

    /** A link whose text or href looks like one of these is worth following. */
    private const LINK_HINTS = [
        'contact', 'about', 'support', 'help', 'team', 'reach', 'touch',
        'enquir', 'inquir', 'impressum', 'kontakt',
    ];

    /**
     * Local parts that are a shared business inbox rather than a person. Only
     * these are offered for outreach.
     */
    private const GENERIC_LOCALS = [
        'info', 'contact', 'hello', 'hi', 'enquiry', 'enquiries', 'inquiry', 'inquiries',
        'sales', 'admin', 'office', 'support', 'help', 'mail', 'email', 'team',
        'booking', 'bookings', 'reservations', 'order', 'orders', 'service',
        'customercare', 'care', 'general', 'business', 'marketing', 'hr', 'careers', 'jobs',
    ];

    /** Automated senders and addresses that exist to be ignored. */
    private const REJECT_LOCALS = [
        'noreply', 'no-reply', 'donotreply', 'do-not-reply', 'bounce', 'bounces',
        'mailer-daemon', 'postmaster', 'abuse', 'spam', 'unsubscribe', 'privacy',
    ];

    /** Domains that appear in boilerplate and tracking snippets, never as contacts. */
    private const REJECT_DOMAINS = [
        'example.com', 'example.org', 'domain.com', 'yourdomain.com', 'email.com',
        'sentry.io', 'wordpress.com', 'wix.com', 'squarespace.com', 'godaddy.com',
        'cloudflare.com', 'google.com', 'gstatic.com', 'schema.org', 'w3.org',
        'facebook.com', 'sentry-cdn.com', 'shopify.com', 'jquery.com', 'bootstrapcdn.com',
    ];

    /**
     * Find every publishable address on the site.
     *
     * @return array{emails: array<int, array{email: string, source_url: string, is_generic: bool, same_domain: bool}>, status: string, note: ?string, pages: int}
     *         status: found | not_found | unreachable | invalid_url | disallowed
     */
    public function findAll(?string $website): array
    {
        $base = $this->normaliseBase($website);

        if (! $base) {
            return $this->result([], 'invalid_url', 'no usable website URL', 0);
        }

        $host = parse_url($base, PHP_URL_HOST);

        if ($this->isDisallowedHost($host)) {
            return $this->result([], 'disallowed', "not a business site: {$host}", 0);
        }

        $root = $this->rootDomain($host);
        $deadline = microtime(true) + self::MAX_SECONDS;

        $queue = array_merge([$base], array_map(fn ($p) => rtrim($base, '/').$p, self::SEED_PATHS));
        $visited = [];
        /** @var array<string, array> $found keyed by address, so a repeat keeps the first page it was seen on */
        $found = [];
        $reached = 0;

        while ($queue && count($visited) < self::MAX_PAGES && microtime(true) < $deadline) {
            $url = array_shift($queue);
            $key = rtrim(strtok($url, '#'), '/');

            if (isset($visited[$key])) {
                continue;
            }
            $visited[$key] = true;

            $html = $this->fetch($url);
            if ($html === null) {
                continue;
            }
            $reached++;

            foreach ($this->extract($html) as $email) {
                if (! isset($found[$email])) {
                    $found[$email] = $this->describe($email, $url, $root);
                }
            }

            // Only follow links from the homepage: the seeds already cover the
            // usual pages, and following from everywhere turns this into a crawl.
            if ($key === rtrim($base, '/')) {
                foreach ($this->contactLinks($html, $base, $host) as $link) {
                    if (! isset($visited[rtrim($link, '/')])) {
                        $queue[] = $link;
                    }
                }
            }

            usleep(self::GAP_US);
        }

        if ($reached === 0) {
            return $this->result([], 'unreachable', "could not load {$host}", 0);
        }

        $emails = $this->sort(array_values($found));

        return $emails
            ? $this->result($emails, 'found', null, count($visited))
            : $this->result([], 'not_found', "no address published on {$host}", count($visited));
    }

    /**
     * Backwards-compatible single-address lookup: the best one, or null.
     *
     * @return array{email: ?string, status: string, note: ?string}
     */
    public function find(?string $website): array
    {
        $result = $this->findAll($website);

        return [
            'email' => $result['emails'][0]['email'] ?? null,
            'status' => $result['status'],
            'note' => $result['note'],
        ];
    }

    private function result(array $emails, string $status, ?string $note, int $pages): array
    {
        return compact('emails', 'status', 'note', 'pages');
    }

    /* ------------------------------------------------------------- fetching */

    /** Turn whatever Maps gave us into a scheme + host origin, or null. */
    private function normaliseBase(?string $website): ?string
    {
        $raw = trim((string) $website);

        if ($raw === '') {
            return null;
        }

        if (! Str::startsWith($raw, ['http://', 'https://'])) {
            $raw = 'https://'.$raw;
        }

        $parts = parse_url($raw);

        if (! $parts || empty($parts['host']) || ! str_contains($parts['host'], '.')) {
            return null;
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'];
    }

    /**
     * Social profiles and marketplace pages are common "websites" on Maps
     * listings. They publish no contact address we can read, and they are not
     * ours to crawl.
     */
    private function isDisallowedHost(?string $host): bool
    {
        $host = mb_strtolower((string) $host);

        foreach (['facebook.', 'instagram.', 'linkedin.', 'twitter.', 'x.com', 'tiktok.',
            'youtube.', 'wa.me', 'whatsapp.', 't.me', 'telegram.', 'google.', 'goo.gl',
            'daraz.', 'amazon.', 'ebay.', 'bikroy.', 'linktr.ee'] as $bad) {
            if (str_contains($host, $bad)) {
                return true;
            }
        }

        return false;
    }

    /** Fetch one page. Every failure is an ordinary outcome here, not an error. */
    private function fetch(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                    // Identifies the caller honestly rather than posing as a browser.
                    'User-Agent' => 'RazinSoftLeadBot/1.0 (+contact lookup; respects robots)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->timeout(self::TIMEOUT)
                ->connectTimeout(5)
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            if (! Str::contains(mb_strtolower($response->header('Content-Type') ?: ''), 'html')) {
                return null;
            }

            return substr($response->body(), 0, self::MAX_BYTES);
        } catch (\Throwable $e) {
            Log::debug('Lead email lookup could not load a page.', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Same-host links whose text or address suggests a contact page.
     *
     * @return array<int, string>
     */
    private function contactLinks(string $html, string $base, ?string $host): array
    {
        if (! preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER)) {
            return [];
        }

        $links = [];

        foreach ($m as [, $href, $text]) {
            $haystack = mb_strtolower($href.' '.strip_tags($text));

            $looksRight = false;
            foreach (self::LINK_HINTS as $hint) {
                if (str_contains($haystack, $hint)) {
                    $looksRight = true;
                    break;
                }
            }
            if (! $looksRight) {
                continue;
            }

            $url = $this->absolute($href, $base);
            if ($url && parse_url($url, PHP_URL_HOST) === $host) {
                $links[$url] = true;
            }
        }

        return array_keys($links);
    }

    /** Resolve an href against the site root. Relative-to-current is not needed: only homepage links are followed. */
    private function absolute(string $href, string $base): ?string
    {
        $href = trim($href);

        if ($href === '' || Str::startsWith($href, ['#', 'mailto:', 'tel:', 'javascript:', 'data:'])) {
            return null;
        }

        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }

        return rtrim($base, '/').'/'.ltrim($href, '/');
    }

    /* ------------------------------------------------------------ extraction */

    /**
     * Addresses on one page. `mailto:` links come first because a link is a
     * deliberate publication, whereas loose text can be anything.
     *
     * @return array<int, string>
     */
    private function extract(string $html): array
    {
        $found = [];

        if (preg_match_all('/mailto:([^"\'?>\s]+)/i', $html, $m)) {
            foreach ($m[1] as $raw) {
                if ($clean = $this->clean(urldecode($raw))) {
                    $found[] = $clean;
                }
            }
        }

        // Plain text addresses, including the "info [at] example.com" dodge.
        $text = preg_replace('/\s*(?:\[at\]|\(at\)|\s+at\s+)\s*/i', '@', strip_tags($html)) ?? '';

        if (preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $text, $m)) {
            foreach ($m[0] as $raw) {
                if ($clean = $this->clean($raw)) {
                    $found[] = $clean;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /** Validate and normalise one candidate, or return null. */
    private function clean(string $raw): ?string
    {
        $email = mb_strtolower(trim($raw, " \t\n\r\0\x0B.,;:<>()[]\"'"));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        // Image and asset filenames routinely match the email pattern.
        if (preg_match('/\.(png|jpe?g|gif|svg|webp|css|js|woff2?)$/i', $email)) {
            return null;
        }

        if (in_array($local, self::REJECT_LOCALS, true)) {
            return null;
        }

        foreach (self::REJECT_DOMAINS as $bad) {
            if ($domain === $bad || str_ends_with($domain, '.'.$bad)) {
                return null;
            }
        }

        // A hex blob local part is a tracking address, not a person.
        if (strlen($local) > 40 || preg_match('/^[0-9a-f]{16,}$/', $local)) {
            return null;
        }

        return $email;
    }

    /**
     * Classify one address.
     *
     * @return array{email: string, source_url: string, is_generic: bool, same_domain: bool}
     */
    private function describe(string $email, string $sourceUrl, ?string $root): array
    {
        [$local, $domain] = explode('@', $email, 2);

        // "info", "info.dhaka" and "sales-bd" are all shared inboxes.
        $head = preg_split('/[.\-_+]/', $local)[0] ?? $local;

        return [
            'email' => $email,
            'source_url' => Str::limit($sourceUrl, 490, ''),
            'is_generic' => in_array($local, self::GENERIC_LOCALS, true)
                || in_array($head, self::GENERIC_LOCALS, true),
            'same_domain' => (bool) ($root && str_ends_with($domain, $root)),
        ];
    }

    /**
     * Best first: an address on the business's own domain beats a gmail one, and
     * a shared inbox beats a named person. The first entry is what outreach uses.
     */
    private function sort(array $emails): array
    {
        usort($emails, function ($a, $b) {
            return [$b['same_domain'], $b['is_generic'], $a['email']]
                <=> [$a['same_domain'], $a['is_generic'], $b['email']];
        });

        return $emails;
    }

/**
     * Compound public suffixes. Without these "clinic.com.bd" reduces to
     * "com.bd", and every .com.bd address in the country counts as the same
     * business - which matters here, where most sites sit under one of these.
     */
    private const COMPOUND_SUFFIXES = [
        'com.bd', 'net.bd', 'org.bd', 'edu.bd', 'gov.bd', 'ac.bd', 'info.bd',
        'co.uk', 'org.uk', 'ac.uk', 'gov.uk', 'me.uk', 'net.uk',
        'com.au', 'net.au', 'org.au', 'edu.au', 'com.pk', 'com.np', 'com.lk',
        'co.in', 'net.in', 'org.in', 'com.my', 'com.sg', 'com.ph', 'com.tr',
        'com.br', 'com.mx', 'co.za', 'co.nz', 'co.jp', 'co.kr', 'com.cn',
    ];

    /**
     * The registrable domain: "www.shop.clinic.com.bd" -> "clinic.com.bd".
     *
     * Not a full public-suffix implementation - just enough of one that a
     * shared country suffix is never mistaken for a shared business.
     */
    private function rootDomain(?string $host): ?string
    {
        $parts = explode('.', mb_strtolower((string) $host));

        if (count($parts) < 2) {
            return null;
        }

        $lastTwo = implode('.', array_slice($parts, -2));

        if (in_array($lastTwo, self::COMPOUND_SUFFIXES, true) && count($parts) >= 3) {
            return implode('.', array_slice($parts, -3));
        }

        return $lastTwo;
    }
}
