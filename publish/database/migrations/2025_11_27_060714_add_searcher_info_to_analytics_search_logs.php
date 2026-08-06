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
        Schema::table('analytics_search_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('searcher_id')->nullable()->after('supplier_id');
            $table->string('searcher_type')->nullable()->after('searcher_id'); // App\Models\Supplier, App\Models\Customer, etc.
            
            // Index for better performance
            $table->index(['searcher_id', 'searcher_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics_search_logs', function (Blueprint $table) {
            $table->dropIndex(['searcher_id', 'searcher_type']);
            $table->dropColumn(['searcher_id', 'searcher_type']);
        });
    }
};
