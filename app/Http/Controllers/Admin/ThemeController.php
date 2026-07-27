<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Stores a person's light/dark choice on their account. */
class ThemeController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(array_keys(User::THEMES))],
        ]);

        // saveQuietly: this is a display preference, not something the activity log or any
        // observer should treat as the account being edited.
        $request->user()->forceFill(['theme' => $data['theme']])->saveQuietly();

        if ($request->expectsJson()) {
            return response()->json(['theme' => $data['theme']]);
        }

        return back();
    }
}
