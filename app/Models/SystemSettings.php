<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSettings extends Model
{
    protected $fillable = [
        // Basic Site Settings
        'site_name',
        'contact_email', 
        'support_email',
        'site_description',
        'maintenance_mode',
        
        // Business Settings
        'maximum_photos_per_business',
        'maximum_description_characters',
        'auto_approve_businesses',
        'business_verification_required',
        'premium_features_enabled',
        
        // Security Settings
        'maximum_login_attempts',
        'session_timeout_minutes',
        'require_two_factor_authentication',
        'strong_password_required',
        'data_encryption_enabled',
        
        // Notification Settings
        'email_notifications',
        'sms_notifications',
        'push_notifications',
        'system_alerts',
        'maintenance_notifications',
        
        // System Settings
        'backup_retention_days',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'auto_approve_businesses' => 'boolean',
        'business_verification_required' => 'boolean',
        'premium_features_enabled' => 'boolean',
        'require_two_factor_authentication' => 'boolean',
        'strong_password_required' => 'boolean',
        'data_encryption_enabled' => 'boolean',
        'email_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'system_alerts' => 'boolean',
        'maintenance_notifications' => 'boolean',
    ];
}
