<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TapPaymentService;
use App\Services\SubscriptionService;
use App\Models\SubscriptionPlan;
use App\Models\PaymentTransaction;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TapPaymentController extends Controller
{
    protected $tapPayment;
    protected $subscriptionService;

    public function __construct(TapPaymentService $tapPayment, SubscriptionService $subscriptionService)
    {
        $this->tapPayment = $tapPayment;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Create a payment charge
     */
    public function createCharge(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'currency' => 'sometimes|string|size:3',
            'customer.first_name' => 'required|string|max:255',
            'customer.last_name' => 'sometimes|string|max:255',
            'customer.email' => 'required|email',
            'customer.phone.country_code' => 'sometimes|string|max:5',
            'customer.phone.number' => 'required|string|max:20',
            'description' => 'sometimes|string|max:255',
            'redirect_url' => 'sometimes|url',
            'post_url' => 'sometimes|url',
            'metadata' => 'sometimes|array',
            'source_id' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $chargeData = $this->tapPayment->prepareChargeData($request->all());
            $charge = $this->tapPayment->createCharge($chargeData);

            return response()->json([
                'success' => true,
                'data' => $charge,
                'message' => 'Charge created successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Tap Payment Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retrieve a charge
     */
    public function retrieveCharge($chargeId)
    {
        try {
            $charge = $this->tapPayment->retrieveCharge($chargeId);

            return response()->json([
                'success' => true,
                'data' => $charge,
                'message' => 'Charge retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Tap Retrieve Charge Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve charge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a customer
     */
    public function createCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'required|email',
            'phone.country_code' => 'sometimes|string|max:5',
            'phone.number' => 'required|string|max:20',
            'description' => 'sometimes|string|max:255',
            'metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $customer = $this->tapPayment->createCustomer($request->all());

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Customer created successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Tap Create Customer Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process a refund
     */
    public function createRefund(Request $request, $chargeId)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'currency' => 'sometimes|string|size:3',
            'reason' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:255',
            'metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $refund = $this->tapPayment->createRefund($chargeId, $request->all());

            return response()->json([
                'success' => true,
                'data' => $refund,
                'message' => 'Refund processed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Tap Refund Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get publishable key for frontend
     */
    public function getPublishableKey()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'publishable_key' => $this->tapPayment->getPublishableKey()
            ],
            'message' => 'Publishable key retrieved successfully'
        ]);
    }

    /**
     * Handle Tap webhook
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        
        Log::info('Tap Webhook received', ['payload' => $payload]);

        try {
            $data = json_decode($payload, true);
            
            if (isset($data['event'])) {
                switch ($data['event']) {
                    case 'CHARGE_INITIALIZED':
                        $this->handleChargeInitialized($data);
                        break;
                    case 'CHARGE_AUTHORIZED':
                        $this->handleChargeAuthorized($data);
                        break;
                    case 'CHARGE_CAPTURED':
                        $this->handleChargeCaptured($data);
                        break;
                    case 'CHARGE_FAILED':
                        $this->handleChargeFailed($data);
                        break;
                    case 'REFUND_INITIALIZED':
                        $this->handleRefundInitialized($data);
                        break;
                    case 'REFUND_CAPTURED':
                        $this->handleRefundCaptured($data);
                        break;
                }
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Webhook processing error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Handle charge initialized
     */
    private function handleChargeInitialized($data)
    {
        $charge = $data['charge'];
        
        // Update transaction status to pending
        PaymentTransaction::where('tap_charge_id', $charge['id'])
            ->update(['status' => 'pending']);
    }

    /**
     * Handle charge authorized
     */
    private function handleChargeAuthorized($data)
    {
        $charge = $data['charge'];
        
        Log::info('Payment authorized', [
            'charge_id' => $charge['id'],
            'amount' => $charge['amount'],
            'currency' => $charge['currency']
        ]);
    }

    /**
     * Handle charge captured (successful payment)
     */
    private function handleChargeCaptured($data)
    {
        $charge = $data['charge'];
        
        Log::info('Payment captured', [
            'charge_id' => $charge['id'],
            'amount' => $charge['amount'],
            'currency' => $charge['currency'],
            'status' => $charge['status']
        ]);

        // Find transaction by charge ID
        $transaction = PaymentTransaction::where('tap_charge_id', $charge['id'])->first();

        if ($transaction) {
            // Update transaction status
            $transaction->update([
                'status' => 'completed',
                'paid_at' => now(),
                'tap_response' => $charge
            ]);

            // If this is a subscription payment, activate subscription
            if ($transaction->type === 'subscription') {
                $result = $this->subscriptionService->activateSubscription($charge['id']);
                
                if (!$result['success']) {
                    Log::error('Failed to activate subscription', [
                        'charge_id' => $charge['id'],
                        'error' => $result['message']
                    ]);
                }
            } else {
                // Handle regular payment
                $this->handleSuccessfulPayment($data);
            }
        } else {
            Log::warning('Transaction not found for charge', [
                'charge_id' => $charge['id']
            ]);
        }
    }

    /**
     * Handle charge failed
     */
    private function handleChargeFailed($data)
    {
        $charge = $data['charge'];
        
        Log::error('Payment failed', [
            'charge_id' => $charge['id'],
            'amount' => $charge['amount'],
            'currency' => $charge['currency'],
            'status' => $charge['status'],
            'failure_reason' => $charge['response']['message'] ?? 'Unknown error'
        ]);

        // Update transaction status
        PaymentTransaction::where('tap_charge_id', $charge['id'])
            ->update([
                'status' => 'failed',
                'tap_response' => $charge
            ]);
    }

    /**
     * Handle refund initialized
     */
    private function handleRefundInitialized($data)
    {
        $refund = $data['refund'];
        
        Log::info('Refund initialized', [
            'refund_id' => $refund['id'],
            'charge_id' => $refund['charge_id'],
            'amount' => $refund['amount']
        ]);
    }

    /**
     * Handle refund captured
     */
    private function handleRefundCaptured($data)
    {
        $refund = $data['refund'];
        
        Log::info('Refund captured', [
            'refund_id' => $refund['id'],
            'charge_id' => $refund['charge_id'],
            'amount' => $refund['amount']
        ]);

        // Update transaction with refund info
        PaymentTransaction::where('tap_charge_id', $refund['charge_id'])
            ->update([
                'tap_refund_id' => $refund['id'],
                'refunded_amount' => $refund['amount'],
                'refunded_at' => now(),
                'status' => 'refunded'
            ]);
    }

    /**
     * Handle successful payment
     */
    private function handleSuccessfulPayment($data)
    {
        $charge = $data['charge'];
        
        Log::info('Payment successful', [
            'charge_id' => $charge['id'],
            'amount' => $charge['amount'],
            'currency' => $charge['currency'],
            'status' => $charge['status']
        ]);

        // Update your database, send confirmation emails, etc.
        // Example: Update order status, send receipt, etc.
    }

    /**
     * Handle failed payment
     */
    private function handleFailedPayment($data)
    {
        $charge = $data['charge'];
        
        Log::error('Payment failed', [
            'charge_id' => $charge['id'],
            'amount' => $charge['amount'],
            'currency' => $charge['currency'],
            'status' => $charge['status'],
            'failure_reason' => $charge['response']['message'] ?? 'Unknown error'
        ]);

        // Update your database, notify customer, etc.
    }

    /**
     * Create subscription payment
     */
    public function createSubscriptionPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
            'customer.first_name' => 'required|string|max:255',
            'customer.last_name' => 'sometimes|string|max:255',
            'customer.email' => 'required|email',
            'customer.phone.country_code' => 'sometimes|string|max:5',
            'customer.phone.number' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $supplier = $request->user();
            $plan = SubscriptionPlan::findOrFail($request->plan_id);

            $result = $this->subscriptionService->createSubscriptionPayment(
                $supplier,
                $plan,
                $request->customer
            );

            if ($result['success']) {
                $charge = $result['charge'];
                
                // Check if charge has errors
                if (isset($charge['errors']) && !empty($charge['errors'])) {
                    Log::error('Tap charge creation returned errors', ['errors' => $charge['errors']]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment creation failed',
                        'errors' => $charge['errors']
                    ], 500);
                }
                
                // Try multiple possible locations for payment URL
                $paymentUrl = null;
                if (isset($charge['transaction']['url'])) {
                    $paymentUrl = $charge['transaction']['url'];
                } elseif (isset($charge['redirect']['url'])) {
                    $paymentUrl = $charge['redirect']['url'];
                } elseif (isset($charge['url'])) {
                    $paymentUrl = $charge['url'];
                } elseif (isset($charge['data']['transaction']['url'])) {
                    $paymentUrl = $charge['data']['transaction']['url'];
                }
                
                // If payment URL is not found, return an error
                if (!$paymentUrl) {
                    Log::error('Payment URL not found in charge response', [
                        'charge_keys' => array_keys($charge),
                        'charge_structure' => $charge,
                        'transaction_id' => $result['transaction']->id ?? null
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to generate payment URL. Please try again or contact support.',
                        'data' => [
                            'transaction' => $result['transaction'],
                            'charge' => $charge,
                        ]
                    ], 500);
                }
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'transaction' => $result['transaction'],
                        'charge' => $charge,
                        'payment_url' => $paymentUrl,
                    ],
                    'message' => 'Subscription payment created successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Payment creation failed'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Subscription payment error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle subscription success callback
     */
    public function subscriptionSuccess(Request $request)
    {
        $transactionId = $request->query('transaction_id');
        $tapId = $request->query('tap_id');

        try {
            $transaction = null;
            $subscription = null;
            $status = 'pending';
            $message = 'تم الاشتراك بنجاح';

            // Activate subscription if tap_id is provided
            if ($tapId) {
                $result = $this->subscriptionService->activateSubscription($tapId);
                
                if ($result['success']) {
                    $subscription = $result['subscription'];
                    $transaction = $result['transaction'];
                    $status = 'completed';
                } else {
                    // Even if activation fails, try to get transaction
                    if ($transactionId) {
                        $transaction = PaymentTransaction::find($transactionId);
                    }
                    $status = 'failed';
                    $message = $result['message'] ?? 'فشل تفعيل الاشتراك';
                }
            } elseif ($transactionId) {
                $transaction = PaymentTransaction::findOrFail($transactionId);
                $status = $transaction->status;
                
                // If transaction has tap_charge_id but status is still pending, check with Tap API
                if ($transaction->tap_charge_id && $transaction->status === 'pending') {
                    try {
                        $charge = $this->tapPayment->retrieveCharge($transaction->tap_charge_id);
                        
                        // Update transaction based on Tap API response
                        if (isset($charge['status'])) {
                            if ($charge['status'] === 'CAPTURED' || $charge['status'] === 'AUTHORIZED') {
                                // Payment successful, activate subscription
                                $result = $this->subscriptionService->activateSubscription($transaction->tap_charge_id);
                                
                                if ($result['success']) {
                                    $subscription = $result['subscription'];
                                    $transaction = $result['transaction'];
                                    $status = 'completed';
                                } else {
                                    // Update transaction status even if activation fails
                                    $transaction->update([
                                        'status' => 'completed',
                                        'paid_at' => now(),
                                        'tap_response' => $charge
                                    ]);
                                    $status = 'completed';
                                }
                            } elseif ($charge['status'] === 'FAILED' || $charge['status'] === 'ABANDONED') {
                                $transaction->update([
                                    'status' => 'failed',
                                    'tap_response' => $charge
                                ]);
                                $status = 'failed';
                                $message = 'فشل الدفع';
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to retrieve charge from Tap API', [
                            'charge_id' => $transaction->tap_charge_id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                // Check if subscription exists for this transaction
                if ($transaction->status === 'completed') {
                    $subscription = \App\Models\UserSubscription::where('tap_charge_id', $transaction->tap_charge_id)
                        ->first();
                }
            }

            // Build redirect URL with parameters
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $redirectUrl = $frontendUrl . '/subscription/success?' . http_build_query([
                'transaction_id' => $transaction?->id,
                'tap_id' => $tapId,
                'status' => $status,
                'success' => $status === 'completed' ? '1' : '0',
            ]);

            // Redirect to frontend success page
            return redirect($redirectUrl);

        } catch (\Exception $e) {
            Log::error('Subscription success callback error: ' . $e->getMessage());
            
            // Even on error, redirect to frontend with error status
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $redirectUrl = $frontendUrl . '/subscription/success?' . http_build_query([
                'status' => 'error',
                'success' => '0',
                'error' => 'حدث خطأ أثناء معالجة الاشتراك',
            ]);
            
            return redirect($redirectUrl);
        }
    }

    /**
     * Get available subscription plans
     */
    public function getSubscriptionPlans()
    {
        try {
            $plans = $this->subscriptionService->getAvailablePlans();

            return response()->json([
                'success' => true,
                'data' => $plans,
                'message' => 'Subscription plans retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Get subscription plans error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's current subscription
     */
    public function getUserSubscription(Request $request)
    {
        try {
            $user = $request->user();
            $subscription = $this->subscriptionService->getUserActiveSubscription($user->id);

            return response()->json([
                'success' => true,
                'data' => $subscription,
                'message' => 'User subscription retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Get user subscription error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's subscription history
     */
    public function getUserSubscriptionHistory(Request $request)
    {
        try {
            $user = $request->user();
            $history = $this->subscriptionService->getUserSubscriptionHistory($user->id);

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'Subscription history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Get subscription history error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's payment transactions
     */
    public function getUserTransactions(Request $request)
    {
        try {
            $user = $request->user();
            
            $query = PaymentTransaction::where('user_id', $user->id)
                ->with('subscriptionPlan')
                ->orderBy('created_at', 'desc');

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
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            $transactions = $query->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'message' => 'Payment transactions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Get user transactions error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
