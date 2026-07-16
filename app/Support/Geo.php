<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Best-effort country lookup from an IP address (cached per IP; never throws). */
class Geo
{
    /**
     * Country name from a 2-letter ISO code — used with Cloudflare's CF-IPCountry
     * header, which is resolved at the edge from the REAL connecting IP and is far
     * more accurate than free IP-lookup APIs (handles VPN/private-relay ranges too).
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

    public static function country(?string $ip): ?string
    {
        if (! $ip || $ip === '127.0.0.1' || $ip === '::1'
            || Str::startsWith($ip, ['192.168.', '10.', '172.16.', '172.17.', '172.18.', '172.19.', '172.2', '172.3', 'fc', 'fd'])) {
            return null; // localhost / private range
        }

        return Cache::remember("geo:country:{$ip}", now()->addDays(30), function () use ($ip) {
            try {
                $res = Http::timeout(2)->get("http://ip-api.com/json/{$ip}", ['fields' => 'status,country']);
                if ($res->ok() && $res->json('status') === 'success') {
                    return $res->json('country') ?: null;
                }
            } catch (\Throwable $e) {
                // ignore — geolocation is optional
            }

            return null;
        });
    }
}
