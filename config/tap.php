<?php

return [
    'secret_key' => env('TAP_SECRET_KEY'),
    'publishable_key' => env('TAP_PUBLISHABLE_KEY'),
    'merchant_id' => env('TAP_MERCHANT_ID'),
    'base_url' => env('TAP_API_BASE_URL', 'https://api.tap.company/v2'),
];
