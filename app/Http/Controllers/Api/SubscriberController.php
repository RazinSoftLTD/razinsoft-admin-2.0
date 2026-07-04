<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    /** Public: capture a blog "Follow" subscription (idempotent by email). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:60'],
            'article' => ['nullable', 'string', 'max:255'],
        ]);

        Subscriber::updateOrCreate(
            ['email' => strtolower($data['email'])],
            [
                'name' => $data['name'] ?? null,
                'source' => $data['source'] ?? 'blog',
                'article' => $data['article'] ?? null,
                'is_active' => true,
            ],
        );

        return response()->json(['message' => 'Subscribed. Thanks for following!'], 201);
    }
}
