<?php

namespace App\Providers;

use App\Models\SystemSettings;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Middleware\ThrottleRequests;

class ThrottleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Extend the throttle middleware to use dynamic settings
        Route::macro('throttleWithSettings', function ($name, $decayMinutes = 1) {
            return function ($request, $next) use ($name, $decayMinutes) {
                // Get system settings for dynamic throttling
                $systemSettings = SystemSettings::first();
                $maxAttempts = $systemSettings->maximum_login_attempts ?? 5;
                
                $limiter = app(RateLimiter::class);
                $key = $name . ':' . sha1($request->ip() . '|' . $request->input('email', ''));
                
                if ($limiter->tooManyAttempts($key, $maxAttempts)) {
                    $response = response()->json([
                        'message' => 'Too many login attempts. Please try again later.',
                        'seconds' => $limiter->availableIn($key),
                        'max_attempts' => $maxAttempts
                    ], 429);
                    
                    return $response->withHeaders([
                        'Retry-After' => $limiter->availableIn($key),
                        'X-RateLimit-Limit' => $maxAttempts,
                        'X-RateLimit-Remaining' => 0,
                        'X-RateLimit-Reset' => now()->addSeconds($limiter->availableIn($key))->getTimestamp(),
                    ]);
                }
                
                $limiter->hit($key, $decayMinutes * 60);
                
                return $next($request);
            };
        });
    }
}
