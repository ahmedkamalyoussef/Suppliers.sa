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
        Schema::table('analytics_visitor_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('visitor_id')->nullable()->after('supplier_id');
            $table->string('visitor_type')->nullable()->after('visitor_id'); // App\Models\Supplier, App\Models\Customer, etc.
            
            // Index for better performance
            $table->index(['visitor_id', 'visitor_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics_visitor_logs', function (Blueprint $table) {
            $table->dropIndex(['visitor_id', 'visitor_type']);
            $table->dropColumn(['visitor_id', 'visitor_type']);
        });
    }
};
