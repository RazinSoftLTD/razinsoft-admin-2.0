<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAnyPermission;
use App\Http\Middleware\EnsureClientActive;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\LogActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(__DIR__.'/../routes/admin.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Cloudflare + nginx: read the real client IP from the forwarded headers
        // so request()->ip() (and geolocation) reflect the actual visitor, not the proxy.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'staff' => EnsureStaff::class,
            'permission' => EnsurePermission::class,
            'permission_any' => EnsureAnyPermission::class,
            'client.active' => EnsureClientActive::class,
            'log.activity' => LogActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
