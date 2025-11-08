<?php

use App\Http\Controllers\Auth\SupplierAuthController;
use App\Http\Controllers\BranchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Public Supplier Routes
Route::post('/supplier/register', [SupplierAuthController::class, 'register']);
Route::post('/supplier/login', [SupplierAuthController::class, 'login']);
Route::post('/supplier/send-otp', [SupplierAuthController::class, 'sendOtp']);
Route::post('/supplier/verify-otp', [SupplierAuthController::class, 'verifyOtp']);

// Protected Supplier Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/supplier/logout', [SupplierAuthController::class, 'logout']);
    Route::put('/supplier/profile', [SupplierAuthController::class, 'updateProfile']);
    
    // Branch Routes
    Route::get('/branches', [BranchController::class, 'index']);
    Route::post('/branches', [BranchController::class, 'store']);
    Route::get('/branches/{branch}', [BranchController::class, 'show']);
    Route::put('/branches/{branch}', [BranchController::class, 'update']);
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);
    Route::post('/branches/{branch}/set-main', [BranchController::class, 'setMainBranch']);
});
