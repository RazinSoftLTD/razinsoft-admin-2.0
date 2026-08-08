<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a page behind ANY of several permissions (`permission_any:a.view,b.view`).
 *
 * For pages that are really several sections in one — CRM settings, whose four tabs each
 * belong to a different module. Holding one tab's permission is reason enough to open the
 * page; the page itself then shows only the tabs that person holds.
 */
class EnsureAnyPermission
{
    // Variadic: Laravel splits `permission_any:a,b,c` on the commas, so the keys arrive as
    // separate arguments — a single string parameter would silently gate on the first one only.
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isPanelUser()) {
            return redirect()->route('admin.login');
        }

        $keys = array_filter(array_map('trim', $permissions));
        $allowed = collect($keys)->contains(fn ($key) => $user->hasPermission($key));

        abort_unless($allowed, 403, 'You do not have access to this section.');

        return $next($request);
    }
}
