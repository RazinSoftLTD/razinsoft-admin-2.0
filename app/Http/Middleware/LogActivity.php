<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Records every panel (employee/admin) request into the activity log. */
class LogActivity
{
    /** Route-name fragments that are too noisy (polling/heartbeats) to log. */
    private const SKIP = ['heartbeat', 'offline', 'presence', 'poll', 'ping', 'unread', 'activity-logs'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $user = $request->user();
            if ($user && $user->isPanelUser() && $this->loggable($request)) {
                ActivityLog::create([
                    'user_id' => $user->id,
                    'method' => $request->method(),
                    'route_name' => optional($request->route())->getName(),
                    'url' => \Illuminate\Support\Str::limit($request->fullUrl(), 990, ''),
                    'ip' => $request->ip(),
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Never let logging break the request.
        }

        return $response;
    }

    private function loggable(Request $request): bool
    {
        $name = (string) optional($request->route())->getName();
        foreach (self::SKIP as $frag) {
            if (str_contains($name, $frag)) {
                return false;
            }
        }

        return true;
    }
}
