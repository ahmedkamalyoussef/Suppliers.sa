<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SupplierAuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SupplierRatingController;
use App\Http\Controllers\BranchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authenticated user info
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
||--------------------------------------------------------------------------
|| Shared Auth Routes
||--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/forgot-password', [PasswordController::class, 'forgotPassword']);
    Route::post('/reset-password', [PasswordController::class, 'resetPassword']);
});

/*
||--------------------------------------------------------------------------
|| Public Registration Routes
||--------------------------------------------------------------------------
*/
Route::prefix('supplier')->group(function () {
    Route::post('/register', [SupplierAuthController::class, 'register']);
});

// Public super admin bootstrap: allowed only if no super admin exists (enforced in controller)
Route::post('/admins/register-super', [AdminController::class, 'registerSuper']);

/*
||--------------------------------------------------------------------------
|| Protected Routes (Authenticated)
||--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Shared
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [PasswordController::class, 'changePassword']);

    // Admin Profile (Admin can update their own profile)
    Route::prefix('admin')->group(function () {
        Route::put('/profile', [AdminController::class, 'updateProfile']);
        Route::post('/profile/image', [AdminController::class, 'updateProfileImage']);
    });

    // Admin Management (Super Admin Only)
    Route::prefix('admins')->group(function () {
        Route::get('/', [AdminController::class, 'index']);
        Route::post('/', [AdminController::class, 'store']);
        Route::get('/{admin}', [AdminController::class, 'show']);
        Route::put('/{admin}', [AdminController::class, 'update']);
        Route::delete('/{admin}', [AdminController::class, 'destroy']);
    });

    // Supplier Profile
    Route::prefix('supplier')->group(function () {
        Route::put('/profile', [SupplierAuthController::class, 'updateProfile']);
        Route::post('/profile/image', [SupplierAuthController::class, 'updateProfileImage']);
        // Ratings
        Route::post('/ratings', [SupplierRatingController::class, 'store']);
    });

    // Branch Management (Supplier Only)
    Route::prefix('branches')->group(function () {
        Route::get('/', [BranchController::class, 'index']);
        Route::post('/', [BranchController::class, 'store']);
        Route::get('/{branch}', [BranchController::class, 'show']);
        Route::put('/{branch}', [BranchController::class, 'update']);
        Route::delete('/{branch}', [BranchController::class, 'destroy']);
        Route::post('/{branch}/set-main', [BranchController::class, 'setMainBranch']);
    });

    // Approve ratings (admins)
    Route::post('/ratings/{rating}/approve', [SupplierRatingController::class, 'approve']);
});
