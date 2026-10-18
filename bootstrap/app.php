<?php
// backend/bootstrap/app.php

use App\Http\Middleware\EnsureActiveAdmin;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\LogApiRequest;
use App\Models\ErrorLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // No web login page: guests get a 401 JSON, never a redirect
        $middleware->redirectGuestsTo(fn () => null);

        // "super" = only the developer account, "active" = account not deactivated
        $middleware->alias([
            'super' => EnsureSuperAdmin::class,
            'active' => EnsureActiveAdmin::class,
        ]);

        // Traffic statistics for the developer dashboard
        $middleware->api(append: [LogApiRequest::class]);

        // The only caller is the Next.js server, which forwards the visitor's IP in
        // X-Forwarded-For (see clientIp() in frontend/lib/server/backend.ts).
        // Trusting that server makes request()->ip() the visitor, so throttle:5,1
        // limits each visitor instead of locking everybody out after 5 attempts.
        // Set TRUSTED_PROXIES to the Next server's IP (comma separated) in production.
        $middleware->trustProxies(
            at: array_filter(explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))),
            headers: Request::HEADER_X_FORWARDED_FOR,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always answer JSON on the API, even without an Accept header
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $e) => $request->is('api/*') || $request->expectsJson()
        );

        // In production, a missing model or route never reveals class names or paths
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') && ! config('app.debug')) {
                return response()->json(['message' => 'Introuvable.'], 404);
            }
        });

        // Every unexpected error (500) is kept for the developer dashboard.
        // Validation, auth and 404 errors are not "reported" by Laravel, so they never land here.
        $exceptions->report(function (\Throwable $e) {
            try {
                $request = request();
                $cli = app()->runningInConsole();

                // A database error message can contain SQL: keep only the driver's short message
                $message = $e instanceof QueryException
                    ? ($e->getPrevious()?->getMessage() ?? 'Database error')
                    : $e->getMessage();

                $base = base_path().DIRECTORY_SEPARATOR;

                // File:line of the first frames only. No arguments, so no secrets.
                $trace = collect($e->getTrace())
                    ->take(8)
                    ->map(fn ($f) => str_replace($base, '', (string) ($f['file'] ?? '?')).':'.($f['line'] ?? 0))
                    ->implode("\n");

                ErrorLog::create([
                    'class' => substr($e::class, 0, 190),
                    'message' => mb_substr($message, 0, 500),
                    'location' => substr(str_replace($base, '', $e->getFile()).':'.$e->getLine(), 0, 250),
                    'method' => $cli ? null : substr($request->method(), 0, 8),
                    'path' => $cli ? null : substr($request->path(), 0, 150),
                    'admin_id' => $cli ? null : $request->user('sanctum')?->id,
                    'trace' => $trace,
                ]);

                if (random_int(1, 50) === 1) {
                    ErrorLog::where('created_at', '<', now()->subDays(30))->delete();
                }
            } catch (\Throwable) {
                // never fail while reporting a failure
            }
        });
    })->create();