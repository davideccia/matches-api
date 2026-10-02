<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogApiRequest
{
    private const STARTED_AT_ATTRIBUTE = 'api_request_log_started_at';

    /**
     * Route templates never logged: the public logo is polled by email
     * clients' image proxies, and reading the audit log must not grow it.
     */
    private const EXCLUDED_ROUTE_URIS = [
        'api/public/settings/logo',
        'api/admin/api_request_logs',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::STARTED_AT_ATTRIBUTE, hrtime(true));

        return $next($request);
    }

    /**
     * Runs after the response is sent, so logging adds no latency. A failing
     * write is reported but never surfaces to the caller.
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($request->user()?->superadmin ?? false) {
            return;
        }

        $route = $request->route();

        if ($route === null || in_array($route->uri(), self::EXCLUDED_ROUTE_URIS, true)) {
            return;
        }

        $startedAt = $request->attributes->get(self::STARTED_AT_ATTRIBUTE, hrtime(true));

        try {
            ApiRequestLog::create([
                'user_id' => $request->user()?->getAuthIdentifier(),
                'method' => $request->method(),
                'path' => $route->uri(),
                'route_name' => $route->getName(),
                'status' => $response->getStatusCode(),
                'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
