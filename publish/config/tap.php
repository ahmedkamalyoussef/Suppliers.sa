<?php

return [
    'secret_key' => env('TAP_SECRET_KEY'),
    'publishable_key' => env('TAP_PUBLIC_KEY'),
    'merchant_id' => env('TAP_MERCHANT_ID'),
    'base_url' => env('TAP_BASE_URL', 'https://api.tap.company/v2'),
    'currency' => env('TAP_CURRENCY', 'SAR'),
    'callbackURL' => env('APP_URL') . '/api/payment/callback',
];
