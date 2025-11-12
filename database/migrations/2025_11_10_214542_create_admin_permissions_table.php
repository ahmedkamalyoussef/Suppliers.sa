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
        Schema::create('admin_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            
            // إدارة المستخدمين (1)
            $table->boolean('user_management_view')->default(false);
            $table->boolean('user_management_edit')->default(false);
            $table->boolean('user_management_delete')->default(false);
            $table->boolean('user_management_full')->default(false);
            
            // إدارة المحتوى (2)
            $table->boolean('content_management_view')->default(false);
            $table->boolean('content_management_supervise')->default(false);
            $table->boolean('content_management_delete')->default(false);
            
            // التحليلات (3)
            $table->boolean('analytics_view')->default(false);
            $table->boolean('analytics_export')->default(false);
            
            // التقارير (4)
            $table->boolean('reports_view')->default(false);
            $table->boolean('reports_create')->default(false);
            
            // النظام (5)
            $table->boolean('system_manage')->default(false);
            $table->boolean('system_settings')->default(false);
            $table->boolean('system_backups')->default(false);
            
            // الدعم (6)
            $table->boolean('support_manage')->default(false);
            
            $table->timestamps();
            
            // Ensure one permission record per admin
            $table->unique('admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_permissions');
    }
};
