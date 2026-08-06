<?php

namespace App\Http\Middleware;

use App\Models\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LoginThrottleMiddleware
{
    public function handle(Request $request, Closure $next, $maxAttempts = 5, $decayMinutes = 1): Response
    {
        // Get system settings for dynamic throttling
        $systemSettings = SystemSettings::first();
        $maxAttempts = $systemSettings->maximum_login_attempts ?? 5;
        
        $key = $this->resolveRequestSignature($request);

        if ($this->limiter()->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildResponse($request, $key, $maxAttempts);
        }

        $this->limiter()->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $response;
    }

    protected function resolveRequestSignature($request): string
    {
        return sha1(
            $request->method() .
            '|' . $request->server('SERVER_NAME') .
            '|' . $request->ip() .
            '|' . $request->path() .
            '|' . $request->input('email', '')
        );
    }

    protected function limiter(): RateLimiter
    {
        return app(RateLimiter::class);
    }

    protected function buildResponse(Request $request, string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter()->availableIn($key);

        $response = response()->json([
            'message' => 'Too many login attempts. Please try again later.',
            'seconds' => $retryAfter,
            'max_attempts' => $maxAttempts
        ], 429);

        return $response->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->getTimestamp(),
        ]);
    }
}
