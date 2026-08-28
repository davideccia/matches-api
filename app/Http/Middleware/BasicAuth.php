<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class BasicAuth
{
    private function matches(string $provided, string $expected): bool
    {
        return hash_equals(hash('sha256', $expected), hash('sha256', $provided));
    }

    abstract protected function configNamespace(): string;

    abstract protected function realm(): string;

    public function handle(Request $request, Closure $next): Response
    {
        $username = config($this->configNamespace().'.basic_auth_username');
        $password = config($this->configNamespace().'.basic_auth_password');

        $authorization = $request->header('Authorization', '');

        if ($username && $password && str_starts_with($authorization, 'Basic ')) {
            $decoded = base64_decode(substr($authorization, 6));
            [$providedUser, $providedPass] = array_pad(explode(':', $decoded, 2), 2, '');

            // Both comparisons run unconditionally: && would short-circuit on a
            // wrong username and leak, through timing, which half was correct.
            $usernameMatches = $this->matches($providedUser, $username);
            $passwordMatches = $this->matches($providedPass, $password);

            if ($usernameMatches && $passwordMatches) {
                return $next($request);
            }
        }

        return response('Unauthorized', 401, [
            'WWW-Authenticate' => 'Basic realm="'.$this->realm().'"',
        ]);
    }
}
