<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $plan = 'premium'): Response
    {
        $user = $request->user();

        // Skip check for guests
        if (!$user) {
            return $next($request);
        }

        // Get user's active subscription
        $subscription = $this->subscriptionService->getUserActiveSubscription($user->id);

        // Check if user has active subscription
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'يجب عليك الاشتراك في باقة بريميوم للوصول إلى هذه الميزة',
                'code' => 'SUBSCRIPTION_REQUIRED',
                'data' => [
                    'required_plan' => $plan,
                    'current_subscription' => null,
                ]
            ], 403);
        }

        // Check if subscription matches required plan
        if ($plan !== 'any' && $subscription->subscriptionPlan->name !== $plan) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الميزة تتطلب باقة ' . $plan,
                'code' => 'PLAN_UPGRADE_REQUIRED',
                'data' => [
                    'required_plan' => $plan,
                    'current_plan' => $subscription->subscriptionPlan->name,
                    'current_subscription' => $subscription,
                ]
            ], 403);
        }

        // Add subscription info to request for later use
        $request->merge(['current_subscription' => $subscription]);

        return $next($request);
    }
}
