<?php

use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminContentReportController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDocumentController;
use App\Http\Controllers\Admin\AdminRatingController;
use App\Http\Controllers\Admin\AdminSupplierController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SupplierAuthController;
use App\Http\Controllers\Public\PublicBusinessController;
use App\Http\Controllers\Public\PublicBusinessInquiryController;
use App\Http\Controllers\Public\PublicBusinessReviewController;
use App\Http\Controllers\Public\PublicContentReportController;
use App\Http\Controllers\Shared\BranchController;
use App\Http\Controllers\Shared\BusinessController;
use App\Http\Controllers\Shared\ExportController;
use App\Http\Controllers\Shared\MapController;
use App\Http\Controllers\Shared\NotificationController;
use App\Http\Controllers\Shared\PaymentController;
use App\Http\Controllers\Shared\ReportController;
use App\Http\Controllers\Shared\ReviewController;
use App\Http\Controllers\Shared\SearchController;
use App\Http\Controllers\Shared\SettingsController;
use App\Http\Controllers\Shared\UserController;
use App\Http\Controllers\Supplier\SupplierContentReportController;
use App\Http\Controllers\Supplier\SupplierDashboardController;
use App\Http\Controllers\Supplier\SupplierDocumentController;
use App\Http\Controllers\Supplier\SupplierInquiryController;
use App\Http\Controllers\Supplier\SupplierRatingController;
use App\Http\Controllers\Supplier\ProductImageController;
use App\Http\Controllers\Supplier\ServiceController;
use App\Http\Controllers\Supplier\CertificationController;
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
    Route::get('/businesses/{slug}/reviews', [PublicBusinessReviewController::class, 'index']);
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

    // Protected supplier profile endpoint
    Route::get('/suppliers/{id}', 'App\\Http\\Controllers\\Public\\SupplierProfileController@show');

    // Shared
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [PasswordController::class, 'changePassword']);

    // Admin Profile (Admin can update their own profile)
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'overview']);
        Route::get('/dashboard/analytics', [AdminDashboardController::class, 'analytics']);
        Route::get('/profile', [AdminController::class, 'getProfile']);
        Route::put('/profile', [AdminController::class, 'updateProfile']);
        Route::post('/profile/image', [AdminController::class, 'updateProfileImage']);
        // Admin Supplier Management
        Route::get('/suppliers', [AdminSupplierController::class, 'index']);
        Route::get('/suppliers/{supplier}', [AdminSupplierController::class, 'show']);
        Route::put('/suppliers/{supplier}', [AdminSupplierController::class, 'update']);
        
        // Admin Supplier Product Images
        Route::get('/suppliers/{supplier}/product-images', [AdminProductImageController::class, 'index']);
        Route::post('/suppliers/{supplier}/product-images', [AdminProductImageController::class, 'store']);
        Route::delete('/suppliers/{supplier}/product-images/{image}', [AdminProductImageController::class, 'destroy']);
        
        // Admin Supplier Services
        Route::get('/suppliers/{supplier}/services', [AdminServiceController::class, 'index']);
        Route::post('/suppliers/{supplier}/services', [AdminServiceController::class, 'store']);
        Route::put('/suppliers/{supplier}/services/{service}', [AdminServiceController::class, 'update']);
        Route::delete('/suppliers/{supplier}/services/{service}', [AdminServiceController::class, 'destroy']);
        
        // Admin Supplier Certifications
        Route::get('/suppliers/{supplier}/certifications', [AdminCertificationController::class, 'index']);
        Route::post('/suppliers/{supplier}/certifications', [AdminCertificationController::class, 'store']);
        Route::put('/suppliers/{supplier}/certifications/{certification}', [AdminCertificationController::class, 'update']);
        Route::delete('/suppliers/{supplier}/certifications/{certification}', [AdminCertificationController::class, 'destroy']);
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
        Route::get('/dashboard/analytics', [SupplierDashboardController::class, 'analytics']);
        Route::get('/profile', [SupplierAuthController::class, 'getProfile']);
        Route::put('/profile', [SupplierAuthController::class, 'updateProfile']);
        
        // Product Images
        Route::get('/product-images', [ProductImageController::class, 'index']);
        Route::post('/product-images', [ProductImageController::class, 'store'])
            ->middleware('App\Http\Middleware\CheckSupplierPlanLimit:productImages,8');
        Route::delete('/product-images/{image}', [ProductImageController::class, 'destroy']);
        Route::post('/product-images/reorder', [ProductImageController::class, 'reorder']);
        
        // Services
        Route::get('/services', [ServiceController::class, 'index']);
        Route::post('/services', [ServiceController::class, 'store'])
            ->middleware('App\Http\Middleware\CheckSupplierPlanLimit:services,8');
        Route::put('/services/{service}', [ServiceController::class, 'update']);
        Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
        Route::post('/services/reorder', [ServiceController::class, 'reorder']);
        
        // Certifications
        Route::get('/certifications', [CertificationController::class, 'index']);
        Route::post('/certifications', [CertificationController::class, 'store'])
            ->middleware('App\Http\Middleware\CheckSupplierPlanLimit:certifications,8');
        Route::put('/certifications/{certification}', [CertificationController::class, 'update']);
        Route::delete('/certifications/{certification}', [CertificationController::class, 'destroy']);
        Route::post('/certifications/reorder', [CertificationController::class, 'reorder']);
        Route::patch('/profile', [SupplierAuthController::class, 'updateProfilePartial']);
        Route::post('/profile/image', [SupplierAuthController::class, 'updateProfileImage']);
        // Ratings
        Route::get('/ratings', [SupplierRatingController::class, 'index']);
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
        Route::patch('/{branch}', [BranchController::class, 'updatePartial']);
        Route::delete('/{branch}', [BranchController::class, 'destroy']);
        Route::post('/{branch}/set-main', [BranchController::class, 'setMainBranch']);
    });

    // Legacy ratings approval route (kept for compatibility)
    Route::post('/ratings/{rating}/approve', [AdminRatingController::class, 'approve']);

    /*
    ||--------------------------------------------------------------------------
    || User Management Routes
    ||--------------------------------------------------------------------------
    */
    Route::prefix('users')->group(function () {
        Route::post('/register', [UserController::class, 'register']);
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::patch('/{user}', [UserController::class, 'updatePartial']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        Route::get('/profile', [UserController::class, 'profile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::get('/limits', [UserController::class, 'limits']);
        Route::post('/check-business-limit', [UserController::class, 'checkBusinessLimit']);
    });

    /*
    ||--------------------------------------------------------------------------
    || Business Management Routes
    ||--------------------------------------------------------------------------
    */
    Route::prefix('businesses')->group(function () {
        Route::get('/', [BusinessController::class, 'index']);
        Route::post('/', [BusinessController::class, 'store']);
        Route::get('/{business}', [BusinessController::class, 'show']);
        Route::put('/{business}', [BusinessController::class, 'update']);
        Route::patch('/{business}', [BusinessController::class, 'updatePartial']);
        Route::delete('/{business}', [BusinessController::class, 'destroy']);
        Route::post('/{business}/images', [BusinessController::class, 'uploadImage']);
        Route::delete('/{business}/images/{image}', [BusinessController::class, 'deleteImage']);
        Route::get('/{business}/reviews', [BusinessController::class, 'businessReviews']);
        Route::put('/{business}/location', [BusinessController::class, 'updateLocation']);
    });

    /*
    ||--------------------------------------------------------------------------
    || Search and Filtering Routes
    ||--------------------------------------------------------------------------
    */
    Route::prefix('search')->group(function () {
        Route::get('/businesses', [SearchController::class, 'businesses']);
        Route::get('/suggestions', [SearchController::class, 'suggestions']);
        Route::post('/advanced', [SearchController::class, 'advanced']);
        Route::post('/image-search', [SearchController::class, 'imageSearch']);
    });

    /*
    ||--------------------------------------------------------------------------
    || Payment Routes
    ||--------------------------------------------------------------------------
    */
    Route::prefix('payments')->group(function () {
        Route::post('/create', [PaymentController::class, 'create']);
        Route::get('/{transaction_id}/query', [PaymentController::class, 'query']);
        Route::post('/{transaction_id}/refund', [PaymentController::class, 'refund']);
        Route::post('/callback', [PaymentController::class, 'callback']);
        Route::get('/methods', [PaymentController::class, 'methods']);
    });

    /*
    ||--------------------------------------------------------------------------
    || Reviews and Ratings Routes
    ||--------------------------------------------------------------------------
    */
    Route::prefix('reviews')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
        Route::get('/', [ReviewController::class, 'index']);
        Route::get('/{review}', [ReviewController::class, 'show']);
        Route::put('/{review}', [ReviewController::class, 'update']);
        Route::delete('/{review}', [ReviewController::class, 'destroy']);
        Route::post('/{review}/helpful', [ReviewController::class, 'markHelpful']);
        Route::post('/{review}/report', [ReviewController::class, 'report']);
        Route::post('/{review}/approve', [ReviewController::class, 'approve']);
        Route::post('/{review}/reject', [ReviewController::class, 'reject']);
        Route::get('/pending', [ReviewController::class, 'pending']);
        Route::get('/statistics', [ReviewController::class, 'statistics']);
    });

    /*
    ||--------------------------------------------------------------------------
    || Maps and Location Routes
    ||--------------------------------------------------------------------------
    */
    Route::prefix('maps')->group(function () {
        Route::get('/businesses', [MapController::class, 'businesses']);
        Route::post('/directions', [MapController::class, 'directions']);
        Route::post('/geocode', [MapController::class, 'geocode']);
        Route::post('/reverse-geocode', [MapController::class, 'reverseGeocode']);
        Route::get('/nearby', [MapController::class, 'nearby']);
    });

    /*
    ||--------------------------------------------------------------------------
    || System Settings Routes
    ||--------------------------------------------------------------------------
    */
    Route::prefix('settings')->group(function () {
        Route::get('/general', [SettingsController::class, 'general']);
        Route::put('/general', [SettingsController::class, 'updateGeneral']);
        Route::get('/plans', [SettingsController::class, 'plans']);
        Route::put('/plans', [SettingsController::class, 'updatePlans']);
        Route::get('/payment', [SettingsController::class, 'payment']);
        Route::put('/payment', [SettingsController::class, 'updatePayment']);
        Route::get('/notifications', [SettingsController::class, 'notifications']);
        Route::put('/notifications', [SettingsController::class, 'updateNotifications']);
        Route::get('/security', [SettingsController::class, 'security']);
        Route::put('/security', [SettingsController::class, 'updateSecurity']);
        Route::get('/maintenance', [SettingsController::class, 'maintenance']);
        Route::put('/maintenance', [SettingsController::class, 'updateMaintenance']);
        Route::post('/cache/clear', [SettingsController::class, 'clearCache']);
        Route::get('/system/status', [SettingsController::class, 'systemStatus']);
    });

    /*
    ||--------------------------------------------------------------------------
    || Reports and Analytics Routes
    ||--------------------------------------------------------------------------
    */
    Route::prefix('reports')->group(function () {
        Route::get('/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/users', [ReportController::class, 'users']);
        Route::get('/businesses', [ReportController::class, 'businesses']);
        Route::get('/reviews', [ReportController::class, 'reviews']);
        Route::get('/revenue', [ReportController::class, 'revenue']);
        Route::post('/export', [ReportController::class, 'export']);
    });

    /*
    ||--------------------------------------------------------------------------
    || Notifications Routes
    ||--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'delete']);
        Route::get('/settings', [NotificationController::class, 'settings']);
        Route::put('/settings', [NotificationController::class, 'updateSettings']);
        Route::post('/send', [NotificationController::class, 'sendNotification']);
        Route::post('/send-bulk', [NotificationController::class, 'sendBulk']);
        Route::get('/statistics', [NotificationController::class, 'statistics']);
        Route::get('/templates', [NotificationController::class, 'templates']);
    });

    /*
    ||--------------------------------------------------------------------------
    || Data Export Routes
    ||--------------------------------------------------------------------------
    */
    Route::prefix('exports')->group(function () {
        Route::post('/users', [ExportController::class, 'users']);
        Route::post('/businesses', [ExportController::class, 'businesses']);
        Route::post('/reviews', [ExportController::class, 'reviews']);
        Route::post('/analytics', [ExportController::class, 'analytics']);
        Route::get('/history', [ExportController::class, 'history']);
        Route::get('/download/{filename}', [ExportController::class, 'download']);
    });
});
