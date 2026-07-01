<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SearchLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    /** Record one committed search (not per keystroke). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'term' => ['required', 'string', 'min:2', 'max:100'],
            'results_count' => ['nullable', 'integer', 'min:0'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);

        SearchLog::create([
            'term' => Str::lower(trim($data['term'])),
            'results_count' => $data['results_count'] ?? 0,
            'source' => $data['source'] ?? 'products',
            'user_id' => optional($request->user('sanctum'))->id,
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true], 201);
    }
}
