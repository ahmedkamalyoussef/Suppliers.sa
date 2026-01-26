<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use App\Models\UserSubscription;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Get all subscriptions
     */
    public function index(Request $request)
    {
        try {
            $query = UserSubscription::with(['user', 'subscriptionPlan']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by plan
            if ($request->has('plan_id')) {
                $query->where('subscription_plan_id', $request->plan_id);
            }

            // Search by user email or name
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            }

            $subscriptions = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'message' => 'Subscriptions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Get subscriptions error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscriptions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription statistics
     */
    public function statistics()
    {
        try {
            $stats = $this->subscriptionService->getSubscriptionStatistics();
            
            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Subscription statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Get subscription statistics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment transactions
     */
    public function transactions(Request $request)
    {
        try {
            $query = PaymentTransaction::with(['user', 'subscriptionPlan']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('paid_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('paid_at', '<=', $request->end_date);
            }

            // Search by user email or charge ID
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%");
                })->orWhere('tap_charge_id', 'like', "%{$search}%");
            }

            $transactions = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'message' => 'Transactions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Get transactions error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment statistics
     */
    public function paymentStatistics(Request $request)
    {
        try {
            $startDate = $request->start_date ? date('Y-m-d', strtotime($request->start_date)) : null;
            $endDate = $request->end_date ? date('Y-m-d', strtotime($request->end_date)) : null;
            
            $stats = $this->subscriptionService->getPaymentStatistics($startDate, $endDate);
            
            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Payment statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Get payment statistics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription plans (admin view)
     */
    public function plans()
    {
        try {
            $plans = SubscriptionPlan::withCount(['userSubscriptions' => function ($query) {
                $query->where('status', 'active');
            }])->orderBy('sort_order')->orderBy('price')->get();

            return response()->json([
                'success' => true,
                'data' => $plans,
                'message' => 'Subscription plans retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Get plans error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create subscription plan
     */
    public function createPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:subscription_plans,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'billing_cycle' => 'required|in:monthly,yearly',
            'duration_months' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $plan = SubscriptionPlan::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $plan,
                'message' => 'Subscription plan created successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Create plan error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update subscription plan
     */
    public function updatePlan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:subscription_plans,name,' . $id,
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'billing_cycle' => 'required|in:monthly,yearly',
            'duration_months' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $plan = SubscriptionPlan::findOrFail($id);
            $plan->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $plan,
                'message' => 'Subscription plan updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Update plan error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel user subscription
     */
    public function cancelSubscription($id)
    {
        try {
            $result = $this->subscriptionService->cancelSubscription($id);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['subscription'],
                    'message' => 'Subscription cancelled successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to cancel subscription'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Cancel subscription error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get monthly revenue chart data
     */
    public function monthlyRevenue()
    {
        try {
            $revenue = PaymentTransaction::completed()
                ->where('type', 'subscription')
                ->whereYear('paid_at', now()->year)
                ->selectRaw('MONTH(paid_at) as month, SUM(amount) as total')
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Fill missing months with 0
            $monthlyData = [];
            for ($i = 1; $i <= 12; $i++) {
                $monthData = $revenue->where('month', $i)->first();
                $monthlyData[] = [
                    'month' => $i,
                    'month_name' => date('F', mktime(0, 0, 0, $i, 1)),
                    'total' => $monthData ? $monthData->total : 0,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $monthlyData,
                'message' => 'Monthly revenue data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Get monthly revenue error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve monthly revenue',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
