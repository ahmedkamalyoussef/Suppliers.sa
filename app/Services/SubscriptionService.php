<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Models\PaymentTransaction;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SubscriptionService
{
    /**
     * Create subscription payment charge
     */
    public function createSubscriptionPayment(Supplier $supplier, SubscriptionPlan $plan, array $customerData)
    {
        try {
            DB::beginTransaction();

            // Create payment transaction record
            $transaction = PaymentTransaction::create([
                'supplier_id' => $supplier->id,
                'subscription_plan_id' => $plan->id,
                'type' => 'subscription',
                'status' => 'pending',
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'description' => "اشتراك {$plan->display_name} - {$plan->duration_text}",
                'metadata' => [
                    'plan_name' => $plan->name,
                    'billing_cycle' => $plan->billing_cycle,
                    'supplier_email' => $supplier->email,
                ],
            ]);

            // Prepare payment data for Tap
            // Tap API requires amount in smallest currency unit (fils for SAR/KWD, etc.)
            // For example: 199.00 SAR = 19900 fils
            $amountInSmallestUnit = (int) round($plan->price * 100);
            
            // Validate amount
            if ($amountInSmallestUnit <= 0) {
                DB::rollBack();
                Log::error('Invalid plan price', [
                    'plan_id' => $plan->id,
                    'price' => $plan->price,
                    'amount_in_smallest_unit' => $amountInSmallestUnit
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Invalid plan price. Please contact support.',
                ];
            }
            
            $paymentData = [
                'amount' => $amountInSmallestUnit,
                'currency' => $plan->currency,
                'customer' => [
                    'first_name' => $customerData['first_name'],
                    'last_name' => $customerData['last_name'] ?? '',
                    'email' => $customerData['email'],
                    'phone' => [
                        'country_code' => $customerData['phone']['country_code'] ?? '966',
                        'number' => $customerData['phone']['number'],
                    ],
                ],
                'source' => [
                    'id' => 'src_all'
                ],
                'redirect' => [
                    'url' => config('app.frontend_url', 'http://localhost:3000') . "/subscription/success?transaction_id={$transaction->id}"
                ],
                'post' => [
                    'url' => config('app.url') . "/api/tap/webhook"
                ],
                'description' => "اشتراك {$plan->display_name} - {$plan->duration_text}",
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'supplier_id' => $supplier->id,
                    'plan_id' => $plan->id,
                    'type' => 'subscription',
                ],
            ];

            // Create charge with Tap
            $tapService = new TapPaymentService();
            $charge = $tapService->createCharge($paymentData);

            // Check if charge creation failed - Tap API can return errors in different formats
            $errorMessage = null;
            
            // Check for errors array
            if (isset($charge['errors']) && !empty($charge['errors'])) {
                $errorMessage = is_array($charge['errors'][0]) 
                    ? ($charge['errors'][0]['message'] ?? $charge['errors'][0]['description'] ?? $charge['errors'][0]['code'] ?? 'Unknown error')
                    : $charge['errors'][0];
            }
            // Check for single error object
            elseif (isset($charge['error'])) {
                $errorMessage = is_array($charge['error'])
                    ? ($charge['error']['message'] ?? $charge['error']['description'] ?? $charge['error']['code'] ?? 'Unknown error')
                    : $charge['error'];
            }
            // Check for message field
            elseif (isset($charge['message']) && !isset($charge['id'])) {
                $errorMessage = $charge['message'];
            }
            // Check for description field
            elseif (isset($charge['description']) && !isset($charge['id'])) {
                $errorMessage = $charge['description'];
            }
            
            if ($errorMessage) {
                DB::rollBack();
                Log::error('Tap charge creation failed', [
                    'error_message' => $errorMessage,
                    'charge_response' => $charge,
                    'transaction_id' => $transaction->id,
                    'payment_data' => [
                        'amount' => $paymentData['amount'],
                        'currency' => $paymentData['currency'],
                        'customer_email' => $paymentData['customer']['email'] ?? null,
                    ]
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Payment gateway error: ' . $errorMessage,
                    'charge' => $charge,
                ];
            }

            // Check if charge ID exists (indicates successful creation)
            if (!isset($charge['id'])) {
                DB::rollBack();
                Log::error('Tap charge creation returned invalid response', [
                    'charge' => $charge,
                    'transaction_id' => $transaction->id,
                    'charge_keys' => array_keys($charge ?? [])
                ]);
                
                $errorMsg = 'Invalid response from payment gateway';
                if (isset($charge['message'])) {
                    $errorMsg .= ': ' . $charge['message'];
                } elseif (isset($charge['description'])) {
                    $errorMsg .= ': ' . $charge['description'];
                }
                
                return [
                    'success' => false,
                    'message' => $errorMsg,
                    'charge' => $charge,
                ];
            }

            // Update transaction with Tap charge ID
            $transaction->update([
                'tap_charge_id' => $charge['id'],
                'tap_response' => $charge, 
            ]);

            DB::commit();

            return [
                'success' => true,
                'transaction' => $transaction,
                'charge' => $charge,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription payment creation failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if user is eligible for free trial
     */
    public function isEligibleForTrial($supplierId)
    {
        $supplier = Supplier::find($supplierId);
        
        if (!$supplier) {
            return false;
        }
        
        // Check if user has ever had a subscription before
        $hasPreviousSubscriptions = UserSubscription::where('supplier_id', $supplierId)
            ->where('status', '!=', 'pending')
            ->exists();
        
        // Eligible if no previous subscriptions and hasn't used trial
        return !$hasPreviousSubscriptions && !$supplier->has_used_free_trial;
    }

    /**
     * Activate subscription after successful payment (or trial)
     */
    public function activateSubscription($tapChargeId, $isTrial = false)
    {
        try {
            DB::beginTransaction();

            // Find the transaction
            $transaction = PaymentTransaction::where('tap_charge_id', $tapChargeId)
                ->where('status', 'pending')
                ->firstOrFail();

            // Get supplier and plan
            $supplier = Supplier::find($transaction->supplier_id);
            $plan = $transaction->subscriptionPlan;
            
            // Check for trial eligibility
            $isTrialEligible = $this->isEligibleForTrial($supplier->id);
            $trialDays = $isTrialEligible ? 30 : 0;

            // Deactivate any existing active subscriptions
            $this->deactivateUserSubscriptions($supplier->id);

            // Calculate subscription dates
            $startsAt = now();
            if ($trialDays > 0) {
                // First time: 30 days trial + plan duration
                $endsAt = $startsAt->copy()->addDays($trialDays)->addMonths($plan->duration_months);
            } else {
                // Returning customer: normal duration
                $endsAt = $startsAt->copy()->addMonths($plan->duration_months);
            }

            // Create new subscription
            $subscription = UserSubscription::create([
                'supplier_id' => $supplier->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'tap_charge_id' => $tapChargeId,
                'paid_amount' => $plan->price,
                'currency' => $plan->currency,
                'auto_renew' => false,
                'is_trial' => $trialDays > 0,
                'trial_ends_at' => $trialDays > 0 ? $startsAt->copy()->addDays($trialDays) : null,
            ]);
            
            // Update supplier plan and trial status
            $supplier->update([
                'plan' => $plan->name,
                'subscription_status' => 'active',
                'subscription_plan_id' => $plan->id,
                'has_used_free_trial' => $trialDays > 0 ? true : $supplier->has_used_free_trial,
                'trial_ends_at' => $trialDays > 0 ? $startsAt->copy()->addDays($trialDays) : $supplier->trial_ends_at,
            ]);

            // Update transaction
            $transaction->update([
                'status' => 'completed',
                'paid_at' => now(),
                'is_trial' => $trialDays > 0,
            ]);

            DB::commit();

            Log::info('Subscription activated', [
                'supplier_id' => $supplier->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
                'charge_id' => $tapChargeId,
                'is_trial' => $trialDays > 0,
                'trial_days' => $trialDays,
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
                'transaction' => $transaction,
                'is_trial' => $trialDays > 0,
                'trial_ends_at' => $trialDays > 0 ? $startsAt->copy()->addDays($trialDays) : null,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription activation failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Deactivate all active subscriptions for a user
     */
    private function deactivateUserSubscriptions($supplierId)
    {
        UserSubscription::where('supplier_id', $supplierId)
            ->where('status', 'active')
            ->update([
                'status' => 'expired',
                'cancelled_at' => now(),
            ]);
    }

    /**
     * Check and update expired subscriptions
     */
    public function updateExpiredSubscriptions()
    {
        $expiredSubscriptions = UserSubscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->update([
                'status' => 'expired',
                'cancelled_at' => now(),
            ]);

            // Revert supplier plan back to Basic
            DB::table('suppliers')
                ->where('id', $subscription->supplier_id)
                ->update([
                    'plan' => 'free',
                    'subscription_status' => 'free',
                    'subscription_plan_id' => null,
                    'updated_at' => now(),
                ]);

            Log::info('Subscription expired and reverted to Basic', [
                'supplier_id' => $subscription->supplier_id,
                'subscription_id' => $subscription->id,
            ]);
        }

        return $expiredSubscriptions->count();
    }

    /**
     * Get user's current active subscription
     */
    public function getUserActiveSubscription($supplierId)
    {
        return UserSubscription::where('supplier_id', $supplierId)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->with('subscriptionPlan')
            ->first();
    }

    /**
     * Get user's subscription history
     */
    public function getUserSubscriptionHistory($supplierId)
    {
        return UserSubscription::where('supplier_id', $supplierId)
            ->with('subscriptionPlan')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get available subscription plans
     */
    public function getAvailablePlans()
    {
        return SubscriptionPlan::active()
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStatistics($startDate = null, $endDate = null)
    {
        $query = PaymentTransaction::completed();

        if ($startDate) {
            $query->where('paid_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('paid_at', '<=', $endDate);
        }

        $transactions = $query->get();

        return [
            'total_transactions' => $transactions->count(),
            'total_amount' => $transactions->sum('amount'),
            'total_refunded' => $transactions->sum('refunded_amount'),
            'net_amount' => $transactions->sum('amount') - $transactions->sum('refunded_amount'),
            'by_type' => $transactions->groupBy('type')->map->count(),
            'by_month' => $transactions->groupBy(function($item) {
                return $item->paid_at->format('Y-m');
            })->map->sum('amount'),
        ];
    }

    /**
     * Get subscription statistics
     */
    public function getSubscriptionStatistics()
    {
        $activeSubscriptions = UserSubscription::active()->count();
        $expiredSubscriptions = UserSubscription::expired()->count();
        $totalSubscriptions = UserSubscription::count();

        $revenueThisMonth = PaymentTransaction::completed()
            ->where('type', 'subscription')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $revenueThisYear = PaymentTransaction::completed()
            ->where('type', 'subscription')
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        return [
            'active_subscriptions' => $activeSubscriptions,
            'expired_subscriptions' => $expiredSubscriptions,
            'total_subscriptions' => $totalSubscriptions,
            'revenue_this_month' => $revenueThisMonth,
            'revenue_this_year' => $revenueThisYear,
            'by_plan' => UserSubscription::with('subscriptionPlan')
                ->get()
                ->groupBy('subscriptionPlan.display_name')
                ->map->count(),
        ];
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription($subscriptionId)
    {
        try {
            $subscription = UserSubscription::findOrFail($subscriptionId);

            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'auto_renew' => false,
            ]);

            Log::info('Subscription cancelled', [
                'subscription_id' => $subscriptionId,
                'supplier_id' => $subscription->supplier_id,
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
            ];

        } catch (\Exception $e) {
            Log::error('Subscription cancellation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send renewal reminders for subscriptions expiring in 2 days
     */
    public function sendRenewalReminders()
    {
        // Find all active subscriptions expiring in the next 2 days
        $expiringSubscriptions = UserSubscription::where('status', 'active')
            ->where('ends_at', '<=', now()->addDays(2))
            ->where('ends_at', '>=', now())
            ->with('supplier', 'subscriptionPlan')
            ->get();

        $sentCount = 0;

        foreach ($expiringSubscriptions as $subscription) {
            $supplier = Supplier::find($subscription->supplier_id);

            if (!$supplier) {
                continue;
            }

            $planName = $subscription->subscriptionPlan?->display_name ?? 'Premium';
            $daysRemaining = ceil(now()->diffInDays($subscription->ends_at));

            // Send email directly
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "عزيزي {$supplier->name}،\n\n" .
                    "اشتراكك في باقة {$planName} سينتهي خلال {$daysRemaining} أيام ({$subscription->ends_at->format('Y-m-d')}).\n\n" .
                    "لضمان استمرار خدماتك بدون انقطاع، يرجى تجديد اشتراكك قبل انتهاء الفترة.",
                    function ($message) use ($supplier) {
                        $message->to($supplier->email)
                                ->subject('تذكير: اشتراكك على وشك الانتهاء');
                    }
                );

                Log::info('Renewal reminder email sent', [
                    'supplier_id' => $subscription->supplier_id,
                    'email' => $supplier->email,
                    'subscription_id' => $subscription->id,
                    'ends_at' => $subscription->ends_at,
                ]);

                $sentCount++;
            } catch (Exception $e) {
                Log::error('Failed to send renewal reminder email', [
                    'supplier_id' => $subscription->supplier_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sentCount;
    }
}
