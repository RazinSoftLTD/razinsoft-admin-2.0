<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Http;

/**
 * Walks one site and pulls the email addresses out of it.
 *
 * Deliberately narrow: it stays on the submitted host, follows a bounded number of pages, and
 * prefers the pages addresses actually live on (contact, about, team) before anything else it
 * finds. That ordering matters more than raw breadth — a 25-page budget spent on a blog archive
 * finds nothing, the same budget spent on /contact usually finds everything the site publishes.
 *
 * It is a fetcher, not a browser: pages rendered entirely by JavaScript will look empty, and that
 * is a real limit rather than something to work around here.
 */
class SiteEmailScraper
{
    /** Paths worth trying first, whether or not they are linked from the homepage. */
    private const PRIORITY_PATHS = [
        '/contact', '/contact-us', '/contactus', '/about', '/about-us', '/team', '/our-team',
        '/support', '/impressum', '/privacy', '/privacy-policy',
    ];

    /** Link text/href fragments that suggest a page carrying contact details. */
    private const PRIORITY_HINTS = ['contact', 'about', 'team', 'staff', 'support', 'impressum', 'people'];

    private const TIMEOUT_SECONDS = 12;

    /** Extensions that are never HTML — skipped without a request. */
    private const SKIP_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'css', 'js',
        'pdf', 'zip', 'rar', 'mp4', 'mp3', 'avi', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

    /**
     * @return array{emails: array<string, array{url: string, name: ?string}>, pages: int}
     */
    public function crawl(string $startUrl, int $maxPages = 25): array
    {
        $start = $this->normaliseUrl($startUrl);
        $host = parse_url($start, PHP_URL_HOST);

        if (! $host) {
            throw new \InvalidArgumentException('That does not look like a web address.');
        }

        $queue = $this->seedQueue($start, $host);
        $visited = [];
        $emails = [];
        $pages = 0;

        while ($queue && $pages < $maxPages) {
            $url = array_shift($queue);
            $key = rtrim(strtok($url, '#'), '/');

            if (isset($visited[$key])) {
                continue;
            }
            $visited[$key] = true;

            $html = $this->fetch($url);
            if ($html === null) {
                continue;   // 404, timeout, non-HTML — a dead page is not a dead crawl
            }
            $pages++;

            foreach ($this->extractEmails($html) as $email => $name) {
                // First page an address appears on is the one worth recording.
                $emails[$email] ??= ['url' => $url, 'name' => $name];
            }

            if ($pages < $maxPages) {
                $queue = $this->mergeLinks($queue, $visited, $html, $url, $host);
            }
        }

        return ['emails' => $emails, 'pages' => $pages];
    }

    /** Start page plus the usual contact paths, so a homepage with no links still yields something. */
    private function seedQueue(string $start, string $host): array
    {
        $scheme = parse_url($start, PHP_URL_SCHEME) ?: 'https';
        $root = $scheme.'://'.$host;

        $queue = [$start];
        foreach (self::PRIORITY_PATHS as $path) {
            $queue[] = $root.$path;
        }

        return $queue;
    }

    private function fetch(string $url): ?string
    {
        try {
            $res = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; RazinSoftBot/1.0)'])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $res->successful()) {
            return null;
        }

        $type = $res->header('Content-Type');
        if ($type && ! str_contains(mb_strtolower($type), 'html')) {
            return null;
        }

        return $res->body();
    }

    /**
     * Addresses in the page: mailto: links first (where a name often sits alongside), then plain
     * text, including the obfuscations sites use — "name (at) example.com".
     *
     * @return array<string, ?string>  email => name found next to it
     */
    public function extractEmails(string $html): array
    {
        $found = [];

        // mailto: links — the anchor text is frequently the person's name.
        if (preg_match_all('/<a[^>]+href=["\']mailto:([^"\'?]+)[^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $email = $this->clean($hit[1]);
                if (! $email) {
                    continue;
                }
                $text = trim(html_entity_decode(strip_tags($hit[2] ?? '')));
                // Anchor text that is just the address again is not a name.
                $name = ($text && ! str_contains($text, '@') && mb_strlen($text) <= 60) ? $text : null;
                $found[$email] ??= $name;
            }
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        // Plain addresses.
        if (preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $text, $m)) {
            foreach ($m[0] as $raw) {
                if ($email = $this->clean($raw)) {
                    $found[$email] ??= null;
                }
            }
        }

        // Obfuscated: "name (at) example (dot) com" and the [at] / {at} variants.
        $deobfuscated = preg_replace(
            ['/\s*[\(\[\{]\s*at\s*[\)\]\}]\s*/i', '/\s*[\(\[\{]\s*dot\s*[\)\]\}]\s*/i'],
            ['@', '.'],
            $text
        );
        if ($deobfuscated !== $text && preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $deobfuscated, $m)) {
            foreach ($m[0] as $raw) {
                if ($email = $this->clean($raw)) {
                    $found[$email] ??= null;
                }
            }
        }

        return $found;
    }

    /** Normalise, validate, and reject the addresses that are never people. */
    private function clean(string $raw): ?string
    {
        $email = mb_strtolower(trim(rtrim($raw, '.,;:')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            return null;
        }

        // Image filenames caught by the pattern (logo@2x.png), and placeholder domains.
        if (preg_match('/\.(png|jpe?g|gif|webp|svg|css|js)$/i', $email)) {
            return null;
        }

        $domain = explode('@', $email)[1] ?? '';
        if (in_array($domain, ['example.com', 'example.org', 'domain.com', 'email.com', 'sentry.io'], true)) {
            return null;
        }

        return $email;
    }

    /** Same-host links, contact-ish pages first. */
    private function mergeLinks(array $queue, array $visited, string $html, string $baseUrl, string $host): array
    {
        if (! preg_match_all('/<a[^>]+href=["\']([^"\'#]+)["\'][^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER)) {
            return $queue;
        }

        $priority = [];
        $normal = [];

        foreach ($m as $hit) {
            $url = $this->absolute($hit[1], $baseUrl);
            if (! $url || parse_url($url, PHP_URL_HOST) !== $host) {
                continue;   // stay on the submitted site
            }

            $ext = mb_strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            if ($ext && in_array($ext, self::SKIP_EXTENSIONS, true)) {
                continue;
            }

            $key = rtrim(strtok($url, '#'), '/');
            if (isset($visited[$key]) || in_array($url, $queue, true)) {
                continue;
            }

            $haystack = mb_strtolower($url.' '.strip_tags($hit[2] ?? ''));
            $isPriority = false;
            foreach (self::PRIORITY_HINTS as $hint) {
                if (str_contains($haystack, $hint)) {
                    $isPriority = true;
                    break;
                }
            }

            $isPriority ? $priority[] = $url : $normal[] = $url;
        }

        return array_merge($priority, $queue, $normal);
    }

    private function absolute(string $href, string $baseUrl): ?string
    {
        $href = trim($href);

        if ($href === '' || preg_match('/^(mailto|tel|javascript|data):/i', $href)) {
            return null;
        }
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        $root = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (str_starts_with($href, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $root.$href;
        }

        $dir = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $root.$dir.'/'.$href;
    }

    private function normaliseUrl(string $url): string
    {
        $url = trim($url);

        return preg_match('#^https?://#i', $url) ? $url : 'https://'.ltrim($url, '/');
    }
}
