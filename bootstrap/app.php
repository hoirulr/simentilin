<?php

use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('TRUSTED_PROXIES', '*');
        if ($trustedProxies === '*' || empty($trustedProxies)) {
            $middleware->trustProxies(at: '*');
        } else {
            $middleware->trustProxies(at: array_map('trim', explode(',', $trustedProxies)));
        }

        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'role' => EnsureUserRole::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->stopIgnoring([
            \Illuminate\Auth\Access\AuthorizationException::class,
            \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class,
        ]);

        $exceptions->report(function (\Illuminate\Auth\Access\AuthorizationException $e) {
            \Illuminate\Support\Facades\Log::warning('security.authorization_failed', [
                'user_id' => auth()->id(),
                'role' => auth()->user()?->role?->value ?? auth()->user()?->role,
                'ip' => request()->ip(),
                'path' => request()->path(),
                'message' => $e->getMessage(),
            ]);
        });

        $exceptions->report(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            \Illuminate\Support\Facades\Log::warning('security.access_denied', [
                'user_id' => auth()->id(),
                'role' => auth()->user()?->role?->value ?? auth()->user()?->role,
                'ip' => request()->ip(),
                'path' => request()->path(),
                'message' => $e->getMessage(),
            ]);
        });
    })->create();
