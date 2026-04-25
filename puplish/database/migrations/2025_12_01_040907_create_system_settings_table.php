<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            
            // Basic Site Settings
            $table->string('site_name')->default('Suppliers.sa');
            $table->string('contact_email')->default('contact@suppliers.sa');
            $table->string('support_email')->default('support@suppliers.sa');
            $table->text('site_description')->nullable();
            $table->boolean('maintenance_mode')->default(false);
            
            // Business Settings
            $table->integer('maximum_photos_per_business')->default(10);
            $table->integer('maximum_description_characters')->default(1000);
            $table->boolean('auto_approve_businesses')->default(false);
            $table->boolean('business_verification_required')->default(true);
            $table->boolean('premium_features_enabled')->default(true);
            
            // Security Settings
            $table->integer('maximum_login_attempts')->default(5);
            $table->integer('session_timeout_minutes')->default(120);
            $table->boolean('require_two_factor_authentication')->default(false);
            $table->boolean('strong_password_required')->default(true);
            $table->boolean('data_encryption_enabled')->default(true);
            
            // Notification Settings
            $table->boolean('email_notifications')->default(true);
            $table->boolean('sms_notifications')->default(false);
            $table->boolean('push_notifications')->default(true);
            $table->boolean('system_alerts')->default(true);
            $table->boolean('maintenance_notifications')->default(true);
            
            // System Settings
            $table->integer('backup_retention_days')->default(30);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
