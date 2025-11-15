<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SupplierAuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminContentController;
use App\Http\Controllers\AdminContentReportController;
use App\Http\Controllers\AdminDocumentController;
use App\Http\Controllers\AdminRatingController;
use App\Http\Controllers\AdminSupplierController;
use App\Http\Controllers\PublicContentReportController;
use App\Http\Controllers\SupplierContentReportController;
use App\Http\Controllers\SupplierDashboardController;
use App\Http\Controllers\SupplierDocumentController;
use App\Http\Controllers\SupplierInquiryController;
use App\Http\Controllers\SupplierRatingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\PublicBusinessController;
use App\Http\Controllers\PublicBusinessInquiryController;
use App\Http\Controllers\PublicBusinessReviewController;
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
|| Public Routes
||--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    Route::get('/businesses', [PublicBusinessController::class, 'index']);
    Route::get('/businesses/{slug}', [PublicBusinessController::class, 'show']);
    Route::post('/businesses/{slug}/reviews', [PublicBusinessReviewController::class, 'store']);
    Route::post('/businesses/{slug}/inquiries', [PublicBusinessInquiryController::class, 'store']);
    Route::post('/reports', [PublicContentReportController::class, 'store']);
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
        Route::get('/dashboard', [AdminDashboardController::class, 'overview']);
        Route::get('/dashboard/analytics', [AdminDashboardController::class, 'analytics']);
        Route::put('/profile', [AdminController::class, 'updateProfile']);
        Route::post('/profile/image', [AdminController::class, 'updateProfileImage']);
        Route::get('/suppliers', [AdminSupplierController::class, 'index']);
        Route::get('/suppliers/{supplier}', [AdminSupplierController::class, 'show']);
        Route::put('/suppliers/{supplier}', [AdminSupplierController::class, 'update']);
        Route::post('/suppliers/{supplier}/status', [AdminSupplierController::class, 'updateStatus']);
        Route::delete('/suppliers/{supplier}', [AdminSupplierController::class, 'destroy']);
        Route::get('/ratings', [AdminRatingController::class, 'index']);
        Route::get('/ratings/{rating}', [AdminRatingController::class, 'show']);
        Route::post('/ratings/{rating}/approve', [AdminRatingController::class, 'approve']);
        Route::post('/ratings/{rating}/reject', [AdminRatingController::class, 'reject']);
        Route::post('/ratings/{rating}/flag', [AdminRatingController::class, 'flag']);
        Route::post('/ratings/{rating}/restore', [AdminRatingController::class, 'restore']);
        Route::get('/documents', [AdminDocumentController::class, 'index']);
        Route::get('/documents/{document}', [AdminDocumentController::class, 'show']);
        Route::post('/documents/{document}/approve', [AdminDocumentController::class, 'approve']);
        Route::post('/documents/{document}/reject', [AdminDocumentController::class, 'reject']);
        Route::post('/documents/{document}/request-resubmission', [AdminDocumentController::class, 'requestResubmission']);
        Route::get('/content', [AdminContentController::class, 'index']);
        Route::get('/reports', [AdminContentReportController::class, 'index']);
        Route::get('/reports/{report}', [AdminContentReportController::class, 'show']);
        Route::post('/reports/{report}/approve', [AdminContentReportController::class, 'approve']);
        Route::post('/reports/{report}/dismiss', [AdminContentReportController::class, 'dismiss']);
        Route::post('/reports/{report}/takedown', [AdminContentReportController::class, 'takedown']);
        Route::post('/reports/{report}/status', [AdminContentReportController::class, 'updateStatus']);
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
        Route::get('/dashboard', [SupplierDashboardController::class, 'overview']);
        Route::put('/profile', [SupplierAuthController::class, 'updateProfile']);
        Route::post('/profile/image', [SupplierAuthController::class, 'updateProfileImage']);
        // Ratings
        Route::post('/ratings', [SupplierRatingController::class, 'store']);
        // Compliance Documents
        Route::get('/documents', [SupplierDocumentController::class, 'index']);
        Route::post('/documents', [SupplierDocumentController::class, 'store']);
        Route::delete('/documents/{document}', [SupplierDocumentController::class, 'destroy']);
        Route::post('/documents/{document}/resubmit', [SupplierDocumentController::class, 'resubmit']);
        // Content reports
        Route::get('/reports', [SupplierContentReportController::class, 'index']);
        Route::post('/reports', [SupplierContentReportController::class, 'store']);
        // Inquiries
        Route::get('/inquiries', [SupplierInquiryController::class, 'index']);
        Route::get('/inquiries/{inquiry}', [SupplierInquiryController::class, 'show']);
        Route::post('/inquiries/{inquiry}/reply', [SupplierInquiryController::class, 'reply']);
        Route::post('/inquiries/{inquiry}/mark-read', [SupplierInquiryController::class, 'markRead']);
        Route::post('/inquiries/{inquiry}/status', [SupplierInquiryController::class, 'updateStatus']);
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

    // Legacy ratings approval route (kept for compatibility)
    Route::post('/ratings/{rating}/approve', [AdminRatingController::class, 'approve']);
});
