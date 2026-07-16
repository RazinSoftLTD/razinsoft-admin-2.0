<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Visitor country + bot detection helpers (cached; never throw). */
class Geo
{
    /** Crawler / render-farm IP prefixes (Meta, Google) that masquerade as real browsers. */
    private const BOT_IP_PREFIXES = [
        '2a03:2880', '31.13.', '66.220.', '69.63.', '69.171.', '157.240.', '173.252.', '179.60.19', '185.60.21', // Meta
        '66.249.', '2001:4860', // Google render/crawl
        '40.77.', '207.46.', '157.55.', // Bing
    ];

    /**
     * Country from the visitor's BROWSER TIMEZONE (e.g. Asia/Dhaka → Bangladesh).
     * This reflects where the device actually is — immune to VPNs, relays and
     * proxies that fool IP-based lookups. UTC/Etc zones carry no location info.
     */
    public static function countryFromTimezone(?string $tz): ?string
    {
        $tz = trim((string) $tz);
        if ($tz === '' || strcasecmp($tz, 'UTC') === 0 || Str::startsWith($tz, ['Etc/', 'GMT'])) {
            return null;
        }
        try {
            $loc = (new \DateTimeZone($tz))->getLocation();
            if (! empty($loc['country_code']) && $loc['country_code'] !== '??') {
                return self::countryFromCode($loc['country_code']);
            }
        } catch (\Throwable $e) {
            // unknown zone string — fall through
        }

        return null;
    }

    /**
     * Country name from a 2-letter ISO code — used with Cloudflare's CF-IPCountry
     * header (resolved at the edge from the real connecting IP).
     */
    public static function countryFromCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));
        // XX = unknown, T1 = Tor — not real countries.
        if ($code === '' || strlen($code) !== 2 || in_array($code, ['XX', 'T1'], true)) {
            return null;
        }

        static $map = null;
        $map ??= collect(config('countries', []))->keyBy('code')->map(fn ($c) => $c['name']);

        return $map[$code] ?? (class_exists(\Locale::class) ? (\Locale::getDisplayRegion('-'.$code, 'en') ?: null) : null);
    }

    /** Country via IP lookup (last resort — least reliable). */
    public static function country(?string $ip): ?string
    {
        return self::meta($ip)['country'] ?? null;
    }

    /**
     * Is this visit from a bot / crawler / datacenter rather than a human?
     * Checks the user-agent, known crawler IP ranges (Meta's link-preview
     * renderers use real-browser UAs), and the IP's hosting/proxy flags.
     */
    public static function isBot(?string $ip, ?string $userAgent): bool
    {
        $ua = (string) $userAgent;
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|meta-external|whatsapp|telegram|skype|headless|phantom|lighthouse|pingdom|uptime|monitor|scrap|python|curl|wget|http/i', $ua)) {
            return true;
        }
        foreach (self::BOT_IP_PREFIXES as $prefix) {
            if ($ip && str_starts_with($ip, $prefix)) {
                return true;
            }
        }

        // Datacenter / proxy egress (best-effort, cached per IP).
        $meta = self::meta($ip);

        return (bool) ($meta['hosting'] ?? false) || (bool) ($meta['proxy'] ?? false);
    }

    /** Cached IP metadata: ['country' => ?, 'hosting' => bool, 'proxy' => bool]. */
    private static function meta(?string $ip): array
    {
        if (! $ip || $ip === '127.0.0.1' || $ip === '::1'
            || Str::startsWith($ip, ['192.168.', '10.', '172.16.', '172.17.', '172.18.', '172.19.', '172.2', '172.3', 'fc', 'fd'])) {
            return ['country' => null, 'hosting' => false, 'proxy' => false];
        }

        return Cache::remember("geo:meta:{$ip}", now()->addDays(30), function () use ($ip) {
            try {
                $res = Http::timeout(2)->get("http://ip-api.com/json/{$ip}", ['fields' => 'status,country,hosting,proxy']);
                if ($res->ok() && $res->json('status') === 'success') {
                    return [
                        'country' => $res->json('country') ?: null,
                        'hosting' => (bool) $res->json('hosting'),
                        'proxy' => (bool) $res->json('proxy'),
                    ];
                }
            } catch (\Throwable $e) {
                // geolocation is optional
            }

            return ['country' => null, 'hosting' => false, 'proxy' => false];
        });
    }
}
