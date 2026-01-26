<?php

use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminRatingController;
use App\Http\Controllers\Admin\AdminSupplierController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDocumentController;
use App\Http\Controllers\Admin\AdminContentReportController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\PartnershipController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Supplier\SupplierInquiryController;
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
use App\Http\Controllers\Supplier\SupplierRatingController;
use App\Http\Controllers\Supplier\ProductImageController;
use App\Http\Controllers\Supplier\ServiceController;
use App\Http\Controllers\Supplier\SupplierProductController;
use App\Http\Controllers\Supplier\CertificationController;
use App\Http\Controllers\Api\Supplier\BusinessRequestController;
use App\Http\Controllers\PublicBusinessesStatisticsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminSupplierInquiryController;
use App\Http\Controllers\Admin\AdminEmailController;
use App\Http\Controllers\Admin\AdminSupplierCommunicationController;
use App\Http\Controllers\Public\TopSuppliersController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Api\TapPaymentController;
use App\Http\Controllers\Api\Admin\SubscriptionController;

// Simple test route for debugging
Route::get('/', function () {
    return response()->json(['message' => 'API is working', 'timestamp' => now()]);
});

// Public maintenance status (no authentication required)
Route::get('/maintenance/status', [PublicController::class, 'getMaintenanceStatus']);

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
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:5,1');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,1');
    // Get user's profile picture (authenticated user or by ID)
    Route::get('/profile/picture/{id?}', [AuthController::class, 'getProfilePicture'])
        ->where('id', '[0-9]+');
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordController::class, 'forgotPassword']);
    Route::post('/reset-password', [\App\Http\Controllers\Auth\PasswordController::class, 'resetPassword']);
});

/*
||--------------------------------------------------------------------------
|| Public Routes
||--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    // Business statistics
    Route::get('/stats', [PublicBusinessController::class, 'getStats']);
    
    // Business endpoints
    Route::get('/businesses', [PublicBusinessController::class, 'index']);
    Route::post('/businesses/ai-search', [PublicBusinessController::class, 'aiSearch']);
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

// Public business endpoint
Route::get('/suppliers/{id}/business', 'App\\Http\\Controllers\\SupplierController@getSupplierBusiness');

// Public top suppliers endpoints (no authentication required)
Route::get('/suppliers/top-rated', [TopSuppliersController::class, 'topRated']);
Route::get('/suppliers/most-active', [TopSuppliersController::class, 'mostActive']);
Route::get('/suppliers/most-viewed', [TopSuppliersController::class, 'mostViewed']);

// Public inquiries endpoint (no authentication required)
Route::post('/supplier/inquiries', [SupplierInquiryController::class, 'store']);

// Public partnerships endpoint (no authentication required)
Route::get('/partnerships', [PartnershipController::class, 'index']);

// Public businesses statistics endpoints
Route::get('/public/businesses-statistics', [PublicBusinessesStatisticsController::class, 'index']);

/*
||--------------------------------------------------------------------------
|| Tap Payment Routes (Public)
||--------------------------------------------------------------------------
*/
Route::prefix('tap')->group(function () {
    Route::get('/publishable-key', [TapPaymentController::class, 'getPublishableKey']);
    Route::post('/charges', [TapPaymentController::class, 'createCharge']);
    Route::get('/charges/{chargeId}', [TapPaymentController::class, 'retrieveCharge']);
    Route::post('/customers', [TapPaymentController::class, 'createCustomer']);
    Route::post('/webhook', [TapPaymentController::class, 'handleWebhook']);
    
    // Subscription endpoints
    Route::get('/subscription/plans', [TapPaymentController::class, 'getSubscriptionPlans']);
    Route::post('/subscription/payment', [TapPaymentController::class, 'createSubscriptionPayment'])->middleware('auth:sanctum:supplier');
    Route::post('/subscription/payment-test', [TapPaymentController::class, 'createSubscriptionPayment'])->middleware('auth:supplier'); // Temporary test endpoint
    Route::get('/subscription/success', [TapPaymentController::class, 'subscriptionSuccess']);
    Route::get('/subscription/current', [TapPaymentController::class, 'getUserSubscription'])->middleware('auth:sanctum:supplier');
    Route::get('/subscription/history', [TapPaymentController::class, 'getUserSubscriptionHistory'])->middleware('auth:sanctum:supplier');
    Route::get('/subscription/transactions', [TapPaymentController::class, 'getUserTransactions'])->middleware('auth:sanctum:supplier');
});

