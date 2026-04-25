<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Supplier;
use App\Services\TapPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected $tapPaymentService;

    public function __construct(TapPaymentService $tapPaymentService)
    {
        $this->tapPaymentService = $tapPaymentService;
    }

    /**
     * Create a new payment
     */
    public function createPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email',
            'phone_number' => 'required|string|max:20',
            'phone_country_code' => 'nullable|string|max:5',
            'description' => 'nullable|string|max:500',
            'order_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get authenticated supplier
            $supplier = $request->user();
            if (!$supplier) {
                Log::error('Payment creation: Supplier not authenticated');
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier not authenticated'
                ], 401);
            }

            Log::info('Payment creation: Authenticated supplier', ['supplier_id' => $supplier->id, 'email' => $supplier->email]);

            // Extract plan_id from order_id if exists
            $planId = null;
            if ($request->order_id && preg_match('/plan_(\d+)_/', $request->order_id, $matches)) {
                $planId = (int) $matches[1];
            }

            // Create payment record first
            $payment = Payment::create([
                'supplier_id' => $supplier->id, // Use actual authenticated supplier ID
                'amount' => $request->amount,
                'currency' => 'SAR',
                'status' => 'INITIATED',
                'is_paid' => false,
                'order_id' => $request->order_id,
            ]);

            // Prepare payment data for Tap
            $paymentData = [
                'amount' => $request->amount,
                'currency' => 'SAR',
                'customer' => [
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name ?? '',
                    'email' => $request->email,
                    'phone' => [
                        'country_code' => $request->phone_country_code ?? '966',
                        'number' => $request->phone_number,
                    ],
                ],
                'source' => [
                    'id' => 'src_all'
                ],
                'redirect' => [
                    'url' => env('FRONTEND_URL', env('APP_URL')) . '/payment-complete'
                ],
                'post' => [
                    'url' => env('APP_URL') . '/api/payment/webhook'
                ],
                'description' => $request->description ?? 'Payment for order',
                'metadata' => [
                    'order_id' => $request->order_id ?? uniqid('order_'),
                    'payment_id' => $payment->id, // Store payment ID for webhook
                    'customer_ip' => $request->ip(),
                ],
                'reference' => [
                    'transaction' => uniqid('txn_'),
                    'order' => $request->order_id ?? uniqid('order_'),
                ],
            ];

            // Create charge with Tap
            $response = $this->tapPaymentService->createCharge($paymentData);

            if (isset($response['error'])) {
                Log::error('Tap API Error:', $response['error']);
                
                // Update payment status to failed
                $payment->markAsFailed();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Payment creation failed',
                    'error' => $response['error']
                ], 500);
            }

            // Update payment with Tap response
            $payment->update([
                'tap_id' => $response['id'],
                'raw_response' => $response,
            ]);

            Log::info('Payment created successfully:', [
                'payment_id' => $payment->id,
                'tap_id' => $response['id'],
                'amount' => $request->amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment created successfully',
                'data' => [
                    'charge_id' => $response['id'],
                    'transaction_url' => $response['transaction']['url'] ?? null,
                    'amount' => $response['amount'],
                    'currency' => $response['currency'],
                    'status' => $response['status']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Payment creation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Payment creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle payment webhook from Tap
     */
    public function webhook(Request $request)
    {
        // Add CORS headers for webhook
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Tap-Signature');
        
        Log::info('Tap Webhook Received:', $request->all());

        $payload = $request->getContent();
        $signature = $request->header('Tap-Signature');

        // Verify webhook signature (optional but recommended)
        if (!$this->tapPaymentService->verifyWebhook($payload, $signature)) {
            Log::error('Invalid webhook signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        try {
            $event = $request->json()->all();
            $tapId = $event['id'] ?? null;
            $status = $event['status'] ?? null;

            if (!$tapId) {
                Log::error('Webhook missing tap_id');
                return response()->json(['error' => 'Missing tap_id'], 400);
            }

            // Find payment by tap_id
            $payment = Payment::where('tap_id', $tapId)->first();

            if (!$payment) {
                Log::error('Payment not found for tap_id: ' . $tapId);
                return response()->json(['error' => 'Payment not found'], 404);
            }

            Log::info('Processing webhook for payment:', [
                'payment_id' => $payment->id,
                'tap_id' => $tapId,
                'status' => $status,
                'current_payment_status' => $payment->status,
                'is_paid' => $payment->is_paid,
            ]);

            // Update raw response FIRST with webhook data
            $payment->update(['raw_response' => $event]);

            // Process based on status
            switch ($status) {
                case 'CAPTURED':
                case 'AUTHORIZED':
                    // Payment successful - this is the REAL success!
                    if (!$payment->is_paid) {
                        $payment->markAsPaid();
                        $this->activateUserSubscription($payment);
                        
                        Log::info('Payment marked as paid and subscription activated:', [
                            'payment_id' => $payment->id,
                            'tap_id' => $tapId,
                        ]);
                    } else {
                        Log::info('Payment already processed, skipping:', [
                            'payment_id' => $payment->id,
                            'tap_id' => $tapId,
                        ]);
                    }
                    break;

                case 'FAILED':
                case 'DECLINED':
                    // Payment failed
                    $payment->markAsFailed();
                    Log::info('Payment marked as failed:', [
                        'payment_id' => $payment->id,
                        'tap_id' => $tapId,
                    ]);
                    break;

                case 'INITIATED':
                    // Payment initiated - just log it
                    Log::info('Payment initiated:', [
                        'payment_id' => $payment->id,
                        'tap_id' => $tapId,
                    ]);
                    break;

                case 'VOID':
                    // Payment voided
                    $payment->update(['status' => 'VOID']);
                    Log::info('Payment voided:', [
                        'payment_id' => $payment->id,
                        'tap_id' => $tapId,
                    ]);
                    break;

                default:
                    Log::info('Unknown payment status:', [
                        'payment_id' => $payment->id,
                        'tap_id' => $tapId,
                        'status' => $status,
                    ]);
                    break;
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Webhook Processing Error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Activate user subscription after successful payment
     */
    private function activateUserSubscription(Payment $payment)
    {
        try {
            $orderId = $payment->order_id;
            
            // Extract plan_id from order_id (format: plan_{plan_id}_{timestamp})
            $planId = null;
            if ($orderId && preg_match('/plan_(\d+)_/', $orderId, $matches)) {
                $planId = (int) $matches[1];
            }
            
            if ($planId) {
                // Get plan details
                $plan = \DB::table('subscription_plans')->where('id', $planId)->first();
                
                if ($plan) {
                    // Check if this is user's first subscription (eligible for trial)
                    $hasPreviousSubscriptions = \DB::table('user_subscriptions')
                        ->where('supplier_id', $payment->supplier_id)
                        ->where('status', '!=', 'pending')
                        ->exists();
                    
                    $supplier = \DB::table('suppliers')->where('id', $payment->supplier_id)->first();
                    $hasUsedTrial = $supplier ? $supplier->has_used_free_trial : false;
                    $isFirstTime = !$hasPreviousSubscriptions && !$hasUsedTrial;
                    
                    // Calculate expiry date
                    $expiresAt = now();
                    $trialEndsAt = null;
                    
                    if ($isFirstTime) {
                        // First time: 30 days trial + plan duration
                        $trialEndsAt = now()->copy()->addDays(30);
                        if ($plan->billing_cycle === 'monthly') {
                            $expiresAt = $trialEndsAt->copy()->addMonth();
                        } elseif ($plan->billing_cycle === 'yearly') {
                            $expiresAt = $trialEndsAt->copy()->addYear();
                        }
                    } else {
                        // Returning customer: normal duration
                        if ($plan->billing_cycle === 'monthly') {
                            $expiresAt->addMonth();
                        } elseif ($plan->billing_cycle === 'yearly') {
                            $expiresAt->addYear();
                        }
                    }
                    
                    // Create payment transaction record
                    \DB::table('payment_transactions')->insert([
                        'supplier_id' => $payment->supplier_id,
                        'subscription_plan_id' => $planId,
                        'tap_charge_id' => $payment->tap_id,
                        'type' => 'subscription',
                        'status' => 'completed',
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'tap_response' => json_encode($payment->raw_response),
                        'paid_at' => now(),
                        'is_trial' => $isFirstTime,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Create or update user subscription
                    \DB::table('user_subscriptions')->updateOrInsert(
                        ['supplier_id' => $payment->supplier_id],
                        [
                            'subscription_plan_id' => $planId,
                            'status' => 'active',
                            'starts_at' => now(),
                            'ends_at' => $expiresAt,
                            'trial_ends_at' => $trialEndsAt,
                            'is_trial' => $isFirstTime,
                            'tap_charge_id' => $payment->tap_id,
                            'paid_amount' => $payment->amount,
                            'currency' => $payment->currency,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                    
                    // Update user subscription status
                    \DB::table('suppliers')
                        ->where('id', $payment->supplier_id)
                        ->update([
                            'subscription_status' => 'premium',
                            'subscription_plan_id' => $planId,
                            'plan' => $plan->name,
                            'has_used_free_trial' => true,
                            'trial_ends_at' => $trialEndsAt,
                            'updated_at' => now(),
                        ]);
                    
                    Log::info('Subscription activated successfully:', [
                        'supplier_id' => $payment->supplier_id,
                        'plan_id' => $planId,
                        'payment_id' => $payment->id,
                        'is_trial' => $isFirstTime,
                        'trial_ends_at' => $trialEndsAt,
                        'expires_at' => $expiresAt,
                    ]);
                } else {
                    Log::error('Plan not found:', ['plan_id' => $planId]);
                }
            } else {
                Log::error('Could not extract plan_id from order_id:', ['order_id' => $orderId]);
            }
            
        } catch (\Exception $e) {
            Log::error('Error activating subscription: ' . $e->getMessage());
        }
    }

    /**
     * Get subscription plans
     */
    public function getSubscriptionPlans()
    {
        try {
            $plans = \DB::table('subscription_plans')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($plan) {
                    $features = json_decode($plan->features, true) ?? [];
                    $features = is_array($features) && isset($features['en']) ? $features['en'] : $features;
                    
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'display_name' => $plan->display_name,
                        'description' => $plan->description,
                        'price' => (float) $plan->price,
                        'currency' => $plan->currency,
                        'billing_cycle' => $plan->billing_cycle,
                        'duration_months' => $plan->duration_months,
                        'features' => $features,
                        'is_active' => $plan->is_active,
                        'sort_order' => $plan->sort_order,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $plans
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching subscription plans: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscription plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent payments for authenticated user
     */
    public function getRecentPayments(Request $request)
    {
        try {
            $supplier = $request->user();
            if (!$supplier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier not authenticated'
                ], 401);
            }

            $payments = Payment::where('supplier_id', $supplier->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'tap_id' => $payment->tap_id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'is_paid' => $payment->is_paid,
                        'paid_at' => $payment->paid_at,
                        'order_id' => $payment->order_id,
                        'created_at' => $payment->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $payments
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching recent payments: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle payment success callback
     */
    public function paymentSuccess(Request $request)
    {
        $tapId = $request->query('tap_id');
        
        if (!$tapId) {
            return response()->json([
                'success' => false,
                'message' => 'No payment ID provided'
            ], 400);
        }

        try {
            $charge = $this->tapPaymentService->retrieveCharge($tapId);
            
            if ($charge && $charge['status'] === 'CAPTURED') {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment completed successfully',
                    'data' => [
                        'status' => $charge['status'],
                        'amount' => $charge['amount'],
                        'currency' => $charge['currency'],
                        'charge_id' => $charge['id']
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment was not successful',
                    'data' => [
                        'status' => $charge['status'] ?? 'unknown',
                        'charge_id' => $charge['id'] ?? null
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Payment Success Callback Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed'
            ], 500);
        }
    }

    /**
     * Get payment details
     */
    public function getPaymentDetails($chargeId)
    {
        try {
            $charge = $this->tapPaymentService->retrieveCharge($chargeId);
            
            if (isset($charge['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                    'error' => $charge['error']
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $charge
            ]);

        } catch (\Exception $e) {
            Log::error('Payment Details Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Tap publishable key for frontend
     */
    public function getConfig()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'publishable_key' => $this->tapPaymentService->getPublishableKey(),
                'currency' => 'SAR',
                'merchant_id' => config('tap.merchant_id'),
            ]
        ]);
    }

    /**
     * Process payment updates from webhook
     */
    private function processPaymentUpdate($charge)
    {
        $status = $charge['status'] ?? null;
        $orderId = $charge['metadata']['order_id'] ?? null;
        $transactionId = $charge['id'] ?? null;

        Log::info('Processing Payment Update:', [
            'status' => $status,
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'charge' => $charge
        ]);

        switch ($status) {
            case 'CAPTURED':
            case 'AUTHORIZED':
                // Payment successful - this is the REAL success!
                $this->handleSuccessfulPayment($charge);
                break;
            case 'INITIATED':
                // Payment initiated - just log it, don't create records yet
                Log::info('Payment initiated for: ' . $transactionId . ', waiting for CAPTURED status');
                break;
            case 'FAILED':
            case 'DECLINED':
                // Payment failed
                $this->handleFailedPayment($charge);
                break;
            case 'VOID':
                // Payment voided
                $this->handleVoidedPayment($charge);
                break;
        }
    }

    /**
     * Handle successful payment
     */
    private function handleSuccessfulPayment($charge)
    {
        Log::info('Payment Successful:', $charge);
        
        try {
            $orderId = $charge['metadata']['order_id'] ?? null;
            $chargeId = $charge['id'] ?? null;
            $amount = $charge['amount'] ?? 0;
            $currency = $charge['currency'] ?? 'SAR';
            
            // Extract plan_id from order_id (format: plan_{plan_id}_{timestamp})
            $planId = null;
            if ($orderId && preg_match('/plan_(\d+)_/', $orderId, $matches)) {
                $planId = (int) $matches[1];
            }
            
            // Check if payment transaction already exists
            $existingPayment = \DB::table('payment_transactions')
                ->where('tap_charge_id', $chargeId)
                ->first();
            
            if ($existingPayment) {
                Log::info('Payment transaction already exists for: ' . $chargeId);
                return;
            }
            
            // Get supplier from metadata or find by email
            $supplierEmail = $charge['customer']['email'] ?? null;
            $supplier = $supplierEmail ? \App\Models\Supplier::where('email', $supplierEmail)->first() : null;
            $supplierId = $supplier ? $supplier->id : 1;
            
            // Create payment transaction record
            \DB::table('payment_transactions')->insert([
                'supplier_id' => $supplierId,
                'tap_charge_id' => $chargeId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'completed',
                'type' => 'subscription',
                'description' => $orderId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Create user subscription record
            if ($planId) {
                // Get plan details
                $plan = \DB::table('subscription_plans')->where('id', $planId)->first();
                
                if ($plan) {
                    // Calculate expiry date based on billing cycle
                    $expiresAt = now();
                    if ($plan->billing_cycle === 'monthly') {
                        $expiresAt->addMonth();
                    } elseif ($plan->billing_cycle === 'yearly') {
                        $expiresAt->addYear();
                    }
                    
                    // Create or update user subscription
                    \DB::table('user_subscriptions')->updateOrInsert(
                        ['supplier_id' => $supplierId],
                        [
                            'subscription_plan_id' => $planId,
                            'status' => 'active',
                            'starts_at' => now(),
                            'ends_at' => $expiresAt,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                    
                    // Update user subscription status
                    \DB::table('suppliers')
                        ->where('id', $supplierId)
                        ->update([
                            'subscription_status' => 'premium',
                            'subscription_plan_id' => $planId,
                            'updated_at' => now(),
                        ]);
                    
                    Log::info('Subscription created for supplier ' . $supplierId . ', plan: ' . $planId);
                }
            }
            
            Log::info('Payment and subscription records created successfully');
            
        } catch (\Exception $e) {
            Log::error('Error creating payment records: ' . $e->getMessage());
        }
    }

    /**
     * Handle failed payment
     */
    private function handleFailedPayment($charge)
    {
        Log::error('Payment Failed:', $charge);
        // Implement your failure logic here
    }

    /**
     * Handle voided payment
     */
    private function handleVoidedPayment($charge)
    {
        Log::info('Payment Voided:', $charge);
        // Implement your void logic here
    }
}
