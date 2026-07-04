<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Gate a panel section behind a permission key (e.g. `permission:invoices`). Admins pass all. */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isPanelUser()) {
            return redirect()->route('admin.login');
        }

        abort_unless($user->hasPermission($permission), 403, 'You do not have access to this section.');

        return $next($request);
    }
}
