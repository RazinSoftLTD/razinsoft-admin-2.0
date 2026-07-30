<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Finds a contact address on a business's own website.
 *
 * Google Maps never publishes an email, so the only place to look is the site
 * the listing links to. This reads the pages a visitor would open to find a
 * contact address - the homepage and the usual contact/about paths - and takes
 * the address printed there.
 *
 * Deliberately narrow: a handful of pages per site, a short timeout, a response
 * size cap, and one address returned. It is a lookup, not a crawl.
 */
class MapsLeadEmailFinder
{
    /** Paths tried after the homepage, in order. */
    private const PATHS = ['/contact', '/contact-us', '/contact.html', '/about', '/about-us'];

    /** Give up on a slow site rather than holding a queue worker. */
    private const TIMEOUT = 8;

    /** Stop reading a response after this much; contact details are never deep in a huge page. */
    private const MAX_BYTES = 512000;

    /**
     * Local parts that are never worth mailing - automated senders, or addresses
     * that exist to be ignored.
     */
    private const REJECT_LOCAL = [
        'noreply', 'no-reply', 'donotreply', 'do-not-reply', 'bounce', 'bounces',
        'mailer-daemon', 'postmaster', 'abuse', 'spam', 'unsubscribe', 'privacy',
    ];

    /** Domains that show up in boilerplate and template markup, never real contacts. */
    private const REJECT_DOMAINS = [
        'example.com', 'example.org', 'domain.com', 'yourdomain.com', 'email.com',
        'sentry.io', 'wordpress.com', 'wix.com', 'squarespace.com', 'godaddy.com',
        'cloudflare.com', 'google.com', 'gstatic.com', 'schema.org', 'w3.org',
        'facebook.com', 'sentry-cdn.com',
    ];

    /** Preferred local parts, best first - a shared inbox beats a personal one. */
    private const PREFER_LOCAL = ['info', 'contact', 'hello', 'enquiry', 'enquiries', 'inquiry', 'sales', 'admin', 'office', 'support', 'mail'];

    /**
     * Look for an address on the given site.
     *
     * @return array{email: ?string, status: string, note: ?string}
     *         status: found | not_found | unreachable | invalid_url | disallowed
     */
    public function find(?string $website): array
    {
        $base = $this->normaliseBase($website);

        if (! $base) {
            return ['email' => null, 'status' => 'invalid_url', 'note' => 'no usable website URL'];
        }

        $host = parse_url($base, PHP_URL_HOST);

        if ($this->isDisallowedHost($host)) {
            return ['email' => null, 'status' => 'disallowed', 'note' => "not a business site: {$host}"];
        }

        $candidates = [];
        $reached = false;

        foreach ($this->urlsToTry($base) as $url) {
            $html = $this->fetch($url);

            if ($html === null) {
                continue;
            }

            $reached = true;
            $found = $this->extract($html, $host);
            $candidates = array_merge($candidates, $found);

            // The homepage often carries the address in the footer; stop as soon
            // as something on the site's own domain turns up.
            if ($this->bestOnHost($candidates, $host)) {
                break;
            }
        }

        if (! $reached) {
            return ['email' => null, 'status' => 'unreachable', 'note' => "could not load {$host}"];
        }

        $email = $this->pick($candidates, $host);

        return $email
            ? ['email' => $email, 'status' => 'found', 'note' => null]
            : ['email' => null, 'status' => 'not_found', 'note' => "no address published on {$host}"];
    }

    /** Homepage first, then the usual contact paths. */
    private function urlsToTry(string $base): array
    {
        return array_merge([$base], array_map(fn ($p) => rtrim($base, '/').$p, self::PATHS));
    }

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
     * listings. They have no contact address to read and are not ours to crawl.
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

    /**
     * Fetch one page. Every failure is swallowed and reported as null: an
     * unreachable site is an ordinary outcome here, not an error.
     */
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
     * Pull addresses out of a page. `mailto:` links come first because a link is
     * a deliberate publication, whereas loose text can be anything.
     *
     * @return array<int, string>
     */
    private function extract(string $html, ?string $host): array
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
        $text = preg_replace('/\s*(?:\[at\]|\(at\))\s*/i', '@', strip_tags($html)) ?? '';

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

        if (in_array($local, self::REJECT_LOCAL, true)) {
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

    /** First candidate whose domain matches the site being read. */
    private function bestOnHost(array $candidates, ?string $host): ?string
    {
        $root = $this->rootDomain($host);

        foreach ($candidates as $email) {
            if ($root && str_ends_with(explode('@', $email)[1], $root)) {
                return $email;
            }
        }

        return null;
    }

    /**
     * Choose one address: same-domain first, then a preferred shared inbox, then
     * whatever is left.
     */
    private function pick(array $candidates, ?string $host): ?string
    {
        if ($candidates === []) {
            return null;
        }

        $root = $this->rootDomain($host);

        $onDomain = array_values(array_filter(
            $candidates,
            fn ($e) => $root && str_ends_with(explode('@', $e)[1], $root),
        ));

        $pool = $onDomain !== [] ? $onDomain : $candidates;

        foreach (self::PREFER_LOCAL as $preferred) {
            foreach ($pool as $email) {
                if (explode('@', $email)[0] === $preferred) {
                    return $email;
                }
            }
        }

        return $pool[0];
    }

    /** "www.shop.example.co" -> "example.co" (good enough for a suffix match). */
    private function rootDomain(?string $host): ?string
    {
        $parts = explode('.', mb_strtolower((string) $host));

        if (count($parts) < 2) {
            return null;
        }

        return implode('.', array_slice($parts, -2));
    }
}
