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
        Schema::table('suppliers', function (Blueprint $table) {
            // Notification Preferences
            $table->boolean('email_notifications')->default(true); // Receive notifications via email
            $table->boolean('sms_notifications')->default(false); // Receive urgent notifications via SMS
            $table->boolean('new_inquiries_notifications')->default(true); // When someone contacts your business
            $table->boolean('profile_views_notifications')->default(false); // When someone views your profile
            $table->boolean('weekly_reports')->default(true); // Weekly analytics and performance reports
            $table->boolean('marketing_emails')->default(false); // Tips, updates, and promotional content
            
            // Security Preferences
            $table->enum('profile_visibility', ['public', 'limited'])->default('public'); // Profile visibility
            $table->boolean('show_email_publicly')->default(false); // Show email address publicly
            $table->boolean('show_phone_publicly')->default(false); // Show phone number publicly
            $table->boolean('allow_direct_contact')->default(true); // Allow direct contact through platform
            $table->boolean('allow_search_engine_indexing')->default(true); // Allow search engines to index profile
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Notification Preferences
            $table->dropColumn([
                'email_notifications',
                'sms_notifications',
                'new_inquiries_notifications',
                'profile_views_notifications',
                'weekly_reports',
                'marketing_emails'
            ]);
            
            // Security Preferences
            $table->dropColumn([
                'profile_visibility',
                'show_email_publicly',
                'show_phone_publicly',
                'allow_direct_contact',
                'allow_search_engine_indexing'
            ]);
        });
    }
};
