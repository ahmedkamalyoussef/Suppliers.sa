<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// Add GET route for login (for frontend compatibility)
Route::get('/login', function () {
    return response()->json([
        'message' => 'Login endpoint - use POST to authenticate',
        'endpoint' => 'POST /login'
    ]);
});

// Temporary fix for payment endpoints
Route::post('/payment/create', function (Request $request) {
    return app('App\Http\Controllers\Api\TapPaymentController')->createSubscriptionPayment($request);
});

Route::post('/payment/callback', function (Request $request) {
    return app('App\Http\Controllers\Api\TapPaymentController')->subscriptionSuccess($request);
});

require __DIR__.'/auth.php';
