<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private const CLICKPAY_API_URL = 'https://api.clickpay.com.cn';
    
    /**
     * Create ClickPay payment request
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'profile_id' => 'required|string',
            'tran_type' => 'required|string|in:sale',
            'tran_class' => 'required|string|in:ecom',
            'cart_id' => 'required|string|max:255',
            'cart_currency' => 'required|string|size:3',
            'cart_amount' => 'required|numeric|min:0.01',
            'cart_description' => 'required|string|max:500',
            'paypage_lang' => 'required|string|in:en,ar',
            'customer_details' => 'required|array',
            'customer_details.name' => 'required|string|max:255',
            'customer_details.email' => 'required|email|max:255',
            'customer_details.phone' => 'required|string|max:20',
            'customer_details.street1' => 'required|string|max:255',
            'customer_details.city' => 'required|string|max:255',
            'customer_details.state' => 'required|string|max:255',
            'customer_details.country' => 'required|string|size:2',
            'customer_details.zip' => 'required|string|max:20',
            'return' => 'required|url',
            'callback' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payload = [
                'profile_id' => $request->profile_id,
                'tran_type' => $request->tran_type,
                'tran_class' => $request->tran_class,
                'cart_id' => $request->cart_id,
                'cart_currency' => $request->cart_currency,
                'cart_amount' => $request->cart_amount,
                'cart_description' => $request->cart_description,
                'paypage_lang' => $request->paypage_lang,
                'customer_details' => $request->customer_details,
                'return' => $request->return,
                'callback' => $request->callback,
                'hide_shipping' => true,
                'framed' => false,
                'white_label' => false,
            ];

            // Call ClickPay API
            $response = Http::post(self::CLICKPAY_API_URL . '/payment/request', $payload);

            if (!$response->successful()) {
                Log::error('ClickPay API error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment gateway error. Please try again.',
                ], 500);
            }

            $data = $response->json();

            return response()->json([
                'success' => true,
                'redirectUrl' => $data['redirect_url'] ?? null,
                'transactionId' => $data['tran_ref'] ?? null,
                'message' => $data['result'] ?? 'Payment request created successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Payment creation error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Query transaction status
     */
    public function query(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'profile_id' => 'required|string',
            'tran_ref' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payload = [
                'profile_id' => $request->profile_id,
                'tran_ref' => $request->tran_ref,
            ];

            $response = Http::post(self::CLICKPAY_API_URL . '/payment/query', $payload);

            if (!$response->successful()) {
                Log::error('ClickPay query error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to query transaction status.',
                ], 500);
            }

            $data = $response->json();

            return response()->json([
                'success' => true,
                'result' => $data['result'] ?? null,
                'response_code' => $data['response_code'] ?? null,
                'response_message' => $data['response_message'] ?? null,
                'transaction_id' => $data['tran_ref'] ?? null,
                'transaction_status' => $data['tran_status'] ?? null,
                'transaction_type' => $data['tran_type'] ?? null,
                'transaction_class' => $data['tran_class'] ?? null,
                'cart_id' => $data['cart_id'] ?? null,
                'cart_currency' => $data['cart_currency'] ?? null,
                'cart_amount' => $data['cart_amount'] ?? null,
                'cart_description' => $data['cart_description'] ?? null,
                'customer_details' => $data['customer_details'] ?? [],
            ]);

        } catch (\Exception $e) {
            Log::error('Payment query error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Transaction query failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Process refund
     */
    public function refund(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'profile_id' => 'required|string',
            'transaction_id' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payload = [
                'profile_id' => $request->profile_id,
                'tran_ref' => $request->transaction_id,
                'tran_amount' => $request->amount,
                'tran_currency' => $request->currency,
                'tran_type' => 'refund',
                'tran_class' => 'ecom',
            ];

            $response = Http::post(self::CLICKPAY_API_URL . '/payment/request', $payload);

            if (!$response->successful()) {
                Log::error('ClickPay refund error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Refund processing failed. Please try again.',
                ], 500);
            }

            $data = $response->json();

            return response()->json([
                'success' => true,
                'result' => $data['result'] ?? null,
                'response_code' => $data['response_code'] ?? null,
                'response_message' => $data['response_message'] ?? null,
                'transaction_id' => $data['tran_ref'] ?? null,
                'refund_id' => $data['refund_ref'] ?? null,
                'refund_amount' => $data['tran_amount'] ?? null,
                'refund_currency' => $data['tran_currency'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('Refund processing error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Refund processing failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Handle ClickPay callback
     */
    public function callback(Request $request): JsonResponse
    {
        try {
            // Log the callback for debugging
            Log::info('ClickPay callback received', $request->all());

            // Validate required callback fields
            $validator = Validator::make($request->all(), [
                'transaction_id' => 'required|string',
                'resp_status' => 'required|string',
                'resp_message' => 'required|string',
                'tran_ref' => 'required|string',
                'cart_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                Log::warning('Invalid callback data', [
                    'errors' => $validator->errors(),
                    'data' => $request->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid callback data'
                ], 400);
            }

            // Here you would typically:
            // 1. Verify the callback authenticity (using ClickPay's signature verification)
            // 2. Update your database with the transaction status
            // 3. Trigger any post-payment actions (send emails, update subscriptions, etc.)

            // For now, we'll just log and acknowledge receipt
            Log::info('Payment callback processed', [
                'transaction_id' => $request->transaction_id,
                'status' => $request->resp_status,
                'cart_id' => $request->cart_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Callback processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Callback processing error', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Callback processing failed'
            ], 500);
        }
    }

    /**
     * Get payment methods (for checkout page)
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        try {
            // This would typically call ClickPay API to get available payment methods
            // For now, we'll return common payment methods
            
            return response()->json([
                'success' => true,
                'methods' => [
                    [
                        'id' => 'credit_card',
                        'name' => 'Credit/Debit Card',
                        'icon' => 'credit-card',
                        'enabled' => true,
                    ],
                    [
                        'id' => 'apple_pay',
                        'name' => 'Apple Pay',
                        'icon' => 'apple',
                        'enabled' => true,
                    ],
                    [
                        'id' => 'google_pay',
                        'name' => 'Google Pay',
                        'icon' => 'google',
                        'enabled' => true,
                    ],
                    [
                        'id' => 'sadad',
                        'name' => 'SADAD',
                        'icon' => 'bank',
                        'enabled' => true,
                    ],
                    [
                        'id' => 'mada',
                        'name' => 'MADA',
                        'icon' => 'credit-card',
                        'enabled' => true,
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Payment methods error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch payment methods.'
            ], 500);
        }
    }
}
