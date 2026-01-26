<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class TapPaymentService
{
    protected $client;
    protected $secretKey;
    protected $publishableKey;
    protected $apiBaseUrl;

    public function __construct()
    {
        // Use config instead of env for production compatibility
        $this->secretKey = config('tap.secret_key');
        $this->publishableKey = config('tap.publishable_key');
        
        // Ensure proper URL formatting
        $baseUrl = config('tap.base_url', 'https://api.tap.company/v2');
        $this->apiBaseUrl = rtrim($baseUrl, '/') . '/';

        $this->client = new Client([
            'base_uri' => $this->apiBaseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Create a payment charge
     */
    public function createCharge(array $data)
    {
        try {
            $url = 'charges'; // No leading slash since base_uri already ends with /
            Log::info('Tap API Request:', [
                'url' => $this->apiBaseUrl . $url,
                'data' => $data,
                'secret_key' => substr($this->secretKey, 0, 10) . '...'
            ]);
            
            $response = $this->client->post($url, [
                'json' => $data
            ]);

            $statusCode = $response->getStatusCode();
            $result = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Tap API Response:', [
                'status_code' => $statusCode,
                'response' => $result
            ]);
            
            // Check if response indicates an error (4xx or 5xx status codes)
            if ($statusCode >= 400) {
                Log::error('Tap API returned error status', [
                    'status_code' => $statusCode,
                    'response' => $result
                ]);
            }
            
            return $result;
        } catch (RequestException $e) {
            Log::error('Tap Payment Error: ' . $e->getMessage());
            
            if ($e->hasResponse()) {
                $errorResponse = json_decode($e->getResponse()->getBody()->getContents(), true);
                Log::error('Tap API Error Response:', $errorResponse);
                return $errorResponse;
            }
            
            throw new \Exception('Payment processing failed');
        }
    }

    /**
     * Retrieve a charge
     */
    public function retrieveCharge(string $chargeId)
    {
        try {
            $response = $this->client->get("charges/{$chargeId}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Tap Retrieve Charge Error: ' . $e->getMessage());
            throw new \Exception('Failed to retrieve charge');
        }
    }

    /**
     * Create a customer
     */
    public function createCustomer(array $data)
    {
        try {
            $response = $this->client->post('customers', [
                'json' => $data
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Tap Create Customer Error: ' . $e->getMessage());
            throw new \Exception('Failed to create customer');
        }
    }

    /**
     * Create a refund
     */
    public function createRefund(string $chargeId, array $data)
    {
        try {
            $response = $this->client->post('refunds', [
                'json' => array_merge($data, ['charge_id' => $chargeId])
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Tap Refund Error: ' . $e->getMessage());
            throw new \Exception('Failed to process refund');
        }
    }

    /**
     * Get publishable key for frontend
     */
    public function getPublishableKey()
    {
        return $this->publishableKey;
    }

    /**
     * Prepare charge data for payment
     */
    public function prepareChargeData(array $paymentData)
    {
        return [
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? 'KWD',
            'customer' => [
                'first_name' => $paymentData['customer']['first_name'],
                'last_name' => $paymentData['customer']['last_name'] ?? '',
                'email' => $paymentData['customer']['email'],
                'phone' => [
                    'country_code' => $paymentData['customer']['phone']['country_code'] ?? '965',
                    'number' => $paymentData['customer']['phone']['number'],
                ],
            ],
            'source' => [
                'id' => $paymentData['source_id'] ?? 'src_all'
            ],
            'post' => [
                'url' => $paymentData['post_url'] ?? env('APP_URL') . '/tap/webhook'
            ],
            'redirect' => [
                'url' => $paymentData['redirect_url'] ?? env('APP_URL') . '/payment/success'
            ],
            'description' => $paymentData['description'] ?? 'Payment for order',
            'metadata' => $paymentData['metadata'] ?? [],
            'reference' => [
                'transaction' => $paymentData['reference']['transaction'] ?? uniqid('txn_'),
                'order' => $paymentData['reference']['order'] ?? uniqid('ord_'),
            ],
            'receipt' => [
                'email' => $paymentData['receipt']['email'] ?? false,
                'sms' => $paymentData['receipt']['sms'] ?? true,
            ],
        ];
    }
}
