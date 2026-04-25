<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\SystemSettings;
use Illuminate\Http\Request;
use App\Models\Supplier;
use Laravel\Sanctum\PersonalAccessToken;

class DynamicSessionTimeout
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Check if user is a supplier - try multiple ways to be sure
        $isSupplier = false;
        if ($user) {
            // Using getMorphClass() is the most reliable way as Sanctum uses it for tokenable_type
            if (method_exists($user, 'getMorphClass') && $user->getMorphClass() === 'App\Models\Supplier') {
                $isSupplier = true;
            } elseif ($user instanceof Supplier) {
                $isSupplier = true;
            } elseif (isset($user->role) && $user->role === 'supplier') {
                $isSupplier = true;
            }
        }

        if ($isSupplier && $request->bearerToken()) {
            $token = PersonalAccessToken::findToken($request->bearerToken());
            
            if ($token) {
                $settings = SystemSettings::first();
                $timeoutMinutes = $settings ? (int) $settings->session_timeout_minutes : 15;
                
                // Use last_used_at, or created_at if it hasn't been used yet
                $lastActivity = $token->last_used_at ?: $token->created_at;

                // Log every check for debugging (can be removed later)
                \Log::debug('SessionTimeout Check:', [
                    'user_id' => $user->id,
                    'user_type' => get_class($user),
                    'last_activity' => $lastActivity?->toDateTimeString(),
                    'timeout_minutes' => $timeoutMinutes,
                    'now' => now()->toDateTimeString(),
                ]);
                
                if ($lastActivity && $lastActivity->addMinutes($timeoutMinutes)->isPast()) {
                    \Log::info('Session Timed Out for user: ' . $user->id);
                    $token->delete();
                    return response()->json([
                        'message' => 'Session timed out.',
                        'code' => 'SESSION_TIMEOUT'
                    ], 401);
                }
                
                // Update last_used_at to extend session
                $token->forceFill(['last_used_at' => now()])->save();
            }
        }

        return $next($request);
    }
}
