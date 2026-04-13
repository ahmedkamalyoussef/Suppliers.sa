<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'subscription_status')) {
                $table->string('subscription_status')->default('basic')->after('plan');
            }
            if (!Schema::hasColumn('suppliers', 'subscription_plan_id')) {
                $table->foreignId('subscription_plan_id')->nullable()->after('subscription_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['subscription_status', 'subscription_plan_id']);
        });
    }
};
