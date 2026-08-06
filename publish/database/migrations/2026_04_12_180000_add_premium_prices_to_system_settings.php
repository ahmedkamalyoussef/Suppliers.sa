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
        Schema::table('system_settings', function (Blueprint $table) {
            $table->decimal('premium_monthly_price', 10, 2)->default(290.00)->after('backup_retention_days');
            $table->decimal('premium_annual_price', 10, 2)->default(2900.00)->after('premium_monthly_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['premium_monthly_price', 'premium_annual_price']);
        });
    }
};
