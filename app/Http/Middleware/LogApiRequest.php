<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records one row per API request (method, route pattern, status, duration, IP).
 * It writes in terminate(), after the response is sent, so visitors never wait for it.
 * No body, header or query string is stored. A failure here never breaks a request.
 */
class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $route = $request->route()?->uri() ?? 'unmatched';

            // The developer dashboard must not fill its own statistics
            if (str_starts_with($route, 'api/admin/system')) {
                return;
            }

            $started = (float) $request->attributes->get('_started_at', microtime(true));

            RequestLog::create([
                'method' => substr($request->method(), 0, 8),
                'route' => substr($route, 0, 150),
                'status' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'ip' => $request->ip(),
                'admin_id' => $request->user('sanctum')?->id,
            ]);

            // Keep 14 days (cleaned on about 1 request out of 200)
            if (random_int(1, 200) === 1) {
                RequestLog::where('created_at', '<', now()->subDays(14))->delete();
            }
        } catch (\Throwable) {
            // monitoring must never take the API down
        }
    }
}