Route::middleware('auth:sanctum')->group(function () {
    // Protected supplier profile endpoint (only owner can view)
    Route::get('/suppliers/{id}', 'App\\Http\\Controllers\\Public\\SupplierProfileController@show');
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [PasswordController::class, 'change-password']);

    // Admin Profile (Admin can update their own profile)
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'overview']);
        Route::get('/dashboard/analytics', [AdminDashboardController::class, 'analytics']);
        Route::get('/dashboard/analytics/v2', [AdminDashboardController::class, 'dashboardAnalytics']);
        Route::get('/dashboard/analytics/export', [AdminDashboardController::class, 'exportAnalytics']);
        Route::get('/system/settings', [AdminDashboardController::class, 'getSystemSettings']);
        Route::put('/system/settings', [AdminDashboardController::class, 'updateSystemSettings']);
        Route::post('/system/settings/restore', [AdminDashboardController::class, 'restoreSystemDefaults']);
        Route::post('/system/backup', [AdminDashboardController::class, 'createSystemBackup']);
        Route::get('/profile', [AdminController::class, 'getProfile']);
        Route::get('/permissions', [AdminController::class, 'getPermissions']);
        Route::put('/profile', [AdminController::class, 'updateProfile']);
        Route::post('/profile/image', [AdminController::class, 'updateProfileImage']);
        Route::post('/register-super', [AdminController::class, 'registerSuper']);
        Route::get('/suppliers', [AdminSupplierController::class, 'index']);
        Route::post('/suppliers', [AdminSupplierController::class, 'addSupplier']);
        Route::get('/suppliers/export', [AdminSupplierController::class, 'export']);
        Route::get('/suppliers/{supplier}', [AdminSupplierController::class, 'show']);
        Route::put('/suppliers/{supplier}', [AdminSupplierController::class, 'update']);
        
        // Admin Supplier Inquiries
        Route::post('/inquiries/reply', [AdminSupplierInquiryController::class, 'reply']);
        Route::get('/inquiries/list', [AdminSupplierInquiryController::class, 'getInquiries']);
        
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
        Route::get('/content/approved-today', [AdminDocumentController::class, 'approvedToday']);
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
        
        // Partnerships (Content Management)
        Route::post('/partnerships', [PartnershipController::class, 'store']);
        Route::post('/partnerships/{id}', [PartnershipController::class, 'update']);
        Route::delete('/partnerships/{id}', [PartnershipController::class, 'destroy']);
        
        // Businesses Statistics Management
        Route::put('/businesses-statistics', [PublicBusinessesStatisticsController::class, 'update']);
        
        // Admin Supplier Inquiries
        Route::get('/inquiries', [SupplierInquiryController::class, 'index']);
        Route::get('/inquiries/{inquiry}', [SupplierInquiryController::class, 'show']);
        Route::post('/inquiries/{inquiry}/reply', [SupplierInquiryController::class, 'reply']);
        Route::post('/inquiries/{inquiry}/read', [SupplierInquiryController::class, 'markRead']);
        Route::put('/inquiries/{inquiry}/status', [SupplierInquiryController::class, 'updateStatus']);
        Route::get('/inquiries/unread/count', [SupplierInquiryController::class, 'unreadCount']);
        
        // Email Management (Admin Only)
        Route::post('/email/send', [AdminEmailController::class, 'sendEmail']);
        Route::post('/email/send-bulk', [AdminEmailController::class, 'sendBulkEmail']);
        
        // Supplier Communications (Admin Only)
        Route::get('/communications', [AdminSupplierCommunicationController::class, 'getCommunications']);
        Route::get('/communications/summary', [AdminSupplierCommunicationController::class, 'getCommunicationSummary']);
        
        // Subscription Management (Admin Only)
        Route::prefix('subscriptions')->group(function () {
            Route::get('/', [SubscriptionController::class, 'index']);
            Route::get('/statistics', [SubscriptionController::class, 'statistics']);
            Route::get('/transactions', [SubscriptionController::class, 'transactions']);
            Route::get('/payment-statistics', [SubscriptionController::class, 'paymentStatistics']);
            Route::get('/plans', [SubscriptionController::class, 'plans']);
            Route::post('/plans', [SubscriptionController::class, 'createPlan']);
            Route::put('/plans/{id}', [SubscriptionController::class, 'updatePlan']);
            Route::post('/cancel/{id}', [SubscriptionController::class, 'cancelSubscription']);
            Route::get('/monthly-revenue', [SubscriptionController::class, 'monthlyRevenue']);
        });
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
        Route::delete('/account', [SupplierAuthController::class, 'deleteAccount']);
        
        // Public supplier profile view
        Route::get('/{supplier}/view', [\App\Http\Controllers\Supplier\SupplierViewController::class, 'view']);
        
        // Analytics tracking endpoints (authenticated)
        Route::prefix('analytics')->group(function () {
            Route::post('/track-view', [\App\Http\Controllers\Supplier\AnalyticsController::class, 'trackView']);
            Route::post('/track-search', [\App\Http\Controllers\Supplier\AnalyticsController::class, 'trackSearch']);
            Route::get('/charts', [\App\Http\Controllers\Supplier\AnalyticsController::class, 'charts']);
            Route::get('/keywords', [\App\Http\Controllers\Supplier\AnalyticsController::class, 'keywords']);
            Route::get('/insights', [\App\Http\Controllers\Supplier\AnalyticsController::class, 'insights']);
            Route::get('/performance', [\App\Http\Controllers\Supplier\AnalyticsController::class, 'performance']);
            Route::get('/recommendations', [\App\Http\Controllers\Supplier\AnalyticsController::class, 'recommendations']);
            Route::get('/export', [\App\Http\Controllers\Supplier\AnalyticsController::class, 'export']);
        });
        
        // Supplier to Supplier Inquiries (New)
        Route::prefix('supplier-inquiries')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Supplier\SupplierToSupplierInquiryController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Supplier\SupplierToSupplierInquiryController::class, 'store']);
            Route::get('/unread-count', [\App\Http\Controllers\Api\Supplier\SupplierToSupplierInquiryController::class, 'unreadCount']);
            Route::get('/{inquiry}', [\App\Http\Controllers\Api\Supplier\SupplierToSupplierInquiryController::class, 'show']);
            Route::post('/{inquiry}/reply', [\App\Http\Controllers\Api\Supplier\SupplierToSupplierInquiryController::class, 'reply']);
            Route::post('/{id}/read', [\App\Http\Controllers\Api\Supplier\SupplierToSupplierInquiryController::class, 'markAsRead']);
        });
        
        // Messages
        Route::prefix('messages')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Supplier\MessageController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Supplier\MessageController::class, 'store']);
            Route::get('/unread-count', [\App\Http\Controllers\Api\Supplier\MessageController::class, 'unreadCount']);
            Route::post('/{message}/read', [\App\Http\Controllers\Api\Supplier\MessageController::class, 'markAsRead']);
        });
        
        // Unified Inbox
        Route::prefix('inbox')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Supplier\InboxController::class, 'index']);
            Route::post('/mark-read', [\App\Http\Controllers\Api\Supplier\InboxController::class, 'markAsRead']);
            Route::post('/reply', [\App\Http\Controllers\Api\Supplier\InboxController::class, 'reply']);
        });
        
        // Business Requests
        Route::prefix('business-requests')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\Supplier\BusinessRequestController::class, 'store']);
        });
        
        // Regular Inquiries (Old)
        Route::prefix('inquiries')->group(function () {
            Route::get('/', [SupplierInquiryController::class, 'index']);
            Route::get('/unread-count', [SupplierInquiryController::class, 'unreadCount']);
            Route::get('/{inquiry}', [SupplierInquiryController::class, 'show']);
            Route::post('/{inquiry}/reply', [SupplierInquiryController::class, 'reply']);
        });
        Route::put('/profile', [SupplierAuthController::class, 'updateProfile']);
        
        // Product Images
        Route::apiResource('product-images', ProductImageController::class)->except(['update', 'destroy']);
        
        // Products
        Route::apiResource('products', SupplierProductController::class);
        
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
        
        // Supplier Preferences
        Route::get('/preferences', [SupplierAuthController::class, 'getPreferences']);
        Route::put('/preferences', [SupplierAuthController::class, 'updatePreferences']);
        
        // Profile and Business Images
        Route::post('/profile/image', 'App\Http\Controllers\Supplier\SupplierProfileImageController@update');
        Route::post('/business/image', 'App\Http\Controllers\Supplier\SupplierBusinessImageController@update');
        
        // Profile Category
        Route::patch('/profile/category', function (Request $request) {
            $request->validate([
                'category' => 'nullable|string|max:255',
            ]);
            
            $supplier = $request->user();
            $supplier->profile->update([
                'category' => $request->category
            ]);
            
            return response()->json([
                'message' => 'تم تحديث التصنيف بنجاح',
                'category' => $request->category
            ]);
        });
        
        // Get Supplier Location
        Route::get('/location', [SupplierAuthController::class, 'getLocation']);
        
        // Ratings
        Route::get('/ratings', [SupplierRatingController::class, 'index']);
        Route::post('/ratings', [SupplierRatingController::class, 'store']);
        
        // Review Replies
        Route::prefix('ratings/{rating}/replies')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\Supplier\ReviewReplyController::class, 'store']);
            Route::put('/{reply}', [\App\Http\Controllers\Api\Supplier\ReviewReplyController::class, 'update']);
            Route::delete('/{reply}', [\App\Http\Controllers\Api\Supplier\ReviewReplyController::class, 'destroy']);
        });
        // Compliance Documents
        Route::get('/documents', [SupplierDocumentController::class, 'index']);
        Route::post('/documents', [SupplierDocumentController::class, 'store']);
        Route::delete('/documents/{document}', [SupplierDocumentController::class, 'destroy']);
        Route::post('/documents/{document}/resubmit', [SupplierDocumentController::class, 'resubmit']);
        // Content reports
        Route::get('/reports', [SupplierContentReportController::class, 'index']);
        Route::post('/reports', [SupplierContentReportController::class, 'store']);
        
        // Inquiries
        
    });
    Route::get('/inquiries', [SupplierInquiryController::class, 'index']);
    Route::get('/inquiries/{inquiry}', [SupplierInquiryController::class, 'show']);
    Route::post('/inquiries/{inquiry}/reply', [SupplierInquiryController::class, 'reply']);
    Route::get('/inquiries/unread/count', [SupplierInquiryController::class, 'unreadCount']);

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
    || Tap Payment Routes (Authenticated)
    ||--------------------------------------------------------------------------
    */
    Route::prefix('tap')->group(function () {
        Route::post('/charges/{chargeId}/refund', [TapPaymentController::class, 'createRefund']);
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
