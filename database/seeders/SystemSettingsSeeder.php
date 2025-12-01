<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SystemSettings;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SystemSettings::create([
            // Basic Site Settings
            'site_name' => 'Suppliers.sa',
            'contact_email' => 'contact@suppliers.sa',
            'support_email' => 'support@suppliers.sa',
            'site_description' => 'Professional suppliers directory platform connecting businesses with trusted suppliers across Saudi Arabia.',
            'maintenance_mode' => false,
            
            // Business Settings
            'maximum_photos_per_business' => 10,
            'maximum_description_characters' => 1000,
            'auto_approve_businesses' => false,
            'business_verification_required' => true,
            'premium_features_enabled' => true,
            
            // Security Settings
            'maximum_login_attempts' => 5,
            'session_timeout_minutes' => 120,
            'require_two_factor_authentication' => false,
            'strong_password_required' => true,
            'data_encryption_enabled' => true,
            
            // Notification Settings
            'email_notifications' => true,
            'sms_notifications' => false,
            'push_notifications' => true,
            'system_alerts' => true,
            'maintenance_notifications' => true,
            
            // System Settings
            'backup_retention_days' => 30,
        ]);
    }
}
