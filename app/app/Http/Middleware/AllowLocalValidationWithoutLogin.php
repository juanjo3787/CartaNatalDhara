<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;

final class AllowLocalValidationWithoutLogin extends Authenticate
{
    public function handle($request, Closure $next, ...$guards)
    {
        if ($this->allowsLocalValidation($request)) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }

    private function allowsLocalValidation(Request $request): bool
    {
        $host = strtolower((string) $request->server('HTTP_HOST'));
        $host = preg_replace('/:\d+$/', '', $host);

        return (bool) config('validation.bypass_login', false)
            && app()->environment('local', 'testing')
            && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            && in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
    }
}
