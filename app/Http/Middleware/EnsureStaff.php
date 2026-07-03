<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Allows admin + staff into the panel. Admin-only sections use the `admin` middleware on top. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isPanelUser()) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
