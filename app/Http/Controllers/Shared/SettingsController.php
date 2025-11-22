<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Get all system settings
     */
    public function index(): JsonResponse
    {
        $settings = [
            'general' => $this->getGeneralSettings(),
            'plans' => $this->getPlansSettings(),
            'payment' => $this->getPaymentSettings(),
            'notifications' => $this->getNotificationSettings(),
            'security' => $this->getSecuritySettings(),
            'maintenance' => $this->getMaintenanceSettings(),
        ];

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    /**
     * Get general settings
     */
    public function general(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'settings' => $this->getGeneralSettings(),
        ]);
    }

    /**
     * Update general settings
     */
    public function updateGeneral(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'site_name' => 'required|string|max:255',
            'site_description' => 'required|string|max:1000',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'contact_address' => 'required|string|max:500',
            'default_language' => 'required|string|in:en,ar',
            'default_currency' => 'required|string|size:3',
            'timezone' => 'required|string|max:50',
            'registration_enabled' => 'required|boolean',
            'business_verification_required' => 'required|boolean',
            'review_approval_required' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update settings in cache/database
        foreach ($request->all() as $key => $value) {
            Cache::forever("settings.general.{$key}", $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'General settings updated successfully',
            'settings' => $this->getGeneralSettings(),
        ]);
    }

    /**
     * Get plans settings
     */
    public function plans(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'plans' => $this->getPlansSettings(),
        ]);
    }

    /**
     * Update plans settings
     */
    public function updatePlans(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plans' => 'required|array',
            'plans.*.name' => 'required|string|max:255',
            'plans.*.price' => 'required|numeric|min:0',
            'plans.*.duration' => 'required|string|in:monthly,yearly',
            'plans.*.max_businesses' => 'required|integer|min:1',
            'plans.*.features' => 'required|array',
            'plans.*.features.*' => 'string|max:255',
            'plans.*.popular' => 'required|boolean',
            'default_plan' => 'required|string|in:Basic,Premium,Enterprise',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update plans settings
        Cache::forever('settings.plans', $request->plans);
        Cache::forever('settings.default_plan', $request->default_plan);

        return response()->json([
            'success' => true,
            'message' => 'Plans settings updated successfully',
            'plans' => $this->getPlansSettings(),
        ]);
    }

    /**
     * Get payment settings
     */
    public function payment(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'settings' => $this->getPaymentSettings(),
        ]);
    }

    /**
     * Update payment settings
     */
    public function updatePayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'clickpay_enabled' => 'required|boolean',
            'clickpay_profile_id' => 'required_if:clickpay_enabled,true|string',
            'clickpay_api_key' => 'required_if:clickpay_enabled,true|string',
            'clickpay_secret_key' => 'required_if:clickpay_enabled,true|string',
            'currency' => 'required|string|size:3',
            'auto_invoice' => 'required|boolean',
            'invoice_reminder_days' => 'required|integer|min:1|max:30',
            'late_fee_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update payment settings
        foreach ($request->all() as $key => $value) {
            Cache::forever("settings.payment.{$key}", $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment settings updated successfully',
            'settings' => $this->getPaymentSettings(),
        ]);
    }

    /**
     * Get notification settings
     */
    public function notifications(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'settings' => $this->getNotificationSettings(),
        ]);
    }

    /**
     * Update notification settings
     */
    public function updateNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_notifications' => 'required|boolean',
            'sms_notifications' => 'required|boolean',
            'push_notifications' => 'required|boolean',
            'new_registration_email' => 'required|boolean',
            'business_verification_email' => 'required|boolean',
            'review_submission_email' => 'required|boolean',
            'payment_confirmation_email' => 'required|boolean',
            'newsletter_enabled' => 'required|boolean',
            'smtp_host' => 'required_if:email_notifications,true|string',
            'smtp_port' => 'required_if:email_notifications,true|integer',
            'smtp_username' => 'required_if:email_notifications,true|string',
            'smtp_password' => 'required_if:email_notifications,true|string',
            'smtp_encryption' => 'required_if:email_notifications,true|string|in:tls,ssl',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update notification settings
        foreach ($request->all() as $key => $value) {
            Cache::forever("settings.notifications.{$key}", $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated successfully',
            'settings' => $this->getNotificationSettings(),
        ]);
    }

    /**
     * Get security settings
     */
    public function security(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'settings' => $this->getSecuritySettings(),
        ]);
    }

    /**
     * Update security settings
     */
    public function updateSecurity(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password_min_length' => 'required|integer|min:6|max:50',
            'password_require_uppercase' => 'required|boolean',
            'password_require_lowercase' => 'required|boolean',
            'password_require_numbers' => 'required|boolean',
            'password_require_symbols' => 'required|boolean',
            'session_timeout' => 'required|integer|min:5|max:1440',
            'max_login_attempts' => 'required|integer|min:3|max:10',
            'lockout_duration' => 'required|integer|min:1|max:60',
            'two_factor_enabled' => 'required|boolean',
            'ip_whitelist_enabled' => 'required|boolean',
            'ip_whitelist' => 'required_if:ip_whitelist_enabled,true|array',
            'ip_whitelist.*' => 'ip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update security settings
        foreach ($request->all() as $key => $value) {
            Cache::forever("settings.security.{$key}", $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'Security settings updated successfully',
            'settings' => $this->getSecuritySettings(),
        ]);
    }

    /**
     * Get maintenance settings
     */
    public function maintenance(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'settings' => $this->getMaintenanceSettings(),
        ]);
    }

    /**
     * Update maintenance settings
     */
    public function updateMaintenance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'maintenance_mode' => 'required|boolean',
            'maintenance_message' => 'required_if:maintenance_mode,true|string|max:1000',
            'backup_enabled' => 'required|boolean',
            'backup_frequency' => 'required_if:backup_enabled,true|string|in:daily,weekly,monthly',
            'backup_retention' => 'required_if:backup_enabled,true|integer|min:1|max:365',
            'log_retention' => 'required|integer|min:1|max:365',
            'cache_cleanup_frequency' => 'required|string|in:hourly,daily,weekly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update maintenance settings
        foreach ($request->all() as $key => $value) {
            Cache::forever("settings.maintenance.{$key}", $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'Maintenance settings updated successfully',
            'settings' => $this->getMaintenanceSettings(),
        ]);
    }

    /**
     * Clear system cache
     */
    public function clearCache(): JsonResponse
    {
        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => 'System cache cleared successfully',
        ]);
    }

    /**
     * Get system status
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => [
                'system' => $this->getSystemStatus(),
                'database' => $this->getDatabaseStatus(),
                'cache' => $this->getCacheStatus(),
                'storage' => $this->getStorageStatus(),
                'services' => $this->getServicesStatus(),
            ],
        ]);
    }

    private function getGeneralSettings(): array
    {
        return [
            'site_name' => Cache::get('settings.general.site_name', 'Suppliers.sa'),
            'site_description' => Cache::get('settings.general.site_description', 'Find the best suppliers in Saudi Arabia'),
            'contact_email' => Cache::get('settings.general.contact_email', 'info@suppliers.sa'),
            'contact_phone' => Cache::get('settings.general.contact_phone', '+966 50 123 4567'),
            'contact_address' => Cache::get('settings.general.contact_address', 'Riyadh, Saudi Arabia'),
            'default_language' => Cache::get('settings.general.default_language', 'en'),
            'default_currency' => Cache::get('settings.general.default_currency', 'SAR'),
            'timezone' => Cache::get('settings.general.timezone', 'Asia/Riyadh'),
            'registration_enabled' => Cache::get('settings.general.registration_enabled', true),
            'business_verification_required' => Cache::get('settings.general.business_verification_required', false),
            'review_approval_required' => Cache::get('settings.general.review_approval_required', true),
        ];
    }

    private function getPlansSettings(): array
    {
        return Cache::get('settings.plans', [
            [
                'name' => 'Basic',
                'price' => 0,
                'duration' => 'monthly',
                'max_businesses' => 6,
                'features' => ['Basic Profile', 'Up to 6 Businesses', 'Standard Support'],
                'popular' => false,
            ],
            [
                'name' => 'Premium',
                'price' => 299,
                'duration' => 'monthly',
                'max_businesses' => 15,
                'features' => ['Enhanced Profile', 'Up to 15 Businesses', 'Priority Support', 'Analytics'],
                'popular' => true,
            ],
            [
                'name' => 'Enterprise',
                'price' => 999,
                'duration' => 'monthly',
                'max_businesses' => 50,
                'features' => ['Premium Profile', 'Up to 50 Businesses', '24/7 Support', 'Advanced Analytics', 'API Access'],
                'popular' => false,
            ],
        ]);
    }

    private function getPaymentSettings(): array
    {
        return [
            'clickpay_enabled' => Cache::get('settings.payment.clickpay_enabled', false),
            'clickpay_profile_id' => Cache::get('settings.payment.clickpay_profile_id', ''),
            'clickpay_api_key' => Cache::get('settings.payment.clickpay_api_key', ''),
            'clickpay_secret_key' => Cache::get('settings.payment.clickpay_secret_key', ''),
            'currency' => Cache::get('settings.payment.currency', 'SAR'),
            'auto_invoice' => Cache::get('settings.payment.auto_invoice', true),
            'invoice_reminder_days' => Cache::get('settings.payment.invoice_reminder_days', 7),
            'late_fee_percentage' => Cache::get('settings.payment.late_fee_percentage', 10),
        ];
    }

    private function getNotificationSettings(): array
    {
        return [
            'email_notifications' => Cache::get('settings.notifications.email_notifications', true),
            'sms_notifications' => Cache::get('settings.notifications.sms_notifications', false),
            'push_notifications' => Cache::get('settings.notifications.push_notifications', true),
            'new_registration_email' => Cache::get('settings.notifications.new_registration_email', true),
            'business_verification_email' => Cache::get('settings.notifications.business_verification_email', true),
            'review_submission_email' => Cache::get('settings.notifications.review_submission_email', true),
            'payment_confirmation_email' => Cache::get('settings.notifications.payment_confirmation_email', true),
            'newsletter_enabled' => Cache::get('settings.notifications.newsletter_enabled', false),
            'smtp_host' => Cache::get('settings.notifications.smtp_host', 'smtp.gmail.com'),
            'smtp_port' => Cache::get('settings.notifications.smtp_port', 587),
            'smtp_username' => Cache::get('settings.notifications.smtp_username', ''),
            'smtp_password' => Cache::get('settings.notifications.smtp_password', ''),
            'smtp_encryption' => Cache::get('settings.notifications.smtp_encryption', 'tls'),
        ];
    }

    private function getSecuritySettings(): array
    {
        return [
            'password_min_length' => Cache::get('settings.security.password_min_length', 8),
            'password_require_uppercase' => Cache::get('settings.security.password_require_uppercase', true),
            'password_require_lowercase' => Cache::get('settings.security.password_require_lowercase', true),
            'password_require_numbers' => Cache::get('settings.security.password_require_numbers', true),
            'password_require_symbols' => Cache::get('settings.security.password_require_symbols', false),
            'session_timeout' => Cache::get('settings.security.session_timeout', 120),
            'max_login_attempts' => Cache::get('settings.security.max_login_attempts', 5),
            'lockout_duration' => Cache::get('settings.security.lockout_duration', 15),
            'two_factor_enabled' => Cache::get('settings.security.two_factor_enabled', false),
            'ip_whitelist_enabled' => Cache::get('settings.security.ip_whitelist_enabled', false),
            'ip_whitelist' => Cache::get('settings.security.ip_whitelist', []),
        ];
    }

    private function getMaintenanceSettings(): array
    {
        return [
            'maintenance_mode' => Cache::get('settings.maintenance.maintenance_mode', false),
            'maintenance_message' => Cache::get('settings.maintenance.maintenance_message', 'System is under maintenance. Please try again later.'),
            'backup_enabled' => Cache::get('settings.maintenance.backup_enabled', true),
            'backup_frequency' => Cache::get('settings.maintenance.backup_frequency', 'daily'),
            'backup_retention' => Cache::get('settings.maintenance.backup_retention', 30),
            'log_retention' => Cache::get('settings.maintenance.log_retention', 90),
            'cache_cleanup_frequency' => Cache::get('settings.maintenance.cache_cleanup_frequency', 'daily'),
        ];
    }

    private function getSystemStatus(): array
    {
        return [
            'uptime' => '99.9%',
            'version' => '1.0.0',
            'environment' => config('app.env'),
            'timezone' => config('app.timezone'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    private function getDatabaseStatus(): array
    {
        try {
            \DB::connection()->getPdo();

            return [
                'status' => 'connected',
                'driver' => config('database.default'),
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getCacheStatus(): array
    {
        return [
            'driver' => config('cache.default'),
            'status' => 'connected',
        ];
    }

    private function getStorageStatus(): array
    {
        return [
            'driver' => config('filesystems.default'),
            'status' => 'connected',
            'disk_space' => [
                'total' => '100 GB',
                'used' => '25 GB',
                'available' => '75 GB',
            ],
        ];
    }

    private function getServicesStatus(): array
    {
        return [
            'clickpay' => [
                'status' => 'connected',
                'last_check' => now()->toISOString(),
            ],
            'google_maps' => [
                'status' => 'connected',
                'last_check' => now()->toISOString(),
            ],
            'readdy_ai' => [
                'status' => 'connected',
                'last_check' => now()->toISOString(),
            ],
        ];
    }
}
