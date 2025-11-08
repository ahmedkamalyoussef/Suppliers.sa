<?php

use App\Http\Controllers\Auth\SupplierAuthController;
use App\Http\Controllers\Auth\BuyerAuthController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\BranchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Shared Auth Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/forgot-password', [PasswordController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordController::class, 'resetPassword']);

// Public Buyer Routes
Route::post('/buyer/register', [BuyerAuthController::class, 'register']);

// Public Supplier Routes
Route::post('/supplier/register', [SupplierAuthController::class, 'register']);

// Protected Supplier Routes
Route::middleware('auth:sanctum')->group(function () {
    // Shared Auth Routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [PasswordController::class, 'changePassword']);
    
    // Supplier Profile Routes
    Route::put('/supplier/profile', [SupplierAuthController::class, 'updateProfile']);
    Route::post('/supplier/profile/image', [SupplierAuthController::class, 'updateProfileImage']);
    
    // Buyer Profile Routes
    Route::put('/buyer/profile', [BuyerAuthController::class, 'updateProfile']);
    Route::post('/buyer/profile/image', [BuyerAuthController::class, 'updateProfileImage']);
    
    // Branch Routes
    Route::get('/branches', [BranchController::class, 'index']);
    Route::post('/branches', [BranchController::class, 'store']);
    Route::get('/branches/{branch}', [BranchController::class, 'show']);
    Route::put('/branches/{branch}', [BranchController::class, 'update']);
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);
    Route::post('/branches/{branch}/set-main', [BranchController::class, 'setMainBranch']);
});
