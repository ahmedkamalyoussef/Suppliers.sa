<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Temporary fix for payment endpoint redirect
Route::post('/payment/create', function (Request $request) {
    // Redirect to the correct Tap endpoint
    return app('App\Http\Controllers\Api\TapPaymentController')->createSubscriptionPayment($request);
});

// Add other payment endpoints if needed
Route::post('/payment/callback', function (Request $request) {
    return app('App\Http\Controllers\Api\TapPaymentController')->subscriptionSuccess($request);
});
