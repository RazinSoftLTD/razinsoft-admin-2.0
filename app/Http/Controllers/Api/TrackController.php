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
        // Only record visits for authenticated clients (customers).
        $client = auth('sanctum')->user();
        if (! $client || $client->role !== User::ROLE_CUSTOMER) {
            return response()->noContent();
        }

        $data = $request->validate([
            'path' => ['required', 'string', 'max:1000'],
            'title' => ['nullable', 'string', 'max:255'],
            'referrer' => ['nullable', 'string', 'max:1000'],
        ]);

        ClientActivityLog::create([
            'client_id' => $client->id,
            'path' => Str::limit($data['path'], 990, ''),
            'title' => $data['title'] ?? null,
            'referrer' => $data['referrer'] ?? null,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'created_at' => now(),
        ]);

        return response()->noContent();
    }
}
