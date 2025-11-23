<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckSupplierPlanLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $relation
     * @param  int  $limit
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $relation, int $limit = 8)
    {
        try {
            $supplier = $request->user();

            if (!$supplier) {
                Log::error('No authenticated user in CheckSupplierPlanLimit middleware');
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // Admins bypass the limit
            if (method_exists($supplier, 'isAdmin') && $supplier->isAdmin()) {
                return $next($request);
            }

            // Premium suppliers bypass the limit
            if (isset($supplier->plan) && $supplier->plan !== 'Basic') {
                return $next($request);
            }

            // Check if the relation exists
            if (!method_exists($supplier, $relation)) {
                Log::error("Invalid relation '{$relation}' in CheckSupplierPlanLimit middleware");
                return $next($request);
            }

            $count = $supplier->$relation()->count();

            if ($count >= $limit) {
                return response()->json([
                    'success' => false,
                    'message' => "You have reached the maximum number of " . str_replace('_', ' ', $relation) . " ({$count}/{$limit}) for your plan. Please upgrade to add more.",
                    'current_count' => $count,
                    'limit' => $limit
                ], 403);
            }

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Error in CheckSupplierPlanLimit: ' . $e->getMessage(), [
                'exception' => $e,
                'relation' => $relation ?? null,
                'limit' => $limit ?? null,
                'user_id' => $supplier->id ?? null
            ]);

            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while checking plan limits',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ], 500);
            }

            return $next($request); // fail open in production
        }
    }
}
