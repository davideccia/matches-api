<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogViewerBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = config('log-viewer.basic_auth_username');
        $password = config('log-viewer.basic_auth_password');

        $authorization = $request->header('Authorization', '');

        if ($username && $password && str_starts_with($authorization, 'Basic ')) {
            $decoded = base64_decode(substr($authorization, 6));
            [$providedUser, $providedPass] = array_pad(explode(':', $decoded, 2), 2, '');

            if ($providedUser === $username && $providedPass === $password) {
                return $next($request);
            }
        }

        return response('Unauthorized', 401, [
            'WWW-Authenticate' => 'Basic realm="Log Viewer"',
        ]);
    }
}
