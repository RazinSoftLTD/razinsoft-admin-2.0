<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Best-effort country lookup from an IP address (cached per IP; never throws). */
class Geo
{
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
