<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Receives page-visit beacons from the website and logs them for logged-in clients. */
class TrackController extends Controller
{
    public function visit(Request $request)
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:1000'],
            'title' => ['nullable', 'string', 'max:255'],
            'referrer' => ['nullable', 'string', 'max:1000'],
            'error' => ['nullable', 'integer', 'min:400', 'max:599'], // error-page views (404 …)
            'tz' => ['nullable', 'string', 'max:64'],                 // browser timezone (Asia/Dhaka …)
        ]);

        // Attach the client if a valid client token is present; otherwise it's an
        // anonymous ("unknown") visitor. Both are recorded, with the visit's country.
        $user = auth('sanctum')->user();
        $clientId = ($user && $user->role === User::ROLE_CUSTOMER) ? $user->id : null;
        // Cloudflare's authoritative client IP first, then the trusted-proxy resolved IP.
        $ip = $request->header('CF-Connecting-IP') ?: $request->ip();

        // Don't pollute the analytics with crawler traffic — Meta/Google render bots
        // execute JS with real-browser user agents, so filter by IP range/hosting too.
        if (\App\Support\Geo::isBot($ip, $request->userAgent())) {
            return response()->noContent();
        }

        // Strip the query string so /blog/x?utm=… groups with /blog/x in reports.
        $path = strtok($data['path'], '?') ?: $data['path'];

        // Country, most-trustworthy first:
        //  1. the browser's timezone — where the device REALLY is (VPN/proxy-proof)
        //  2. Cloudflare's edge-resolved CF-IPCountry
        //  3. an IP lookup as the last resort
        $country = \App\Support\Geo::countryFromTimezone($data['tz'] ?? null)
            ?? \App\Support\Geo::countryFromCode($request->header('CF-IPCountry'))
            ?? \App\Support\Geo::country($ip);

        $log = ClientActivityLog::create([
            'client_id' => $clientId,
            'country' => $country,
            'path' => Str::limit($path, 990, ''),
            'title' => $data['title'] ?? null,
            'error_code' => $data['error'] ?? null,
            'referrer' => $data['referrer'] ?? null,
            'ip' => $ip,
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'created_at' => now(),
        ]);

        // Nudge any open Client Activity screens to refresh — never break the beacon.
        try {
            event(new \App\Events\ClientVisitLogged($log->id));
        } catch (\Throwable $e) {
            // broadcasting is best-effort
        }

        return response()->noContent();
    }
}
