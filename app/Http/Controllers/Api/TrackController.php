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
        ]);

        // Attach the client if a valid client token is present; otherwise it's an
        // anonymous ("unknown") visitor. Both are recorded, with the visit's country.
        $user = auth('sanctum')->user();
        $clientId = ($user && $user->role === User::ROLE_CUSTOMER) ? $user->id : null;
        $ip = $request->ip();

        ClientActivityLog::create([
            'client_id' => $clientId,
            'country' => \App\Support\Geo::country($ip),
            'path' => Str::limit($data['path'], 990, ''),
            'title' => $data['title'] ?? null,
            'referrer' => $data['referrer'] ?? null,
            'ip' => $ip,
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'created_at' => now(),
        ]);

        return response()->noContent();
    }
}
