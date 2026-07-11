<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates authenticated client (customer) API access by account status:
 *   active   → full access
 *   inactive → may stay signed in but can only reach support (everything else 403)
 *   blocked  → no access at all (403 on every request; login is already refused)
 */
class EnsureClientActive
{
    /** Paths an inactive client may still reach (support + session basics). */
    private array $allowedForInactive = [
        'api/auth/me',
        'api/auth/logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === User::ROLE_CUSTOMER) {
            if ($user->status === User::STATUS_BLOCKED) {
                return response()->json(['message' => 'Your account is blocked.'], 403);
            }

            if ($user->status === User::STATUS_INACTIVE) {
                $path = $request->path();
                $allowed = in_array($path, $this->allowedForInactive, true) || str_starts_with($path, 'api/support');
                if (! $allowed) {
                    return response()->json(['message' => 'Your account is inactive. You can only contact support.'], 403);
                }
            }
        }

        return $next($request);
    }
}
